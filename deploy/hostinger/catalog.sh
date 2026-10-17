#!/bin/sh
# Etapa 2: importa o catálogo da Fandom Wiki e roda os seeders que dependem dele.
cd "$HOME/domains/nexa.app.br/public_html" || exit 1
rm -f catalog.sh
PHP=/opt/alt/php84/usr/bin/php
[ -x "$PHP" ] || PHP=php
cd afk-builder || exit 1
run() { echo "== $*"; $PHP artisan "$@" 2>&1 | tail -4; }
run heroes:sync --initial
run heroes:enrich
run heroes:resolve-images
run heroes:sync-types
run heroes:sync-furniture
run furniture:classify-effects
run artifacts:sync
run beasts:sync
run heroes:sync-ratings
for s in HeroSynergySeeder HeroEngravingsSeeder HeroFeaturesSeeder BeastTiersSeeder RecommendedFormationsSeeder SignatureItemsGuideSeeder; do
  run db:seed --class=$s --force
done
run beasts:seed-buffs
run heroes:classify-capabilities
run optimize
echo "== FIM catalogo"
