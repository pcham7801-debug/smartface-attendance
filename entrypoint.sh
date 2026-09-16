#!/bin/bash
set -e

echo "Starting MariaDB service..."
mkdir -p /var/run/mysqld /run/mysqld
chown -R mysql:mysql /var/run/mysqld /run/mysqld
service mariadb start || /etc/init.d/mariadb start || true

# Wait for MariaDB to be ready
for i in {1..30}; do
    if mysqladmin ping --silent 2>/dev/null; then
        echo "MariaDB is ready."
        break
    fi
    echo "Waiting for MariaDB ($i/30)..."
    sleep 1
done

# Setup database and permissions
mysql -u root << 'EOF'
CREATE DATABASE IF NOT EXISTS smartface_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('');
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING PASSWORD('');
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF

# Check table count and auto-import if empty
TABLE_COUNT=$(mysql -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'smartface_attendance';" 2>/dev/null || echo 0)
if [ "$TABLE_COUNT" -eq 0 ]; then
    echo "Importing database tables and seed data..."
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
