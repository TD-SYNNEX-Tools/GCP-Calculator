# ---------------------------------------------------------------------------
# Imagem de produção para Azure App Service (Linux — Custom Container).
#
# Diferente da imagem PHP padrão + startup.sh (que instala o driver ODBC a
# cada cold start, ~1-2 min e sujeito a falhas → HTTP 500), aqui o
# Microsoft ODBC Driver 18 e as extensões pdo_sqlsrv/sqlsrv já vêm
# COMPILADOS na imagem. O container sobe pronto, sem custo de cold start.
#
# Build:  docker build -t secops-calculator .
# Run:    docker run -p 8080:8080 --env-file .env secops-calculator
#
# App Service: publique a imagem num registry (ACR) e defina WEBSITES_PORT=8080.
# ---------------------------------------------------------------------------

# ---- Estágio 1: dependências Composer (autoloader otimizado) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install \
        --no-dev --optimize-autoloader --classmap-authoritative \
        --no-interaction --no-progress --ignore-platform-reqs

# ---- Estágio 2: runtime (php-fpm + nginx) ----
FROM php:8.2-fpm-bookworm

ENV ACCEPT_EULA=Y \
    DEBIAN_FRONTEND=noninteractive

# Pacotes de sistema, Microsoft ODBC Driver 18 e extensões PHP.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        nginx supervisor \
        ca-certificates curl gnupg2 apt-transport-https \
        unixodbc-dev libzip-dev libpng-dev libjpeg62-turbo-dev \
        libfreetype6-dev libonig-dev; \
    # Repositório de pacotes da Microsoft (Debian 12 / bookworm).
    curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg; \
    curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list \
        | sed 's|https://|[signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://|' \
        > /etc/apt/sources.list.d/mssql-release.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends msodbcsql18; \
    # Extensões PHP: gd/mbstring/zip (dompdf, Composer) + pdo_sqlsrv/sqlsrv.
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" mbstring gd zip; \
    pecl install sqlsrv pdo_sqlsrv; \
    docker-php-ext-enable sqlsrv pdo_sqlsrv; \
    # Limpeza para reduzir o tamanho da imagem.
    apt-get purge -y --auto-remove gnupg2; \
    rm -rf /var/lib/apt/lists/*

# Configurações: nginx (document root -> /public), php-fpm e supervisor.
COPY docker/nginx.conf       /etc/nginx/sites-available/default
COPY docker/php.ini          /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Código da aplicação + vendor do estágio de build.
WORKDIR /var/www/html
COPY . /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor

RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
