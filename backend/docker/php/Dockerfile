FROM php:8.4-cli

# Dependencias de sistema para las extensiones que usa el proyecto:
#   gd    -> dompdf y simple-qrcode (render de PDF y códigos QR)
#   zip   -> maatwebsite/excel (lectura/escritura de .xlsx)
#   intl  -> formateo de números y fechas
#   mbstring, exif, bcmath, pcntl, pdo_mysql -> base de Laravel
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libxml2-dev \
        mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Usuario no-root con tu UID/GID para que los archivos que genere Laravel
# (logs, cache, storage, PDFs) no queden como root en el host.
ARG UID=501
ARG GID=20
RUN useradd -u ${UID} -g ${GID} -m -s /bin/bash appuser || useradd -m -s /bin/bash appuser

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint && chmod +x /usr/local/bin/entrypoint

# vendor/ es un volumen nombrado. Docker inicializa un volumen vacío copiando
# el contenido Y los permisos de esta ruta en la imagen; si no existe aquí,
# el volumen queda como root y appuser no puede instalar dependencias.
RUN mkdir -p /var/www/html/vendor \
    && chown -R appuser /var/www/html \
    && mkdir -p /home/appuser/.composer \
    && chown -R appuser /home/appuser

USER appuser

EXPOSE 8000

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
