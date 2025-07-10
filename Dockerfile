# Use a currently supported PHP version. PHP 8.2 is a good stable choice.
# Replace with php:8.3-apache if your application is fully compatible and you prefer the latest.
FROM php:8.2-apache

# Set the working directory inside the container.
# This is where your application's files will reside.
WORKDIR /var/www/html

# Copy your entire application code into the container.
# Ensure your main application entry point (e.g., index.php) is at the root of your project
# or prepare to configure Apache's DocumentRoot if it's in a subfolder like 'public'.
COPY . .

# Install system dependencies required by PHP extensions.
# Use a single RUN command for efficiency and smaller image layers.
RUN apt update && \
    apt install -y \
        zip \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
    && rm -rf /var/lib/apt/lists/* # Clean up apt cache to reduce image size

# Install PHP extensions using docker-php-ext-install.
# -j$(nproc) speeds up compilation by using all available CPU cores.
# pdo_mysql is for database connections.
# zip is for handling zip archives.
# gd is for image manipulation (common, added as an example).
RUN docker-php-ext-install -j$(nproc) pdo_mysql zip gd

# Enable Apache modules.
# mod_rewrite is crucial for "pretty" URLs in frameworks.
# mod_headers is useful for setting HTTP headers (e.g., CORS, security).
RUN a2enmod rewrite headers

# Install Composer.
# Use a multi-stage build from the official Composer image for best practice.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy Composer dependency files.
# It's crucial to copy both composer.json and composer.lock for consistent builds.
COPY composer.json composer.lock ./

# Run Composer to install project dependencies.
# --no-dev excludes development dependencies, making the production image smaller.
# --optimize-autoloader optimizes Composer's autoloader for faster execution.
RUN composer install --no-dev --optimize-autoloader

# --- OPTIONAL: Adjust Apache DocumentRoot if your app's entry point is in a 'public' subfolder ---
# If your FlightPHP app's index.php is inside a 'public' directory (e.g., /var/www/html/public/index.php),
# you will need to tell Apache to serve from that 'public' directory.
#
# To do this:
# 1. Create a directory named `docker/apache/` in your project root.
# 2. Inside `docker/apache/`, create a file named `000-default.conf` with the following content:
#
#    <VirtualHost *:80>
#        DocumentRoot /var/www/html/public
#        <Directory /var/www/html/public>
#            Options Indexes FollowSymLinks
#            AllowOverride All
#            Require all granted
#        </Directory>
#        ErrorLog ${APACHE_LOG_DIR}/error.log
#        CustomLog ${APACHE_LOG_DIR}/access.log combined
#    </VirtualHost>
#
# 3. Then, uncomment and add these lines to your Dockerfile right after the `composer install` command:
#
# COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
# RUN a2dissite 000-default.conf # Disable default Apache config
# RUN a2ensite 000-default.conf  # Enable your custom config
#
# --------------------------------------------------------------------------------------------------


# Expose port 80. Render's load balancer will handle HTTPS for you.
# Your container typically only needs to listen on plain HTTP internally.
EXPOSE 80

# The base php:apache image automatically starts Apache when the container runs.
# No explicit CMD instruction is usually needed unless you have very specific startup requirements.