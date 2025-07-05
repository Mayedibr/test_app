# Use official PHP-Apache image
FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy app files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Set up environment variables for DB (optional, for docker-compose)
ENV DB_HOST=db
ENV DB_NAME=employee_entitlements
ENV DB_USER=root
ENV DB_PASS=password

# Start Apache
CMD ["apache2-foreground"] 