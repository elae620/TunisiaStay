# syntax=docker/dockerfile:1

# =============================================================================
# Étape 1 : Construction des assets frontend (Node.js + Webpack Encore + Tailwind)
# =============================================================================
FROM node:20-alpine AS assets_builder

WORKDIR /app

# Optimisation du cache des dépendances npm
COPY package.json package-lock.json ./
RUN npm ci --no-audit --prefer-offline

# Fichiers sources nécessaires aux assets et au scan Tailwind CSS
COPY assets/ assets/
COPY templates/ templates/
COPY src/Twig/ src/Twig/
COPY webpack.config.js postcss.config.js tailwind.config.js ./

# Compilation des assets de production
RUN npm run build

# =============================================================================
# Étape 2 : Préparation des dépendances PHP de production (Composer)
# =============================================================================
FROM composer:2.8 AS vendor_builder

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

# =============================================================================
# Étape 3 : Image finale d'exécution (Apache + PHP 8.2 non-root)
# =============================================================================
FROM php:8.2-apache-bookworm AS app

# 1. Extensions PHP nécessaires pour Symfony et MariaDB/MySQL
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        unzip \
        curl \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        intl \
        zip \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Configuration d'Apache sur port 8080 (non-root)
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && a2enmod rewrite headers

# Configuration du VirtualHost
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# 3. Droits pour l'utilisateur non-root www-data sur les répertoires Apache
RUN mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
    && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2 /var/www/html

WORKDIR /var/www/html

# 4. Copie des fichiers applicatifs et de configuration par défaut
COPY --chown=www-data:www-data assets/ assets/
COPY --chown=www-data:www-data bin/ bin/
COPY --chown=www-data:www-data config/ config/
COPY --chown=www-data:www-data migrations/ migrations/
COPY --chown=www-data:www-data public/ public/
COPY --chown=www-data:www-data src/ src/
COPY --chown=www-data:www-data templates/ templates/
COPY --chown=www-data:www-data translations/ translations/
COPY --chown=www-data:www-data composer.json composer.lock symfony.lock .env ./

# Copie des dépendances PHP depuis l'étape Composer
COPY --from=vendor_builder --chown=www-data:www-data /app/vendor /var/www/html/vendor

# Copie des assets compilés depuis l'étape Node.js
COPY --from=assets_builder --chown=www-data:www-data /app/public/build /var/www/html/public/build

# 5. Répertoires d'écriture (var, cache, logs, uploads)
RUN mkdir -p /var/www/html/var/cache /var/www/html/var/log /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html/var /var/www/html/public/uploads

# 6. Script d'entrée
COPY --chown=www-data:www-data docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 7. Utilisateur non-root
USER www-data

# Port exposé
EXPOSE 8080

# Contrôle de santé
HEALTHCHECK --interval=10s --timeout=3s --retries=3 --start-period=15s \
    CMD curl -f http://localhost:8080/health || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
