#!/usr/bin/env bash
#
# Deploy repetivel do afk-builder na VM Oracle. Rodar como root:
#
#   sudo bash /var/www/afk-builder/deploy/oracle/deploy.sh
#
# Faz: pull -> composer -> build do Vite -> migrate -> caches -> reload.
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/afk-builder}"
APP_USER="${APP_USER:-afk}"
BRANCH="${BRANCH:-main}"
PHP_V="8.4"

if [[ $EUID -ne 0 ]]; then
    echo "Rode como root: sudo bash $0" >&2
    exit 1
fi

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
as_app() { sudo -u "$APP_USER" -H "$@"; }

cd "$APP_DIR"

if [[ ! -f .env ]]; then
    echo "Falta o $APP_DIR/.env — copie de deploy/oracle/env.production.example" >&2
    exit 1
fi

# Modo manutencao. O `|| true` cobre o caso de a app ainda nao ter bootado
# nenhuma vez (sem APP_KEY, por exemplo) — nao queremos abortar o deploy aqui.
as_app php artisan down --retry=60 || true
trap 'as_app php artisan up || true' EXIT

log "Atualizando o codigo (branch $BRANCH)"
as_app git fetch --prune origin "$BRANCH"
as_app git reset --hard "origin/${BRANCH}"

log "Dependencias PHP"
as_app composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

log "Build do frontend"
as_app npm ci --no-audit --fund=false
as_app npm run build

log "Migrations"
as_app php artisan migrate --force

log "Storage link e permissoes"
as_app php artisan storage:link || true
chown -R "$APP_USER:www-data" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

log "Regerando caches"
as_app php artisan optimize:clear
as_app php artisan config:cache
as_app php artisan route:cache
as_app php artisan view:cache
as_app php artisan event:cache

log "Reiniciando FPM e workers"
# O reload do FPM tambem zera o opcache (que roda com
# opcache.validate_timestamps=0 e nao veria o codigo novo sozinho).
systemctl reload "php${PHP_V}-fpm"
supervisorctl restart afk-builder-worker:*

log "Deploy concluido"
