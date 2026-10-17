#!/bin/sh
# Faz o site provisório (*.hostingersite.com) servir a mesma pasta do nexa.app.br.
exec >> "$HOME/domains/nexa.app.br/public_html/check.log" 2>&1
P="$HOME/domains/aqua-toad-818246.hostingersite.com"
[ -d "$P" ] || { echo "== $(date) pasta do site provisorio ainda nao existe"; exit 0; }
[ -L "$P/public_html" ] && exit 0
rm -rf "$P/public_html" && ln -s "$HOME/domains/nexa.app.br/public_html" "$P/public_html" && echo "== $(date) link ok"
ls -la "$P"
