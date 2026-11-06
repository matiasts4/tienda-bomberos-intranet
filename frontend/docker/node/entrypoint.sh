#!/bin/bash
set -e

cd /app

# node_modules vive en un volumen propio del contenedor: los binarios nativos de
# SWC/lightningcss son distintos a los que instala macOS. Si está vacío, instalamos.
if [ ! -d node_modules/next ]; then
    echo "==> Instalando dependencias de npm (primer arranque, puede tardar)"
    if [ -f package-lock.json ]; then
        npm ci
    else
        npm install
    fi
fi

exec "$@"
