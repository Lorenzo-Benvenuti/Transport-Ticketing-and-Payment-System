FROM php:8.2-apache

# System deps for common PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends     libcurl4-openssl-dev   && rm -rf /var/lib/apt/lists/*

# PHP extensions needed by the project
RUN docker-php-ext-install mysqli pdo_mysql curl

# Apache modules used by the apps
RUN a2enmod rewrite headers

# Basic hardening / quality-of-life
RUN {     echo "expose_php=0";     echo "date.timezone=UTC";   } > /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /var/www/html
