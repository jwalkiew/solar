# build stage: generates PHP data files (VSOP87, deltaT, time zones, places), the world map and the algorithms page
# from pinned npm packages
FROM node:22.21.0-alpine AS data
ENV DO_NOT_TRACK=1 \
    NPM_CONFIG_UPDATE_NOTIFIER=false \
    NPM_CONFIG_FUND=false \
    NPM_CONFIG_AUDIT=false
WORKDIR /build
COPY build/package.json build/package-lock.json build/.npmrc ./
RUN npm ci --ignore-scripts
COPY build/*.mjs ./
COPY docs/algorytmy.md ./
RUN node gen-astro-data.mjs /out/data \
 && node gen-tz-data.mjs /out/data \
 && node gen-places.mjs /out/data \
 && node gen-land.mjs /out/public/data/land.json \
 && node gen-algorithms.mjs algorytmy.md /out/public

FROM php:8.4.26-apache AS runtime
ENV APACHE_DOCUMENT_ROOT=/var/www/app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
 && sed -ri -e 's!^Listen 80$!Listen 8080!' /etc/apache2/ports.conf \
 && sed -ri -e 's!<VirtualHost \*:80>!<VirtualHost *:8080>!' /etc/apache2/sites-available/000-default.conf \
 && a2enmod headers \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/apache-solar.conf /etc/apache2/conf-enabled/solar.conf
COPY docker/php-solar.ini $PHP_INI_DIR/conf.d/solar.ini
COPY app/lib /var/www/app/lib
COPY app/public /var/www/app/public
COPY --from=data /out/data /var/www/app/data
COPY --from=data /out/public /var/www/app/public
USER www-data
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -fsS -o /dev/null http://127.0.0.1:8080/ || exit 1
