#!/bin/bash

# Laravel Deployment Script with Nginx and PHP-FPM
# Updated with all fixes from troubleshooting
# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PROJECT_PATH="/home/yuuki/DccpAdminV3"
PROJECT_USER="yuuki" # Keep original user for development
DOMAIN="localhost" # Change this to your actual domain
PHP_INI_PATH="/etc/php/php.ini"

# --- Helper Functions ---
info() {
    echo "[INFO] $1"
}

warn() {
    echo "[WARN] $1"
}

error() {
    echo "[ERROR] $1" >&2
    exit 1
}

success() {
    echo "[SUCCESS] $1"
}

# Check if a service is running
is_service_running() {
    systemctl is-active --quiet "$1"
}

# Check if a service exists
service_exists() {
    systemctl list-unit-files --type=service | grep -q "^$1.service"
}

# --- Main Functions ---

check_root() {
    if [ "$EUID" -ne 0 ]; then
        error "This script must be run as root. Please use sudo."
    fi
}

validate_project() {
    info "Validating project structure..."

    if [ ! -d "$PROJECT_PATH" ]; then
        error "Project directory $PROJECT_PATH does not exist."
    fi

    if [ ! -f "$PROJECT_PATH/artisan" ]; then
        error "Laravel artisan file not found. Is this a Laravel project?"
    fi

    if [ ! -f "$PROJECT_PATH/composer.json" ]; then
        error "composer.json not found. Is this a valid PHP project?"
    fi

    success "Project structure validated."
}

