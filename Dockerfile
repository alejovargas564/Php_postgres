FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpq-dev libssl-dev pkg-config curl unzip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . /var/www/html/

# QUITA el --ignore-platform-req=ext-mongodb, ya la instalamos arriba con pecl
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html

# Importante: Render ya pasa las variables, PassEnv a veces da problemas si el nombre no es exacto
RUN echo "variables_order = \"EGPCS\"" > /usr/local/etc/php/conf.d/custom.ini

EXPOSE 80
