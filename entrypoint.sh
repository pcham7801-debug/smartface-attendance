#!/bin/bash
set -e

# Start MariaDB service
echo "Starting MariaDB service..."
service mariadb start

# Wait for MariaDB to be ready
for i in {1..30}; do
    if mysqladmin ping --silent; then
        echo "MariaDB is ready."
        break
    fi
    echo "Waiting for MariaDB ($i/30)..."
    sleep 1
done

# Initialize database
mysql -u root -e "CREATE DATABASE IF NOT EXISTS smartface_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Check table count and auto-import if empty
TABLE_COUNT=$(mysql -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'smartface_attendance';")
if [ "$TABLE_COUNT" -eq 0 ]; then
    echo "Importing database tables and student records..."
    if [ -f /var/www/html/database/smartface_attendance_cloud.sql ]; then
        mysql -u root smartface_attendance < /var/www/html/database/smartface_attendance_cloud.sql
        echo "Database imported successfully!"
    fi
else
    echo "Database ready ($TABLE_COUNT tables found)."
fi

# Ensure uploads directory
mkdir -p /var/www/html/uploads/profiles
chmod -R 777 /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads

# Support dynamic PORT environment variable (Render / Railway)
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    echo "Configuring Apache to listen on port $PORT..."
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/000-default.conf
fi

echo "Starting Apache web server..."
exec apache2-foreground
