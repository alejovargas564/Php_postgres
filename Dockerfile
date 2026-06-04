FROM php:8.2-apache

# Instalación de dependencias de sistema y extensiones PHP
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libssl-dev \
    pkg-config \
    curl \
    unzip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && a2enmod rewrite

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

# REGLA DE ORO: Usamos --ignore-platform-req=ext-mongodb para que no falle el build
# ya que la extensión la habilitamos arriba con docker-php-ext-enable
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-mongodb

RUN chown -R www-data:www-data /var/www/html

# Asegurar que Apache pase las variables de entorno
RUN echo 'variables_order = "EGPCS"' >> /usr/local/etc/php/conf.d/custom.ini

EXPOSE 80
