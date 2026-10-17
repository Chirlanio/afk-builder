# Deploy do afk-builder na Hostinger (hospedagem compartilhada / Cloud)

Produção: **https://nexa.app.br** — conta `u401878354`, plano Cloud Economy, PHP 8.4, LiteSpeed.

Hospedagem compartilhada: não há root, SSH pelo MCP, supervisor nem nginx:

| Necessidade | Como é resolvida |
|---|---|
| Servir só `public/` | Repositório em `public_html/afk-builder` + [`public_html.htaccess`](public_html.htaccess) reescrevendo tudo para `afk-builder/public/` |
| `composer install` | Feito no GitHub Actions (`--no-dev`); o `vendor/` vai pronto no release |
| Assets do Vite | `public/build` não está no Git: compilado no GitHub Actions e enviado no release |
| Fila (`/admin/sync`) | Cron a cada minuto com [`cron.sh queue`](cron.sh): `queue:work --stop-when-empty` sob `flock` (um worker por vez) e `DB_QUEUE_RETRY_AFTER=1900` |
| Scheduler | Cron a cada minuto com [`cron.sh schedule`](cron.sh) |
| `storage:link` | `symlink()` é bloqueado no PHP web → link criado com `ln -s` via cron |
| Comandos artisan avulsos | Sem SSH pelo MCP: cron de execução única, lido com "Get cron job output" e apagado depois |

## Caminhos

```
/home/u401878354/domains/nexa.app.br/public_html/
├── .htaccess            ← deploy/hostinger/public_html.htaccess
└── afk-builder/         ← clone do repositório (branch main)
    ├── .env             ← só no servidor, fora do Git
    └── public/          ← o que de fato é servido
```

PHP CLI: `/opt/alt/php84/usr/bin/php`.

## Primeiro deploy

1. **Site e PHP:** criar o site `nexa.app.br` no plano e mudar o PHP para 8.4.
2. **DNS** (domínio no Registro.br): apontar o registro `A` de `nexa.app.br` e de `www` para o IP do servidor da Hostinger mostrado no hPanel. Alternativa: trocar os nameservers para `ns1.dns-parking.com` / `ns2.dns-parking.com`. O SSL é emitido depois que o DNS propagar.
3. **Banco:** criar o MySQL no hPanel (ou pela API). A aplicação conecta em `127.0.0.1:3306`.
4. **Código:** deploy Git de `https://github.com/Chirlanio/afk-builder.git`, branch `main`, diretório `afk-builder`.
5. **`.env`:** copiar [`env.production.example`](env.production.example) para `public_html/afk-builder/.env` e preencher `APP_KEY` e as credenciais do banco. Nunca commitar esse arquivo.
6. **Raiz do site:** subir `public_html.htaccess` como `public_html/.htaccess` e remover o `default.php` da Hostinger.
7. **Assets:** `npm ci && npm run build` localmente, zipar o conteúdo de `public/build` em `build.zip`, subir para `public_html/` e extrair com um cron único:
   ```
   cd ~/domains/nexa.app.br/public_html && rm -rf afk-builder/public/build && mkdir -p afk-builder/public/build && unzip -oq build.zip -d afk-builder/public/build && rm build.zip
   ```
8. **Setup inicial** (cron único rodando [`setup.sh`](setup.sh), enviado para `public_html/`): extrai o release, `migrate`, `db:seed` + `EquipmentSeeder`, `storage` link e `optimize`.
9. **Catálogo** (cron único rodando [`catalog.sh`](catalog.sh), alguns minutos): `heroes:sync --initial`, enrich, imagens, tipos, móveis, artefatos, beasts, ratings e os seeders que dependem dos heróis (sinergias, inscrições, features, tiers de beasts, comps recomendados).
10. **Crons fixos:**
    ```
    * * * * *  /bin/sh /home/u401878354/domains/nexa.app.br/public_html/afk-builder/deploy/hostinger/cron.sh schedule
    * * * * *  /bin/sh /home/u401878354/domains/nexa.app.br/public_html/afk-builder/deploy/hostinger/cron.sh queue
    ```
    O agendador da Hostinger **não executa** linhas no formato `cd … && php artisan …` — elas ficam sem saída e sem efeito. Sempre chamar um script com `/bin/sh`.
