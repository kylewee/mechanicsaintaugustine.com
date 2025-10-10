#!/bin/bash

echo "Setting up Mobile Mechanic Database..."

# Try to create database and import schema
echo "Creating database 'mm'..."
mysql -u root -e "CREATE DATABASE IF NOT EXISTS mm;" 2>/dev/null || {
    echo "Failed to create database with root user."
    echo "Please run the following commands manually:"
    echo "1. mysql -u root -p"
    echo "2. CREATE DATABASE IF NOT EXISTS mm;"
    echo "3. USE mm;"
    echo "4. SOURCE $(pwd)/DB/mm.sql;"
    echo ""
    echo "Or try: sudo mysql < setup_database.sql"
    exit 1
}

echo "Importing database schema..."
mysql -u root mm < DB/mm.sql 2>/dev/null || {
    echo "Failed to import schema. Try:"
    echo "mysql -u root -p mm < DB/mm.sql"
    exit 1
}

echo "Database setup completed successfully!"
echo "Database: mm"
echo "Admin login: admin@gmail.com / admin"