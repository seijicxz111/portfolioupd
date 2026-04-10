#!/bin/bash
# CIG Admin Dashboard - Setup Script for Linux/Mac

echo "===================================="
echo "CIG Admin Dashboard - Setup Script"
echo "===================================="
echo ""

# Check if PHP is available
echo "Checking for PHP installation..."
if ! command -v php &> /dev/null; then
    echo "[ERROR] PHP is not installed"
    echo ""
    echo "Install PHP using:"
    echo "  Ubuntu/Debian: sudo apt-get install php php-mysql php-cli"
    echo "  macOS: brew install php"
    echo ""
    exit 1
else
    echo "[OK] PHP is installed"
    php -v
fi

echo ""
echo "Checking for MySQL..."
if ! command -v mysql &> /dev/null; then
    echo "[WARNING] MySQL command-line tool not found"
    echo "You can still use phpMyAdmin if available"
else
    echo "[OK] MySQL is available"
    mysql -V
fi

echo ""
echo "===================================="
echo "Setup Steps:"
echo "===================================="
echo ""
echo "1. DATABASE SETUP:"
echo "   - Create MySQL database: cig_admin"
echo "   - Import schema from: db/schema.sql"
echo ""
echo "2. CONFIGURE DATABASE:"
echo "   - Edit: db/config.php"
echo "   - Update DB_USER and DB_PASS"
echo ""
echo "3. START PHP SERVER:"
echo "   - Run: php -S localhost:8000"
echo "   - Visit: http://localhost:8000/pages/login.php"
echo ""
echo "4. DEFAULT LOGIN:"
echo "   - Email: admin@cig.edu.ph"
echo "   - Password: admin123"
echo ""
echo "===================================="
echo ""
