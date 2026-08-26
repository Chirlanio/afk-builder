# Deploy do afk-builder na Oracle Cloud Always Free

Stack: Ubuntu 24.04 LTS (aarch64) + nginx + PHP 8.4-FPM + MySQL 8 + supervisor + certbot.
Custo: **$0/mês**, sem expiração.

Diferente de hospedagem serverless, aqui o projeto roda inteiro: `storage:link` para os
uploads do `/admin/beasts`, `queue:work` para o `RunArtisanCommandJob` do `/admin/sync`,
e `schedule:run` para o `heroes:sync` / `heroes:resolve-images` / `roster:snapshot`.

## 1. Criar a instância no console da OCI

1. **Compute → Instances → Create instance**
2. **Image:** Canonical Ubuntu 24.04
3. **Shape:** *Ampere* → `VM.Standard.A1.Flex` → **2 OCPU / 12 GB**
   Esse é o teto do Always Free desde 15/jun/2026 (era 4 OCPU / 24 GB). Passar disso
   tira a instância do free tier.
4. **Boot volume:** 50 GB (o Always Free dá 200 GB no total)
5. Salve a chave SSH pública e anote o **IP público**

> Se aparecer `Out of host capacity`, é a escassez crônica de Ampere na região.
> Troque de Availability Domain ou tente em outro horário — não é erro de configuração.

## 2. Abrir as portas na Security List

**VCN → Subnet → Security List → Add Ingress Rules**, duas regras:

| Source CIDR | Protocol | Destination Port |
|---|---|---|
| `0.0.0.0/0` | TCP | 80 |
| `0.0.0.0/0` | TCP | 443 |

Isso **não é suficiente** por si só. As imagens Ubuntu da OCI vêm com uma chain
`INPUT` do iptables que termina em `REJECT`, liberando apenas a 22 — o pacote chega
na VM e morre lá. O `provision.sh` resolve isso inserindo o `ACCEPT` antes da regra
`REJECT` e persistindo com `netfilter-persistent`. É a causa nº 1 de "abri a porta e
o site não responde" na Oracle.

## 3. Provisionar

```bash
ssh ubuntu@<ip-publico>

sudo apt-get update && sudo apt-get install -y git
git clone https://github.com/Chirlanio/afk-builder.git /tmp/afk-builder
sudo bash /tmp/afk-builder/deploy/oracle/provision.sh afk.seudominio.com
```

Leva uns 5-10 minutos. O script é idempotente — pode rodar de novo.

Se ainda não tiver domínio, rode sem o argumento (`sudo bash .../provision.sh`) que
o nginx atende em `server_name _` e você acessa pelo IP. O certbot precisa de domínio.

## 4. Configurar o `.env`

```bash
sudo -u afk cp /var/www/afk-builder/deploy/oracle/env.production.example /var/www/afk-builder/.env
sudo cat /root/afk-builder-credentials.txt          # senha do MySQL
sudo -u afk nano /var/www/afk-builder/.env          # cole DB_PASSWORD e ajuste APP_URL
sudo -u afk php /var/www/afk-builder/artisan key:generate
```

## 5. Primeiro deploy

```bash
sudo bash /var/www/afk-builder/deploy/oracle/deploy.sh
```

Roda as 72 migrations, o `npm run build`, gera os caches e sobe os workers.

## 6. HTTPS

Aponte o DNS do domínio para o IP público, depois:

```bash
sudo certbot --nginx -d afk.seudominio.com
```

Com o certificado no ar, volte no `.env` e ligue `SESSION_SECURE_COOKIE=true`,
depois `sudo -u afk php artisan config:cache`. A renovação automática já vem
configurada pelo pacote do certbot (timer do systemd).

## 7. Importar o banco local (opcional)

Se quiser levar os dados do WAMP em vez de sincronizar do zero:

```powershell
# no Windows
& "C:\wamp64\bin\mysql\mysql8.4.7\bin\mysqldump.exe" -u root --default-character-set=utf8mb4 afk_builder > afk_builder.sql
scp afk_builder.sql ubuntu@<ip>:/tmp/
```

```bash
# na VM
mysql -u afk_builder -p afk_builder < /tmp/afk_builder.sql
```

Alternativa: rodar os syncs pela UI em `/admin/sync` e popular o catálogo da Fandom Wiki.

---

## Deploys seguintes

```bash
sudo bash /var/www/afk-builder/deploy/oracle/deploy.sh
```

Faz `git reset --hard origin/main`, então qualquer edição feita direto no servidor
é descartada. O deploy sobe o modo manutenção e o derruba mesmo se algum passo falhar.

## Operação do dia a dia

```bash
# Logs
sudo tail -f /var/log/afk-builder/worker.log        # fila
sudo tail -f /var/log/afk-builder/scheduler.log     # cron
sudo tail -f /var/www/afk-builder/storage/logs/laravel-$(date +%F).log
sudo tail -f /var/log/nginx/afk-builder.error.log

# Fila
sudo supervisorctl status
sudo supervisorctl restart afk-builder-worker:*

# Scheduler: conferir o que está agendado
sudo -u afk php /var/www/afk-builder/artisan schedule:list

# Runs de sync órfãos (worker morto no meio de um comando)
sudo -u afk php /var/www/afk-builder/artisan commands:reconcile
```

## Notas específicas deste projeto

- **`opcache.validate_timestamps=0`** está ligado por performance. O código novo só
  vale depois do `systemctl reload php8.4-fpm` — que o `deploy.sh` faz. Se editar um
  arquivo à mão no servidor e "nada acontecer", é isso.
- **`queue:work --tries=1`** é intencional. Os comandos de sync escrevem no banco
  conforme raspam a wiki; um retry automático reprocessaria um sync parcial.
- **`config/database.php` força `'engine' => 'InnoDB'`** — inofensivo aqui (o MySQL do
  Ubuntu já usa InnoDB por padrão), mas necessário no WAMP, que usa MyISAM.
- **Horários do scheduler** seguem `APP_TIMEZONE=America/Sao_Paulo`, e o `provision.sh`
  também põe o SO nesse timezone para os logs baterem.
- **Backups:** o Always Free não inclui backup gerenciado. Vale um cron com
  `mysqldump` + upload para algum object storage; o projeto já tem o fluxo de backup v2
  multi-conta na aplicação, mas isso não cobre o banco inteiro.
