#!/bin/sh
set -e

# S'assurer de la présence des dossiers d'écriture
mkdir -p /var/www/html/var/cache /var/www/html/var/log /var/www/html/public/uploads

exec "$@"
