########################################################
# Aegis Filter – Dockerfile
# Laravel 10 + PHP 8.2 + Apache
########################################################

# ─── Etapa 1: Dependencias de Composer ────────────────
FROM composer:2.7 AS composer-builder
WORKDIR /app

# Copiar archivos de dependencias primero (cache layer).
COPY src/composer.json ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

# ─── Etapa 2: Imagen final de producción ──────────────
FROM php:8.2-apache AS production

LABEL maintainer="AegisFilter Team <aegis@techhub.pe>"
LABEL description="Aegis Filter - Sistema Antispam para Tech Hub Forum"
LABEL version="1.0.0"

# ─── Extensiones del sistema ──────────────────────────
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    zip \
    unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ─── Configuración de Apache ──────────────────────────
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

RUN a2enmod rewrite headers

# Configuración de VirtualHost con AllowOverride All para .htaccess
RUN echo '<VirtualHost *:80>\n\
    ServerAdmin webmaster@localhost\n\
    DocumentRoot ${APACHE_DOCUMENT_ROOT}\n\
    <Directory ${APACHE_DOCUMENT_ROOT}>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# ─── Usuario y permisos ───────────────────────────────
WORKDIR /var/www/html

# Copiar vendor desde la etapa builder
COPY --from=composer-builder /app/vendor ./vendor

# Copiar código fuente de la aplicación
COPY src/ .

# Permisos de Laravel
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Variables de entorno por defecto
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

# Entrypoint: ejecutar migraciones y levantar Apache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["apache2-foreground"]
