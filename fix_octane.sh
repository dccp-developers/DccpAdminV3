#!/bin/bash

# Quick fix script for Laravel Octane issues
set -e

PROJECT_PATH="/home/yuuki/DccpAdminV3"
PROJECT_USER="yuuki"
OCTANE_PORT="8000"
PHP_INI_PATH="/etc/php/php.ini"

info() {
    echo "[INFO] $1"
}

error() {
    echo "[ERROR] $1" >&2
    exit 1
}

success() {
    echo "[SUCCESS] $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    error "This script must be run as root. Please use sudo."
fi

info "Fixing Laravel Octane deployment issues..."

# 1. Fix PHP configuration - remove built-in extensions that cause warnings
info "Fixing PHP configuration..."
if grep -q "^extension=mbstring" "$PHP_INI_PATH"; then
    sed -i 's/^extension=mbstring/;extension=mbstring/' "$PHP_INI_PATH"
    info "Disabled mbstring module loading (built-in)"
fi

if grep -q "^extension=xml" "$PHP_INI_PATH"; then
    sed -i 's/^extension=xml/;extension=xml/' "$PHP_INI_PATH"
    info "Disabled xml module loading (built-in)"
fi

if grep -q "^extension=tokenizer" "$PHP_INI_PATH"; then
    sed -i 's/^extension=tokenizer/;extension=tokenizer/' "$PHP_INI_PATH"
    info "Disabled tokenizer module loading (built-in)"
fi

# 2. Stop current services
info "Stopping services..."
systemctl stop laravel-octane || true
systemctl stop nginx || true

# 3. Remove and reinstall Octane properly
info "Reinstalling Laravel Octane with proper configuration..."
cd "$PROJECT_PATH"

# Remove existing Octane installation
sudo -u "$PROJECT_USER" composer remove laravel/octane --no-interaction || true

# Install Octane
sudo -u "$PROJECT_USER" composer require laravel/octane --no-interaction

# Install FrankenPHP without trying to replace system binary
info "Configuring Octane for FrankenPHP..."
sudo -u "$PROJECT_USER" php artisan octane:install frankenphp --no-interaction

# 4. Create a better systemd service
info "Creating improved systemd service..."
cat <<EOF > /etc/systemd/system/laravel-octane.service
[Unit]
Description=Laravel Octane Server
After=network.target
Requires=network.target

[Service]
Type=simple
User=$PROJECT_USER
Group=$PROJECT_USER
WorkingDirectory=$PROJECT_PATH
ExecStart=/usr/bin/php artisan octane:start --server=frankenphp --host=127.0.0.1 --port=$OCTANE_PORT --no-interaction
Restart=always
RestartSec=3
StandardOutput=journal
StandardError=journal
SyslogIdentifier=laravel-octane
KillMode=mixed
KillSignal=SIGTERM
TimeoutStopSec=30

# Environment variables
Environment=APP_ENV=production
Environment=LARAVEL_OCTANE_WATCH=false

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload

# 5. Start services
info "Starting services..."
systemctl enable --now laravel-octane
sleep 5

if systemctl is-active --quiet laravel-octane; then
    success "Laravel Octane service started successfully"
else
    error "Laravel Octane service failed to start. Check logs: journalctl -u laravel-octane -n 20"
fi

systemctl enable --now nginx
if systemctl is-active --quiet nginx; then
    success "Nginx service started successfully"
else
    error "Nginx service failed to start. Check logs: journalctl -u nginx -n 20"
fi

# 6. Test the deployment
info "Testing deployment..."
sleep 3

# Test Octane directly
if curl -s -f --connect-timeout 5 "http://127.0.0.1:$OCTANE_PORT" > /dev/null; then
    success "Direct Octane connection working"
else
    error "Direct Octane connection failed"
fi

# Test through Nginx
response=$(curl -s -w "%{http_code}" --connect-timeout 10 "http://localhost" -o /tmp/test_response.html 2>/dev/null)
if [ "$response" = "200" ]; then
    success "Application accessible through Nginx (HTTP $response)"
else
    info "Received HTTP $response - check /tmp/test_response.html for details"
fi

info "=== Service Status ==="
echo "Laravel Octane: $(systemctl is-active laravel-octane)"
echo "Nginx: $(systemctl is-active nginx)"
echo ""
echo "Test your application:"
echo "- curl http://localhost"
echo "- curl http://localhost/up"
echo ""
echo "View logs:"
echo "- journalctl -u laravel-octane -f"
echo "- journalctl -u nginx -f"

success "Fix script completed!"
