#!/bin/sh
# Scheduler e fila do Laravel na Hostinger, chamados a cada minuto pelo cron:
#   /bin/sh ~/domains/nexa.app.br/public_html/afk-builder/deploy/hostinger/cron.sh schedule
#   /bin/sh ~/domains/nexa.app.br/public_html/afk-builder/deploy/hostinger/cron.sh queue
#
# O agendador da Hostinger não executa linhas no formato `cd … && php artisan …`
# (ficam sem saída e sem efeito); chamar um script com /bin/sh funciona.

APP="$HOME/domains/nexa.app.br/public_html/afk-builder"
PHP=/opt/alt/php84/usr/bin/php
cd "$APP" || exit 1

case "$1" in
    schedule)
        $PHP artisan schedule:run >> storage/logs/cron.log 2>&1
        ;;
    queue)
        # Um worker por vez: jobs do /admin/sync passam de um minuto, e o cron
        # dispara de novo a cada minuto. O flock faz as execuções seguintes saírem
        # na hora enquanto um worker ainda está processando.
        flock -n storage/framework/queue-worker.lock \
            $PHP artisan queue:work --stop-when-empty --tries=1 --timeout=1800 \
            >> storage/logs/queue.log 2>&1
        ;;
    *)
        echo "uso: $0 schedule|queue" >&2
        exit 2
        ;;
esac
