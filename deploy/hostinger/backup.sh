#!/bin/sh
# Backup diário do banco e dos uploads, fora do public_html, com retenção.
# Cron (05:30 UTC = 02:30 em Brasília, antes do heroes:sync das 03:30):
#   30 5 * * *  /bin/sh /home/u401878354/domains/nexa.app.br/public_html/afk-builder/deploy/hostinger/backup.sh
#
# As credenciais vêm do .env do app e vão para o mysqldump por um arquivo de
# opções temporário (chmod 600) — nunca pela linha de comando, onde
# apareceriam no `ps`.

APP="$HOME/domains/nexa.app.br/public_html/afk-builder"
DEST="$HOME/backups/afk-builder"
KEEP_DAYS=14

mkdir -p "$DEST" && chmod 700 "$DEST"
exec >> "$DEST/backup.log" 2>&1
echo "== $(date '+%F %T') backup"

env_value() {
    sed -n "s/^$1=//p" "$APP/.env" | tail -n 1 | sed -e "s/^['\"]//" -e "s/['\"]\$//"
}

STAMP=$(date +%Y%m%d-%H%M%S)
OPTS=$(mktemp) && chmod 600 "$OPTS"
trap 'rm -f "$OPTS"' EXIT
cat > "$OPTS" <<EOF
[client]
host=$(env_value DB_HOST)
port=$(env_value DB_PORT)
user=$(env_value DB_USERNAME)
password=$(env_value DB_PASSWORD)
EOF

DUMP="$DEST/db-$STAMP.sql.gz"
if mysqldump --defaults-extra-file="$OPTS" --single-transaction --quick --no-tablespaces \
        "$(env_value DB_DATABASE)" | gzip -9 > "$DUMP" \
    && [ "$(gzip -dc "$DUMP" | tail -n 1 | grep -c 'Dump completed')" = 1 ]; then
    chmod 600 "$DUMP"
    echo "banco: $(basename "$DUMP") ($(du -h "$DUMP" | cut -f1))"
else
    echo "!! falha no mysqldump — arquivo descartado"
    rm -f "$DUMP"
fi

# Uploads do /admin (imagens de beasts etc.) — pequenos, vão junto.
if [ -d "$APP/storage/app/public" ]; then
    tar -czf "$DEST/uploads-$STAMP.tar.gz" -C "$APP/storage/app" public && chmod 600 "$DEST/uploads-$STAMP.tar.gz"
    echo "uploads: uploads-$STAMP.tar.gz ($(du -h "$DEST/uploads-$STAMP.tar.gz" | cut -f1))"
fi

find "$DEST" -maxdepth 1 \( -name 'db-*.sql.gz' -o -name 'uploads-*.tar.gz' \) -mtime +$KEEP_DAYS -print -delete \
    | sed 's/^/removido (> '"$KEEP_DAYS"' dias): /'
echo "== ok ($(ls "$DEST"/db-*.sql.gz 2>/dev/null | wc -l) backups do banco guardados)"
