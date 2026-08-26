#!/usr/bin/env bash
#
# Provisionamento one-shot do afk-builder numa VM Oracle Cloud Always Free
# (Ampere A1 / aarch64, Ubuntu 24.04 LTS). Rodar UMA vez, como root:
#
#   sudo bash provision.sh afk.seudominio.com
#
# Idempotente: pode rodar de novo sem quebrar nada.
#
set -euo pipefail

DOMAIN="${1:-}"
APP_DIR="${APP_DIR:-/var/www/afk-builder}"
APP_USER="${APP_USER:-afk}"
REPO="${REPO:-https://github.com/Chirlanio/afk-builder.git}"
PHP_V="8.4"
NODE_V="22"
TZ_APP="America/Sao_Paulo"
CRED_FILE="/root/afk-builder-credentials.txt"
HERE="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"

if [[ $EUID -ne 0 ]]; then
    echo "Rode como root: sudo bash $0 <dominio>" >&2
    exit 1
fi

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

# ---------------------------------------------------------------------------
# 1. Base do sistema
# ---------------------------------------------------------------------------
log "Atualizando o sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq software-properties-common curl git unzip ca-certificates acl

timedatectl set-timezone "$TZ_APP"

# ---------------------------------------------------------------------------
# 2. Firewall — a pegadinha da Oracle
#
# As imagens Ubuntu da OCI já vêm com uma chain INPUT que termina em REJECT,
# liberando só a 22. Abrir a porta na Security List do console NÃO basta: o
# pacote chega na VM e morre no iptables local. Inserimos o ACCEPT ANTES da
# primeira regra REJECT (a posição varia entre imagens, então descobrimos).
# ---------------------------------------------------------------------------
log "Liberando as portas 80/443 no iptables local"
apt-get install -y -qq iptables-persistent

open_port() {
    local port=$1
    if iptables -C INPUT -p tcp --dport "$port" -j ACCEPT 2>/dev/null; then
        echo "porta $port ja liberada"
        return
    fi
    local reject_line
    reject_line=$(iptables -L INPUT --line-numbers -n | awk '$2 == "REJECT" { print $1; exit }')
    if [[ -n "$reject_line" ]]; then
        iptables -I INPUT "$reject_line" -p tcp --dport "$port" -j ACCEPT
    else
        iptables -A INPUT -p tcp --dport "$port" -j ACCEPT
    fi
    echo "porta $port liberada"
}

open_port 80
open_port 443
netfilter-persistent save

# ---------------------------------------------------------------------------
# 3. PHP 8.4 (a PPA do ondrej publica arm64)
# ---------------------------------------------------------------------------
log "Instalando PHP $PHP_V"
add-apt-repository -y ppa:ondrej/php >/dev/null
apt-get update -qq
apt-get install -y -qq \
    "php${PHP_V}-fpm" "php${PHP_V}-cli" "php${PHP_V}-common" \
    "php${PHP_V}-mysql" "php${PHP_V}-mbstring" "php${PHP_V}-xml" \
    "php${PHP_V}-curl" "php${PHP_V}-zip" "php${PHP_V}-bcmath" \
    "php${PHP_V}-gd" "php${PHP_V}-intl" "php${PHP_V}-opcache" \
    "php${PHP_V}-readline"

log "Instalando Composer"
if [[ ! -x /usr/local/bin/composer ]]; then
    curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm -f /tmp/composer-setup.php
fi

# ---------------------------------------------------------------------------
# 4. Node (o build do Vite roda na propria VM)
# ---------------------------------------------------------------------------
log "Instalando Node $NODE_V"
if ! command -v node >/dev/null 2>&1 || [[ "$(node -v)" != v${NODE_V}.* ]]; then
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_V}.x" | bash -
    apt-get install -y -qq nodejs
fi

# ---------------------------------------------------------------------------
# 5. nginx, MySQL, supervisor
# ---------------------------------------------------------------------------
log "Instalando nginx, MySQL e supervisor"
apt-get install -y -qq nginx mysql-server supervisor

log "Criando banco e usuario do MySQL"
if [[ -f "$CRED_FILE" ]] && grep -q '^DB_PASSWORD=' "$CRED_FILE"; then
    DB_PASS="$(sed -n 's/^DB_PASSWORD=//p' "$CRED_FILE")"
    echo "reaproveitando senha de $CRED_FILE"
else
    DB_PASS="$(openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | head -c 28)"
fi

mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS afk_builder
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'afk_builder'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER 'afk_builder'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON afk_builder.* TO 'afk_builder'@'localhost';
FLUSH PRIVILEGES;
SQL

# ---------------------------------------------------------------------------
# 6. Usuario da aplicacao e clone
# ---------------------------------------------------------------------------
log "Preparando $APP_DIR"
if ! id -u "$APP_USER" >/dev/null 2>&1; then
    adduser --system --group --home "$APP_DIR" --shell /bin/bash "$APP_USER"
