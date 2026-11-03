#!/bin/bash
set -e

cd /var/www/html

# 1. .env: si el dev no lo creó, lo generamos desde el ejemplo.
#    Solo el contenedor "instalador" lo toca, para no generar dos APP_KEY
#    distintas en paralelo (rompería las sesiones ya emitidas).
if [ "${INSTALL_DEPENDENCIES}" = "true" ] && [ ! -f .env ]; then
    echo "==> No existe .env, copiando desde .env.example"
    cp .env.example .env
fi

# 2. Dependencias. vendor/ es un volumen COMPARTIDO entre backend y queue, así
#    que solo un contenedor puede instalar: si ambos corren composer install a
#    la vez se pisan y dejan el autoloader corrupto. El backend instala
#    (INSTALL_DEPENDENCIES=true) y los demás esperan a que termine.
if [ "${INSTALL_DEPENDENCIES}" = "true" ]; then
    if [ ! -f vendor/autoload.php ]; then
        echo "==> Instalando dependencias de Composer (primer arranque, puede tardar)"
        composer install --no-interaction --prefer-dist
    fi
else
    echo "==> Esperando a que el backend termine de instalar vendor/"
    for i in $(seq 1 300); do
        [ -f vendor/autoload.php ] && break
        if [ "$i" = "300" ]; then
            echo "!! vendor/ no apareció tras 5 minutos" >&2
            exit 1
        fi
        sleep 1
    done
    # El archivo aparece antes de que composer termine de escribirlo todo.
    until php -r 'require "vendor/autoload.php";' >/dev/null 2>&1; do sleep 2; done
    echo "==> vendor/ listo"
fi

# 3. APP_KEY: si viene vacía, la generamos (solo el instalador, ver punto 1).
if [ "${INSTALL_DEPENDENCIES}" = "true" ] && ! grep -qE '^APP_KEY=base64:.+' .env; then
    echo "==> Generando APP_KEY"
    php artisan key:generate --force
fi

# 4. Esperar a que MariaDB acepte conexiones antes de arrancar Laravel.
if [ -n "${DB_HOST}" ]; then
    echo "==> Esperando a la base de datos en ${DB_HOST}:${DB_PORT:-3306}"
    for i in $(seq 1 60); do
        if mariadb-admin ping -h"${DB_HOST}" -P"${DB_PORT:-3306}" \
             -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent >/dev/null 2>&1; then
            echo "==> Base de datos lista"
            break
        fi
        if [ "$i" = "60" ]; then
            echo "!! La base de datos no respondió tras 60 intentos" >&2
            exit 1
        fi
        sleep 1
    done
fi

# 5. Directorios que Laravel necesita escribir (el bind mount puede traerlos vacíos).
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

# 6. Migraciones. En un clon nuevo la BD está vacía y sin tablas el worker de
#    colas (que usa la tabla `cache`) entra en bucle de reinicio. Si restauraste
#    un dump, esto es un no-op porque las migraciones ya están registradas.
if [ "${INSTALL_DEPENDENCIES}" = "true" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "==> Ejecutando migraciones pendientes"
    php artisan migrate --force
fi

exec "$@"
