FROM php:8.3.20-apache

# add docker php extension installer
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions
RUN install-php-extensions intl xsl zip pdo mysqli pdo_mysql gd opcache

# Install composer
RUN curl -sSk https://getcomposer.org/installer | php -- --disable-tls
RUN mv composer.phar /usr/local/bin/composer
RUN apt update && apt install -yqq sudo git
RUN apt clean

# Configurer timezone
ENV TZ=Europe/Paris
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
RUN apt update && apt install -yqq nodejs

COPY ./ /var/www/html/

COPY ./docker/php/prod/apache.conf /etc/apache2/sites-available/000-default.conf

COPY ./docker/php/prod/php.ini /usr/local/etc/php/php.ini

RUN rm -rf /var/www/html/vendor
RUN rm -rf /var/www/html/node_modules
RUN rm -rf /var/www/html/.git
RUN rm -rf /var/www/html/.env
RUN rm -rf /var/www/html/.github
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader --no-interaction && \
    npm install -g npm@latest &&  \
    npm install
RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html/

ENTRYPOINT ["bash", "./docker/docker.sh"]