fi
usermod -aG www-data "$APP_USER"

mkdir -p "$APP_DIR" /var/log/afk-builder
git config --global --add safe.directory "$APP_DIR"
if [[ ! -d "$APP_DIR/.git" ]]; then
    git clone "$REPO" "$APP_DIR"
fi

chown -R "$APP_USER:www-data" "$APP_DIR" /var/log/afk-builder

# ---------------------------------------------------------------------------
# 7. Pool PHP-FPM dedicado — roda como $APP_USER, nao como www-data, para
#    que storage/ e bootstrap/cache/ sejam escreviveis sem recorrer a 777.
# ---------------------------------------------------------------------------
log "Configurando pool PHP-FPM"
rm -f "/etc/php/${PHP_V}/fpm/pool.d/www.conf"
cat > "/etc/php/${PHP_V}/fpm/pool.d/afk-builder.conf" <<POOL
[afk-builder]
user = ${APP_USER}
group = www-data

listen = /run/php/php${PHP_V}-fpm-afk-builder.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Dimensionado para o Always Free de 2 OCPU / 12 GB
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500

php_admin_value[memory_limit] = 512M
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 20M
php_admin_value[max_execution_time] = 120
php_admin_flag[expose_php] = off

catch_workers_output = yes
POOL

cat > "/etc/php/${PHP_V}/fpm/conf.d/99-afk-builder.ini" <<INI
opcache.enable=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
; O deploy.sh sempre roda opcache reset via reload do FPM, entao podemos
; desligar a revalidacao por timestamp e ganhar performance.
opcache.validate_timestamps=0
INI

# CLI: os comandos de sync raspam a Fandom Wiki e podem demorar muito.
cat > "/etc/php/${PHP_V}/cli/conf.d/99-afk-builder.ini" <<INI
memory_limit = 1G
max_execution_time = 0
INI

systemctl enable --now "php${PHP_V}-fpm"

# ---------------------------------------------------------------------------
# 8. nginx
# ---------------------------------------------------------------------------
log "Configurando nginx"
SERVER_NAME="${DOMAIN:-_}"
sed -e "s|__SERVER_NAME__|${SERVER_NAME}|g" \
    -e "s|__APP_DIR__|${APP_DIR}|g" \
    -e "s|__PHP_V__|${PHP_V}|g" \
    "$HERE/nginx.conf" > /etc/nginx/sites-available/afk-builder

ln -sfn /etc/nginx/sites-available/afk-builder /etc/nginx/sites-enabled/afk-builder
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now nginx
systemctl reload nginx

# ---------------------------------------------------------------------------
# 9. Queue worker (supervisor) + scheduler (cron)
# ---------------------------------------------------------------------------
log "Configurando queue worker e scheduler"
sed -e "s|__APP_DIR__|${APP_DIR}|g" \
    -e "s|__APP_USER__|${APP_USER}|g" \
    "$HERE/supervisor.conf" > /etc/supervisor/conf.d/afk-builder.conf

systemctl enable --now supervisor
supervisorctl reread
supervisorctl update

cat > /etc/cron.d/afk-builder <<CRON
# Scheduler do Laravel — dispara heroes:sync (03:30), heroes:resolve-images
# (03:50) e roster:snapshot (04:00), nos horarios de America/Sao_Paulo.
* * * * * ${APP_USER} cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /var/log/afk-builder/scheduler.log 2>&1
CRON
chmod 644 /etc/cron.d/afk-builder

# ---------------------------------------------------------------------------
# 10. certbot
# ---------------------------------------------------------------------------
log "Instalando certbot"
apt-get install -y -qq certbot python3-certbot-nginx

# ---------------------------------------------------------------------------
# 11. Credenciais
# ---------------------------------------------------------------------------
umask 077
cat > "$CRED_FILE" <<CRED
# Gerado pelo provision.sh — copie para o .env e apague este arquivo.
DB_DATABASE=afk_builder
DB_USERNAME=afk_builder
DB_PASSWORD=${DB_PASS}
CRED
chmod 600 "$CRED_FILE"

log "Provisionamento concluido"
cat <<FIM

Credenciais do MySQL em: $CRED_FILE

Proximos passos:
  1. sudo -u $APP_USER cp $APP_DIR/deploy/oracle/env.production.example $APP_DIR/.env
  2. Edite $APP_DIR/.env: cole a DB_PASSWORD acima e ajuste o APP_URL
  3. sudo -u $APP_USER php $APP_DIR/artisan key:generate
  4. sudo bash $APP_DIR/deploy/oracle/deploy.sh
  5. sudo certbot --nginx -d ${DOMAIN:-SEU_DOMINIO}

FIM
