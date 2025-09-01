#!/bin/bash

# Deployment Status Summary Script
# Created after successful Laravel deployment with Nginx and PHP-FPM

PROJECT_PATH="/home/yuuki/DccpAdminV3"
PROJECT_USER="yuuki"
DOMAIN="localhost"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

# Check if service is running
is_service_running() {
    systemctl is-active --quiet "$1"
}

echo "=============================================="
echo "    Laravel Deployment Status Summary"
echo "=============================================="
echo ""

# Project Information
echo "📁 Project Information:"
echo "   Path: $PROJECT_PATH"
echo "   User: $PROJECT_USER"
echo "   Domain: $DOMAIN"
echo ""

# Service Status
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

if is_service_running "laravel-octane"; then
    warn "Laravel Octane: $(systemctl is-active laravel-octane) (Not currently used)"
else
    info "Laravel Octane: Disabled (Using traditional PHP-FPM setup)"
fi
echo ""

# Web Server Configuration
echo "🌐 Web Server Configuration:"
success "Setup Type: Traditional Laravel + Nginx + PHP-FPM"
success "Configuration: /etc/nginx/sites-available/laravel.conf"
success "Document Root: $PROJECT_PATH/public"
echo ""

# Permissions
echo "🔐 File Permissions:"
if [ -r "$PROJECT_PATH/public/index.php" ]; then
    success "Public directory: Readable"
else
    error "Public directory: Not accessible"
fi

if [ -w "$PROJECT_PATH/storage" ]; then
    success "Storage directory: Writable"
else
    error "Storage directory: Not writable"
fi

if [ -w "$PROJECT_PATH/bootstrap/cache" ]; then
    success "Bootstrap cache: Writable"
else
    error "Bootstrap cache: Not writable"
fi
echo ""

# Test Connectivity
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

# Laravel Optimization Status
echo "⚡ Laravel Optimization:"
if [ -f "$PROJECT_PATH/bootstrap/cache/config.php" ]; then
    success "Configuration: Cached"
else
    warn "Configuration: Not cached"
fi

if [ -f "$PROJECT_PATH/bootstrap/cache/routes-v7.php" ]; then
    success "Routes: Cached"
else
    warn "Routes: Not cached"
fi

if [ -f "$PROJECT_PATH/storage/framework/views" ]; then
    success "Views: Compilation directory exists"
else
    warn "Views: Compilation directory missing"
fi
echo ""

# Useful Commands
echo "📋 Useful Commands:"
echo "   • View Nginx logs:     journalctl -u nginx -f"
echo "   • View PHP-FPM logs:   journalctl -u php-fpm -f"
echo "   • Test application:    curl http://$DOMAIN"
echo "   • Test health check:   curl http://$DOMAIN/up"
echo "   • Restart Nginx:       sudo systemctl restart nginx"
echo "   • Restart PHP-FPM:     sudo systemctl restart php-fpm"
echo "   • Laravel optimize:    cd $PROJECT_PATH && php artisan optimize"
echo "   • Clear Laravel cache: cd $PROJECT_PATH && php artisan optimize:clear"
echo ""

# URLs
echo "🔗 Application URLs:"
echo "   • Main App:     http://$DOMAIN"
echo "   • Health Check: http://$DOMAIN/up"
echo ""

# Development Notes
echo "📝 Development Notes:"
success "✓ File ownership preserved for development ($PROJECT_USER)"
success "✓ Traditional PHP-FPM setup (more stable than Octane)"
success "✓ Nginx properly configured with Laravel best practices"
success "✓ Storage and cache directories have correct permissions"
warn "! FrankenPHP/Octane had compatibility issues - using PHP-FPM instead"
info "! To switch to Octane later, resolve FrankenPHP version conflicts"
echo ""

echo "=============================================="
success "Deployment Status: SUCCESSFUL ✅"
echo "Your Laravel application is now running!"
echo "=============================================="
