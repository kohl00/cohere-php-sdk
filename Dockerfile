# Use an official PHP runtime as a parent image
FROM php:8.1-apache

# Set the working directory in the container to /var/www/html
WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get upgrade -y

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy the current directory contents into the container at /var/www/html
COPY . /var/www/html

# Install dependencies
RUN composer install --ignore-platform-reqs

# Change the permissions of the application directory
RUN chown -R www-data:www-data /var/www/html && a2enmod rewrite

# Allow Override
RUN echo '<Directory "/var/www/html/">\n\
            AllowOverride All\n\
        </Directory>' >> /etc/apache2/apache2.conf

# Expose port 80 for the Apache web server
EXPOSE 80