check_dependencies() {
    info "Checking system dependencies..."

    # Check if required packages are installed
    local missing_packages=()
    local required_packages=("nginx" "php" "php-fpm" "composer" "curl")

    for package in "${required_packages[@]}"; do
        if ! pacman -Q "$package" &> /dev/null; then
            missing_packages+=("$package")
        fi
    done

    if [ ${#missing_packages[@]} -gt 0 ]; then
        error "Missing required packages: ${missing_packages[*]}. Please install them first."
    fi

    success "All dependencies are installed."
}

fix_php_configuration() {
    info "Fixing PHP configuration..."

    # Extensions that need to be loaded as modules
    local module_extensions=("gd" "intl" "pdo_mysql" "exif" "zip" "curl" "sockets")
    # Built-in extensions that should NOT be loaded as modules (causes errors)
    local builtin_extensions=("mbstring" "xml" "tokenizer")

    local changes_made=false

    # Enable module extensions
    for ext in "${module_extensions[@]}"; do
        if ! grep -q "^extension=$ext" "$PHP_INI_PATH"; then
            if grep -q "^;extension=$ext" "$PHP_INI_PATH"; then
                sed -i "s/;extension=$ext/extension=$ext/g" "$PHP_INI_PATH"
                info "Enabled extension: $ext"
                changes_made=true
            else
                echo "extension=$ext" >> "$PHP_INI_PATH"
                info "Added extension: $ext"
                changes_made=true
            fi
        fi
    done

    # Remove built-in extensions from being loaded as modules (they cause warnings)
    for ext in "${builtin_extensions[@]}"; do
        if grep -q "^extension=$ext" "$PHP_INI_PATH"; then
            sed -i "s/^extension=$ext/;extension=$ext/g" "$PHP_INI_PATH"
            info "Disabled module loading for built-in extension: $ext"
            changes_made=true
        fi
    done

    if [ "$changes_made" = true ]; then
        info "PHP configuration updated."
    fi
}

setup_laravel_project() {
    info "Setting up Laravel project (keeping original ownership)..."

    cd "$PROJECT_PATH"

    # Create .env if it doesn't exist
    if [ ! -f ".env" ]; then
        info "Creating .env file from .env.example..."
        sudo -u "$PROJECT_USER" cp .env.example .env
    fi

    # Generate app key if not set
    if ! grep -q "^APP_KEY=.*[^=]" .env; then
        info "Generating application key..."
        sudo -u "$PROJECT_USER" php artisan key:generate
    fi

    # Create storage link if needed
    if [ ! -L "public/storage" ]; then
        info "Creating storage link..."
        sudo -u "$PROJECT_USER" php artisan storage:link
    fi

    success "Laravel project setup completed."
}

fix_file_permissions() {
    info "Setting up proper file permissions for web server access..."

    # Give execute permission to home directory and project directory
    # This allows the web server to traverse to the project files
    chmod +x /home/"$PROJECT_USER"
    chmod +x "$PROJECT_PATH"

    # Set proper permissions for public directory (readable by web server)
    chmod -R 755 "$PROJECT_PATH/public"

    # Set proper permissions for storage directories (writable by both user and web server)
    chown -R "$PROJECT_USER":http "$PROJECT_PATH/storage"
    chmod -R 775 "$PROJECT_PATH/storage"

    # Set proper permissions for bootstrap cache (writable by both user and web server)
    chown -R "$PROJECT_USER":http "$PROJECT_PATH/bootstrap/cache"
    chmod -R 775 "$PROJECT_PATH/bootstrap/cache"

    success "File permissions configured properly."
}

configure_nginx() {
    info "Configuring Nginx for Laravel..."

    # Backup existing nginx.conf if it exists
    if [ -f /etc/nginx/nginx.conf ]; then
        cp /etc/nginx/nginx.conf "/etc/nginx/nginx.conf.backup.$(date +%s)"
    fi

    # Create clean nginx.conf
    cat <<EOF > /etc/nginx/nginx.conf
user http;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # Logging
    log_format main '\$remote_addr - \$remote_user [\$time_local] "\$request" '
                    '\$status \$body_bytes_sent "\$http_referer" '
                    '"\$http_user_agent" "\$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;
    error_log /var/log/nginx/error.log warn;

    # Performance
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 4096;
    client_max_body_size 100M;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Include server configurations
    include /etc/nginx/sites-enabled/*;
}
EOF

    # Create sites directories
    mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled

    # Remove default configurations
    rm -f /etc/nginx/sites-enabled/default

    # Create Laravel site configuration using traditional PHP-FPM approach
    local SITE_CONFIG="/etc/nginx/sites-available/laravel.conf"
    cat <<EOF > "$SITE_CONFIG"
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root $PROJECT_PATH/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }

    location = /robots.txt {
        access_log off;
        log_not_found off;
    }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Security headers
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    # Static assets optimization
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
}
EOF

    # Enable the site
    ln -sf "$SITE_CONFIG" /etc/nginx/sites-enabled/laravel.conf

    # Test nginx configuration
    if ! nginx -t; then
        error "Nginx configuration test failed. Please check the configuration."
    fi

    success "Nginx configuration completed."
}

manage_services() {
    info "Managing system services..."

    # Stop services if running to reconfigure
    if is_service_running "laravel-octane"; then
        info "Stopping existing Laravel Octane service..."
        systemctl stop laravel-octane
        systemctl disable laravel-octane || true
    fi

    if is_service_running "nginx"; then
        info "Stopping Nginx for reconfiguration..."
        systemctl stop nginx
    fi

    # Enable and start PHP-FPM (traditional Laravel deployment)
    info "Starting PHP-FPM service..."
    systemctl enable --now php-fpm

    if ! is_service_running "php-fpm"; then
        error "Failed to start PHP-FPM service. Check logs with: journalctl -u php-fpm"
    fi

    info "Starting Nginx service..."
    systemctl enable --now nginx

    if ! is_service_running "nginx"; then
        error "Failed to start Nginx service. Check logs with: journalctl -u nginx"
    fi

    success "All services started successfully."
}

optimize_laravel() {
    info "Optimizing Laravel for production..."

    cd "$PROJECT_PATH"

    # Laravel optimization for production
    sudo -u "$PROJECT_USER" php artisan optimize

    success "Laravel optimization completed."
}

test_deployment() {
    info "Testing the deployment..."

    # Wait a moment for services to be fully ready
    sleep 3

    # Test main application
    info "Testing main application..."
    local response=$(curl -s -w "%{http_code}" --connect-timeout 10 "http://$DOMAIN" -o /tmp/test_response.html 2>/dev/null)

    if [ "$response" = "200" ]; then
        success "Application is accessible through Nginx (HTTP $response)"
        info "Response saved to /tmp/test_response.html for inspection."
    else
        warn "Received HTTP $response - check /tmp/test_response.html for details"
        info "This might be normal if your Laravel routes aren't fully configured."
    fi

    # Test health endpoint
    info "Testing Laravel health endpoint..."
    local health_response=$(curl -s -w "%{http_code}" --connect-timeout 10 "http://$DOMAIN/up" -o /tmp/health_response.html 2>/dev/null)

    if [ "$health_response" = "200" ]; then
        success "Health endpoint is working!"
    else
        warn "Health endpoint returned HTTP $health_response."
        info "This might be normal depending on your Laravel configuration."
    fi
}

show_status() {
    info "=== Deployment Status ==="
    echo "Project Path: $PROJECT_PATH"
    echo "Project User: $PROJECT_USER"
    echo "Domain: $DOMAIN"
    echo "Setup Type: Traditional Laravel + Nginx + PHP-FPM"
    echo ""
    echo "Service Status:"
    echo "- PHP-FPM: $(systemctl is-active php-fpm)"
    echo "- Nginx: $(systemctl is-active nginx)"
    echo ""
    echo "URLs:"
    echo "- Application: http://$DOMAIN"
    echo "- Health Check: http://$DOMAIN/up"
    echo ""
    echo "Useful Commands:"
    echo "- View PHP-FPM logs: journalctl -u php-fpm -f"
    echo "- View Nginx logs: journalctl -u nginx -f"
    echo "- Restart PHP-FPM: systemctl restart php-fpm"
    echo "- Restart Nginx: systemctl restart nginx"
    echo "- Laravel optimize: cd $PROJECT_PATH && php artisan optimize"
    echo "- Clear Laravel cache: cd $PROJECT_PATH && php artisan optimize:clear"
    echo ""
    echo "Development Notes:"
    echo "✓ File ownership preserved for development ($PROJECT_USER)"
    echo "✓ Traditional PHP-FPM setup (stable and reliable)"
    echo "✓ Storage and cache directories have proper permissions"
    echo "✓ All PHP extensions properly configured"
    echo ""
}

create_status_script() {
    info "Creating deployment status script..."

    cat <<'SCRIPT_EOF' > "$PROJECT_PATH/deployment_status.sh"
#!/bin/bash

# Deployment Status Summary Script
PROJECT_PATH="/home/yuuki/DccpAdminV3"
PROJECT_USER="yuuki"
DOMAIN="localhost"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

info() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }
success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }

is_service_running() { systemctl is-active --quiet "$1"; }

echo "=============================================="
echo "    Laravel Deployment Status Summary"
echo "=============================================="
echo ""

echo "📁 Project Information:"
echo "   Path: $PROJECT_PATH"
echo "   User: $PROJECT_USER"
echo "   Domain: $DOMAIN"
echo ""

echo "🔧 Service Status:"
if is_service_running "nginx"; then
    success "Nginx: Running"
else
    error "Nginx: Not running"
fi

if is_service_running "php-fpm"; then
    success "PHP-FPM: Running"
else
    error "PHP-FPM: Not running"
fi
echo ""

echo "🌍 Connectivity Tests:"
if curl -s -f --connect-timeout 5 "http://$DOMAIN" > /dev/null 2>&1; then
    success "Main application: Accessible (HTTP 200)"
else
    error "Main application: Not accessible"
fi

if curl -s -f --connect-timeout 5 "http://$DOMAIN/up" > /dev/null 2>&1; then
    success "Health endpoint: Working"
else
    warn "Health endpoint: Not accessible or returns error"
fi
echo ""

echo "📋 Useful Commands:"
echo "   • Test application:    curl http://$DOMAIN"
echo "   • Test health check:   curl http://$DOMAIN/up"
echo "   • View Nginx logs:     journalctl -u nginx -f"
echo "   • View PHP-FPM logs:   journalctl -u php-fpm -f"
echo "   • Laravel optimize:    cd $PROJECT_PATH && php artisan optimize"
echo ""

echo "=============================================="
success "Deployment Status Check Complete"
echo "=============================================="
SCRIPT_EOF

    chmod +x "$PROJECT_PATH/deployment_status.sh"
    chown "$PROJECT_USER":"$PROJECT_USER" "$PROJECT_PATH/deployment_status.sh"

    success "Status script created at $PROJECT_PATH/deployment_status.sh"
}

# --- Main Execution ---
main() {
    info "Starting Laravel deployment with Nginx and PHP-FPM..."

    check_root
    validate_project
    check_dependencies
    fix_php_configuration
    setup_laravel_project
    fix_file_permissions
    configure_nginx
    manage_services
    optimize_laravel
    test_deployment
    create_status_script
    show_status

    success "=== Deployment completed successfully! ==="
    info "Your Laravel application is now running with PHP-FPM behind Nginx."
    info "File ownership has been preserved for development."
    info "Run '$PROJECT_PATH/deployment_status.sh' anytime to check status."
}

main "$@"
