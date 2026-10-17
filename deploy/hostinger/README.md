# Deploy do afk-builder na Hostinger (hospedagem compartilhada / Cloud)

Produção: **https://nexa.app.br** — conta `u401878354`, plano Cloud Economy, PHP 8.4, LiteSpeed.

Diferente da Oracle (`deploy/oracle/`), aqui não há root, supervisor nem nginx:

| Necessidade | Como é resolvida |
|---|---|
| Servir só `public/` | Repositório em `public_html/afk-builder` + [`public_html.htaccess`](public_html.htaccess) reescrevendo tudo para `afk-builder/public/` |
| `composer install` | Feito pelo deploy Git da Hostinger após cada clone/pull |
| Assets do Vite | `public/build` não está no Git: build local + upload de `build.zip` |
| Fila (`/admin/sync`) | Cron a cada minuto com `queue:work --stop-when-empty` |
| Scheduler | Cron a cada minuto com `schedule:run` |
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
    * * * * *  cd ~/domains/nexa.app.br/public_html/afk-builder && /opt/alt/php84/usr/bin/php artisan schedule:run
    * * * * *  cd ~/domains/nexa.app.br/public_html/afk-builder && /opt/alt/php84/usr/bin/php artisan queue:work --stop-when-empty --max-time=55 --tries=1
    ```
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

Depois que `nexa.app.br` estiver no ar com SSL, apagar o site provisório no hPanel.

## Deploys seguintes

1. `git push` para `main`.
2. Deploy Git de novo (mesmo repositório, branch e diretório): faz pull + `composer install`.
3. Se o frontend mudou: refazer o passo 7 (build + `build.zip`).
4. Cron único: `php artisan migrate --force && php artisan optimize`.
