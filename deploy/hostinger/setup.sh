#!/bin/sh
# Etapa 1: extrai o release, migra, semeia dados estáticos e otimiza.
cd "$HOME/domains/nexa.app.br/public_html" || exit 1
rm -f setup.sh
PHP=/opt/alt/php84/usr/bin/php
[ -x "$PHP" ] || PHP=php
echo "== $($PHP -v | head -1)"
if [ -f afk-builder.tar.gz ]; then
  tar -xzf afk-builder.tar.gz && rm -f afk-builder.tar.gz && echo "== release extraido"
fi
rm -f default.php
cd afk-builder || exit 1
chmod -R u+rwX storage bootstrap/cache
$PHP artisan migrate --force 2>&1 | tail -5
$PHP artisan db:seed --force 2>&1 | tail -12
$PHP artisan db:seed --class=EquipmentSeeder --force 2>&1 | tail -2
ln -sfn ../storage/app/public public/storage && echo "== storage link ok"
$PHP artisan optimize 2>&1 | tail -6
echo "== FIM setup"
