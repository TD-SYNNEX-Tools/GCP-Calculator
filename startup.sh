#!/bin/bash
# ---------------------------------------------------------------------------
# Startup command para Azure App Service (Linux, imagem PHP 8.2 / NGINX).
#
# Configure em: App Service > Configuration > General settings > Startup Command
#   /home/site/wwwroot/startup.sh
#
# Faz duas coisas:
#   1. Aponta o document root do NGINX para /public.
#   2. Instala o Microsoft ODBC Driver 18 + extensoes pdo_sqlsrv/sqlsrv
#      (nao vem na imagem PHP do Linux) e recarrega o PHP-FPM.
#
# Observacao: a instalacao do driver roda a cada COLD START do container
# (~1-2 min). Mantenha "Always On" ligado para reduzir reinicios. Para
# producao, um container customizado (Dockerfile) com o driver ja embutido
# e o caminho mais robusto.
# ---------------------------------------------------------------------------
set -e

echo "[startup] Configurando document root do NGINX -> /public"
cp /home/site/wwwroot/default /etc/nginx/sites-available/default
service nginx reload || true

if php -m | grep -qi pdo_sqlsrv; then
    echo "[startup] pdo_sqlsrv ja disponivel, pulando instalacao."
    exit 0
fi

echo "[startup] Instalando Microsoft ODBC Driver 18 + pdo_sqlsrv/sqlsrv"
export DEBIAN_FRONTEND=noninteractive
DEBIAN_VERSION=$(grep VERSION_ID /etc/os-release | cut -d '"' -f2 | cut -d. -f1)

apt-get update
apt-get install -y --no-install-recommends \
    curl gnupg2 apt-transport-https ca-certificates \
    build-essential autoconf unixodbc-dev

curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
    | tee /etc/apt/trusted.gpg.d/microsoft.asc > /dev/null
curl -fsSL "https://packages.microsoft.com/config/debian/${DEBIAN_VERSION}/prod.list" \
    | tee /etc/apt/sources.list.d/mssql-release.list > /dev/null

apt-get update
ACCEPT_EULA=Y apt-get install -y msodbcsql18

echo "[startup] Compilando extensoes PHP (pecl)"
pecl channel-update pecl.php.net || true
pecl install sqlsrv pdo_sqlsrv

PHP_CONF_DIR=$(php -i | grep -oP '(?<=Scan this dir for additional .ini files => ).*' | head -n1)
PHP_CONF_DIR=${PHP_CONF_DIR:-/usr/local/etc/php/conf.d}
echo "extension=sqlsrv.so"     > "${PHP_CONF_DIR}/30-sqlsrv.ini"
echo "extension=pdo_sqlsrv.so" > "${PHP_CONF_DIR}/35-pdo_sqlsrv.ini"

echo "[startup] Recarregando PHP-FPM para carregar as extensoes"
pkill -o -USR2 php-fpm 2>/dev/null || true

echo "[startup] Concluido."
