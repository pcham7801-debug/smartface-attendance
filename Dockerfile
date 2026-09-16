FROM php:8.2-apache

# Install MariaDB server, client, and required utilities
RUN apt-get update && apt-get install -y \
    mariadb-server \
    mariadb-client \
    sed \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set document root
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set timezone
RUN ln -sf /usr/share/zoneinfo/Asia/Manila /etc/localtime

# Copy application files
COPY . /var/www/html/

# Setup entrypoint script with Unix line endings and executable permission
COPY entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

# Create uploads and database directory
RUN mkdir -p /var/www/html/uploads/profiles && chmod -R 777 /var/www/html/uploads

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
