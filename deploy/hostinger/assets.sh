#!/bin/sh
# Troca public/build pelo build novo (enviado como build.tar.gz) — uma vez só.
exec >> "$HOME/domains/nexa.app.br/public_html/check.log" 2>&1
cd "$HOME/domains/nexa.app.br/public_html" || exit 1
rm -f assets.sh
[ -f build.tar.gz ] || { echo "== $(date) sem build.tar.gz"; exit 0; }
rm -rf afk-builder/public/build.new && mkdir afk-builder/public/build.new \
  && tar -xzf build.tar.gz -C afk-builder/public/build.new --strip-components=1 \
  && rm -rf afk-builder/public/build.old && mv afk-builder/public/build afk-builder/public/build.old \
  && mv afk-builder/public/build.new afk-builder/public/build && rm -rf afk-builder/public/build.old build.tar.gz \
  && echo "== $(date) assets atualizados: $(ls afk-builder/public/build/assets | wc -l) arquivos"
