FROM php:8.3.20-apache

RUN a2enmod rewrite

RUN apt update -y && apt upgrade -y && apt install -y sudo libzip-dev zip libicu-dev libpng-dev libxslt-dev ca-certificates tzdata \
    && apt clean

# Configurer timezone
ENV TZ=Europe/Paris
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Install Composer CLI
RUN curl -sS https://getcomposer.org/installer | php -- \
    && mv composer.phar /usr/local/bin/composer

# Install npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
RUN sudo apt-get install -y nodejs

RUN docker-php-ext-install pdo mysqli pdo_mysql zip gd xsl

RUN docker-php-ext-configure intl && docker-php-ext-install intl

COPY apache.conf /etc/apache2/sites-enabled/000-default.conf

COPY php.ini /usr/local/etc/php/php.ini

WORKDIR /var/www/html/
CMD ["apache2-foreground"]
