#!/bin/bash
# ---------------------------------------------------------------------------
# Startup command para Azure App Service (Linux, imagem PHP 8.2 / NGINX).
#
# Configure em: App Service > Configuration > General settings > Startup Command
#   /home/site/wwwroot/startup.sh
#
# Faz duas coisas:
#   1. Aponta o document root do NGINX para /public (SEMPRE, e primeiro).
#   2. Garante o Microsoft ODBC Driver 18 + extensoes pdo_sqlsrv/sqlsrv.
#
# Robustez:
#   - O passo (1) roda antes de tudo e é isolado: mesmo que a instalação do
#     driver falhe, o site sobe e /login funciona (o app conecta ao banco de
#     forma preguiçosa — lazy — apenas nas rotas que usam o BD).
#   - A instalação do driver é best-effort: NUNCA aborta o container (sem
#     `set -e` global). Falhas são apenas registradas no log.
#   - As extensões compiladas via pecl são cacheadas em /home (persistente),
#     evitando recompilar a cada cold start (mais rápido e mais confiável).
#
# Mantenha "Always On" LIGADO para reduzir cold starts.
# ---------------------------------------------------------------------------

log() { echo "[startup] $*"; }

# --- (1) Document root do NGINX -> /public (crítico; sempre aplicar) --------
log "Configurando document root do NGINX -> /public"
if cp /home/site/wwwroot/default /etc/nginx/sites-available/default 2>/dev/null; then
    service nginx reload 2>/dev/null || nginx -s reload 2>/dev/null || true
    log "NGINX recarregado."
else
    log "AVISO: não foi possível copiar o arquivo 'default' do NGINX."
fi

# --- (2) Driver do SQL Server (best-effort; nunca aborta) -------------------
if php -m 2>/dev/null | grep -qi pdo_sqlsrv; then
    log "pdo_sqlsrv já disponível. Nada a fazer."
    exit 0
fi

install_driver() {
    export DEBIAN_FRONTEND=noninteractive

    local EXT_DIR CACHE_DIR PHP_CONF_DIR DEBIAN_VERSION
    EXT_DIR=$(php -r 'echo ini_get("extension_dir");' 2>/dev/null)
    EXT_DIR=${EXT_DIR:-/usr/local/lib/php/extensions}
    CACHE_DIR=/home/site/php-ext-cache
    PHP_CONF_DIR=$(php -i 2>/dev/null | grep -oP '(?<=Scan this dir for additional .ini files => ).*' | head -n1)
    PHP_CONF_DIR=${PHP_CONF_DIR:-/usr/local/etc/php/conf.d}

    # O msodbcsql18 (lib de sistema) é necessário em TODO cold start para o
    # driver conseguir conectar — o filesystem /usr e /opt é reinicializado.
    log "Instalando Microsoft ODBC Driver 18 (msodbcsql18)"
    DEBIAN_VERSION=$(grep VERSION_ID /etc/os-release | cut -d '"' -f2 | cut -d. -f1)
    if ! apt-get update; then
        log "ERRO: apt-get update falhou. Abortando instalação do driver."
        return 1
    fi
    apt-get install -y --no-install-recommends \
        curl gnupg2 apt-transport-https ca-certificates unixodbc-dev || {
        log "ERRO: dependências apt falharam."; return 1; }

    curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
        | tee /etc/apt/trusted.gpg.d/microsoft.asc > /dev/null || return 1
    curl -fsSL "https://packages.microsoft.com/config/debian/${DEBIAN_VERSION}/prod.list" \
        | tee /etc/apt/sources.list.d/mssql-release.list > /dev/null || return 1

    apt-get update || return 1
    if ! ACCEPT_EULA=Y apt-get install -y msodbcsql18; then
        log "ERRO: instalação do msodbcsql18 falhou."; return 1
    fi

    # Extensões PHP: usa cache em /home se existir; senão compila e cacheia.
    if [ -f "${CACHE_DIR}/pdo_sqlsrv.so" ] && [ -f "${CACHE_DIR}/sqlsrv.so" ]; then
        log "Usando extensões PHP do cache (${CACHE_DIR}) — sem recompilar."
        cp "${CACHE_DIR}/sqlsrv.so"     "${EXT_DIR}/sqlsrv.so"     || return 1
        cp "${CACHE_DIR}/pdo_sqlsrv.so" "${EXT_DIR}/pdo_sqlsrv.so" || return 1
    else
        log "Compilando extensões PHP (pecl) — primeira vez"
        apt-get install -y --no-install-recommends build-essential autoconf || {
            log "ERRO: build tools falharam."; return 1; }
        pecl channel-update pecl.php.net || true
        if ! pecl install sqlsrv pdo_sqlsrv; then
            log "ERRO: pecl install falhou."; return 1
        fi
        # Cacheia os .so compilados para os próximos cold starts.
        mkdir -p "${CACHE_DIR}"
        cp "${EXT_DIR}/sqlsrv.so"     "${CACHE_DIR}/sqlsrv.so"     2>/dev/null || true
        cp "${EXT_DIR}/pdo_sqlsrv.so" "${CACHE_DIR}/pdo_sqlsrv.so" 2>/dev/null || true
        log "Extensões cacheadas em ${CACHE_DIR}."
    fi

    echo "extension=sqlsrv.so"     > "${PHP_CONF_DIR}/30-sqlsrv.ini"
    echo "extension=pdo_sqlsrv.so" > "${PHP_CONF_DIR}/35-pdo_sqlsrv.ini"

    log "Recarregando PHP-FPM para carregar as extensões"
    pkill -o -USR2 php-fpm 2>/dev/null || true
    return 0
}

if install_driver; then
    if php -m 2>/dev/null | grep -qi pdo_sqlsrv; then
        log "Concluído: pdo_sqlsrv carregado com sucesso."
    else
        log "AVISO: instalação concluída, mas pdo_sqlsrv ainda não aparece em 'php -m'."
    fi
else
    log "AVISO: driver do SQL Server NÃO foi instalado. O site continua no ar;"
    log "       rotas que usam o banco retornarão erro tratado até o driver subir."
fi

# Sempre sai com sucesso: a inicialização do site não depende do driver.
exit 0
