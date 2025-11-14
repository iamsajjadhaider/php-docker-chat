# Use a standard PHP image with Apache pre-installed
FROM php:8.2-apache

# Install the mysqli and pdo_mysql extensions necessary to talk to MySQL
RUN docker-php-ext-install mysqli pdo_mysql

# Enable the rewrite module for Apache (optional, but good practice)
RUN a2enmod rewrite

# Copy the application files (index.php) into the web root
COPY index.php /var/www/html/

# The volume mapping in docker-compose.yml will override this copy, 
# but it's kept for completeness.