11. **Admin:** em banco novo **ninguém** vira admin sozinho (a promoção da migration `add_is_admin_to_users_table` só atinge usuários que já existiam). Depois de criar sua conta, rode num cron único:
    ```
    cd ~/domains/nexa.app.br/public_html/afk-builder && /opt/alt/php84/usr/bin/php artisan tinker --execute="App\Models\User::where('email', 'SEU@EMAIL')->update(['is_admin' => true]);"
    ```

## Endereço provisório

Enquanto o DNS de `nexa.app.br` não aponta para o servidor, o app responde em
**https://aqua-toad-818246.hostingersite.com** — um site à parte no mesmo plano cujo
`public_html` é um symlink para `~/domains/nexa.app.br/public_html` (criado por
[`link.sh`](link.sh) via cron único). Mesmo código e mesmo banco; o Laravel monta
URLs a partir do host da requisição, então funciona nos dois endereços.

`nexa.app.br` está no ar com SSL desde 2026-10-17 e o site provisório foi excluído
(o symlink foi desfeito antes, para a exclusão não alcançar a pasta de produção).

## Deploys seguintes (CI)

Automático a cada push no `main`, independente de o repositório ser público ou privado:

```
push main ─► tests.yml ─(verde)─► deploy.yml ─► API Hostinger ─► watcher.sh (cron) ─► no ar
```

1. **[`deploy.yml`](../../.github/workflows/deploy.yml)** roda quando o workflow `tests` termina com sucesso no `main` (ou manualmente em *Actions → deploy → Run workflow*). Monta o release (`git archive` + `vendor` sem dev + `public/build` + `public/release.txt` com o SHA), pede uma URL de upload à API da Hostinger e envia `deploy/release.tar.gz` e, por último, `deploy/release.sha`.
2. **[`watcher.sh`](watcher.sh)** (em `public_html/deploy/`, cron a cada minuto) vê o `.sha`, extrai o release em `afk-builder.new`, copia o `.env`, reaproveita o `storage/` e troca os diretórios por rename. Depois roda `migrate --force`, `optimize` e `queue:restart`. A versão anterior fica em `afk-builder.prev`; o log vai para `public_html/deploy/deploy.log`.
3. O workflow espera `https://nexa.app.br/release.txt` devolver o SHA novo. Se não acontecer em 5 minutos, falha e imprime o final do `deploy.log`.

Configuração no GitHub (*Settings → Secrets and variables → Actions*):

| Tipo | Nome | Valor |
|---|---|---|
| Secret | `HOSTINGER_API_TOKEN` | Token da API (hPanel → Perfil → API) |
| Variable (opcional) | `APP_URL` | URL usada para confirmar o deploy; padrão `https://nexa.app.br` |

Cron no servidor:
```
* * * * *  test -f ~/domains/nexa.app.br/public_html/deploy/release.sha && /bin/sh ~/domains/nexa.app.br/public_html/deploy/watcher.sh
```

**Rollback manual** (cron único): trocar `afk-builder` e `afk-builder.prev` de lugar, movendo o `storage/` junto, e rodar `php artisan optimize`. Migrations já aplicadas não são revertidas.

## E-mail (SMTP)

Envio por `no-reply@nexa.app.br` (e-mail grátis do plano, ativado no hPanel em 2026-10-17): `smtp.hostinger.com`, porta 465, SSL — ver [`env.production.example`](env.production.example). A senha da caixa fica só no `.env` do servidor (`MAIL_PASSWORD='…'`, editado pelo Gerenciador de Arquivos) e é preservada pelos deploys.

DNS no Registro.br (modo avançado — a delegação passa para `d.sec.dns.br`/`e.sec.dns.br`):

| Tipo | Nome | Dados |
|---|---|---|
| MX | `nexa.app.br` | `5 mx1.hostinger.com`, `10 mx2.hostinger.com` |
| TXT | `nexa.app.br` | `v=spf1 include:_spf.mail.hostinger.com ~all` |
| TXT | `nexa.app.br` | código de verificação de propriedade da Hostinger |
| CNAME | `hostingermail-{a,b,c}._domainkey` | `hostingermail-{a,b,c}.dkim.mail.hostinger.com` |
| TXT | `_dmarc` | `v=DMARC1; p=none` |

Hoje o app só envia e-mail de redefinição de senha — a verificação de e-mail no cadastro não está ativa (`User` não implementa `MustVerifyEmail`).
