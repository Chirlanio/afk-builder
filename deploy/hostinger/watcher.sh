#!/bin/sh
# Aplica o release enviado pelo GitHub Actions (.github/workflows/deploy.yml).
#
# O workflow sobe deploy/release.tar.gz e, por último, deploy/release.sha — este
# script só age quando o .sha existe, então nunca pega um upload pela metade.
# Roda a cada minuto via cron:
#   test -f ~/domains/nexa.app.br/public_html/deploy/release.sha && /bin/sh ~/domains/nexa.app.br/public_html/deploy/watcher.sh
#
# A troca é por rename de diretório: .env e storage/ são preservados, a versão
# anterior fica em afk-builder.prev para rollback manual.

ROOT="$HOME/domains/nexa.app.br/public_html"
D="$ROOT/deploy"
APP="$ROOT/afk-builder"
NEW="$ROOT/afk-builder.new"
PREV="$ROOT/afk-builder.prev"
PHP=/opt/alt/php84/usr/bin/php

[ -f "$D/release.sha" ] || exit 0
mkdir "$D/.lock" 2>/dev/null || exit 0
trap 'rmdir "$D/.lock"' EXIT
exec >> "$D/deploy.log" 2>&1

SHA=$(cat "$D/release.sha")
rm -f "$D/release.sha"
echo "== $(date '+%F %T') deploy $SHA"

rm -rf "$NEW" && mkdir "$NEW"
if ! tar -xzf "$D/release.tar.gz" -C "$NEW"; then
    echo "!! falha ao extrair release.tar.gz — nada foi trocado"
    rm -rf "$NEW"
    exit 1
fi
rm -f "$D/release.tar.gz"

cp "$APP/.env" "$NEW/.env"
rm -rf "$NEW/storage"

rm -rf "$PREV"
mv "$APP" "$PREV" && mv "$NEW" "$APP" && mv "$PREV/storage" "$APP/storage"
ln -sfn ../storage/app/public "$APP/public/storage"
chmod -R u+rwX "$APP/storage" "$APP/bootstrap/cache"

cd "$APP" || exit 1
if ! $PHP artisan migrate --force; then
    echo "!! migrate falhou — código novo no ar; versão anterior em afk-builder.prev"
    exit 1
fi
$PHP artisan optimize
$PHP artisan queue:restart

# O próprio watcher vem no release: mantém a cópia do servidor atualizada.
cp "$APP/deploy/hostinger/watcher.sh" "$D/watcher.sh"

echo "== $(date '+%F %T') ok $SHA"
