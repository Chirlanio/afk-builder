# AFK Builder

Aplicação web para montar e otimizar formações de equipes de **AFK Arena**, levando em conta os heróis que o jogador possui (roster), atributos (nível, ascensão, Assinatura, Móveis, Inscrições), facções, classes, sinergias e bônus de formação.

Suporta múltiplos modos de desafio com 1 a 7 equipes (Campanha, Torre do Rei, Arena, Campeonato das Lendas, Reino dos Sonhos, Reino Amaldiçoado, Expedição Abissal, Labirinto Arcano, Provas Generosas).

## Stack

- **Backend:** Laravel 12, PHP 8.4, MySQL 8 (forçado InnoDB, ver Quirks abaixo)
- **Frontend:** Inertia.js 2 + React 19 + TypeScript + Tailwind 4 + componentes estilo shadcn (já incluídos no starter kit)
- **Drag-and-drop:** `@dnd-kit/core` + `@dnd-kit/sortable`
- **HTTP / wiki sync:** Laravel HTTP client + `composer/ca-bundle` + MediaWiki API (Fandom)
- **Build:** Vite

## Setup do zero

Pré-requisitos: PHP 8.2+, Composer 2.x, Node 18+, MySQL 8.x (WAMP no Windows funciona — ver Quirks).

```bash
# 1. Dependências
composer install
npm install

# 2. .env
cp .env.example .env
php artisan key:generate

# 3. Criar banco MySQL
mysql -u root -e "CREATE DATABASE afk_builder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Schema + dados estáticos (8 facções, 5 classes, 3 tiers, 10 tipos de desafio)
php artisan migrate
php artisan db:seed

# 5. Importar catálogo de heróis da Fandom Wiki (~3 min, 213 heróis)
php artisan heroes:sync --initial
php artisan heroes:resolve-images
```

## Rodar em desenvolvimento

```bash
php artisan serve --host=127.0.0.1 --port=8000   # backend (terminal 1)
npm run dev                                       # vite dev server (terminal 2)
```

Acesse `http://127.0.0.1:8000`. Crie uma conta em `/register` (sem verificação de email exigida no dev).

## Comandos úteis

| Comando | Descrição |
|---|---|
| `php artisan heroes:sync` | Sincroniza catálogo (incremental — heróis novos entram com `pending_review=true`) |
| `php artisan heroes:sync --initial` | Sync auto-aprovando tudo (1ª importação ou reset) |
| `php artisan heroes:sync --page=Shemira --dry-run` | Testa parser de uma página específica |
| `php artisan heroes:resolve-images` | Resolve URLs reais da CDN (Special:Filepath → static.wikia.nocookie.net) |
| `php artisan heroes:resolve-images --force` | Re-resolve mesmo URLs já em CDN |
| `php artisan migrate:fresh --seed` | Reset total do BD (apaga catálogo importado!) |
| `php artisan schedule:list` | Lista cron registrado (sync diário 03:30, resolve-images 03:50) |
| `npm run build` | Build de produção do frontend |
| `npx tsc --noEmit` | Type-check TypeScript |

## Estrutura

```
app/
  Console/Commands/        # heroes:sync, heroes:resolve-images
  Http/Controllers/        # HeroController, UserHeroController, FormationController
  Models/                  # Faction, Hero, HeroClass, Tier, ChallengeType, UserHero,
                           # Formation, FormationTeam, FormationTeamHero, SyncLog, etc.
  Services/HeroCatalogSync/  # FandomWikiClient, WikitextHeroParser, FandomSyncService
database/
  migrations/              # 15 migrations (tabelas estáticas → catálogo → user data)
  seeders/                 # FactionSeeder, HeroClassSeeder, TierSeeder, ChallengeTypeSeeder
resources/js/
  components/              # shadcn UI + componentes específicos:
    formation-editor/        # hero-chip, team-slot, team-row, hero-pool
    hero-card.tsx              # card do catálogo
    roster-modal.tsx           # modal de adicionar/editar herói no roster
  pages/                   # Inertia pages: heroes/index, formations/{index,create,edit}
  lib/afk.ts               # Tema das facções, ícones de classe, stages de ascensão
  types/afk.ts             # Tipos compartilhados (Hero, Faction, Formation, etc.)
docs/
  IMPLEMENTATION.md        # Status detalhado por fase + schema + conventions
```

## Status atual

Ver [`docs/IMPLEMENTATION.md`](docs/IMPLEMENTATION.md) para detalhes.

| Fase | Status |
|---|---|
| 0. Bootstrap (Laravel 12 + Inertia + React + MySQL) | ✅ |
| 1. Schema do domínio (15 migrations + models + seeders) | ✅ |
| 2. Importação automática do catálogo (Fandom MediaWiki API) | ✅ |
| 3. Catálogo + roster do usuário (cards por facção, tema in-game, modal CRUD) | ✅ |
| 4. Editor de formações com drag-and-drop | ✅ |
| 5. Algoritmo de otimização (sugerir formação automaticamente) | ✅ |
| 6. Polimento (histórico, comparação, share) | 🚧 6.1-6.5 ✅ aprovação/criação/share/comparação/versionamento · 6.6/6.7 pendentes |

## Quirks do ambiente Windows/WAMP

- **MySQL no WAMP usa MyISAM por padrão**, o que quebra migrations com chaves longas. Já forçamos `'engine' => 'InnoDB'` em `config/database.php`. Não mexa nisso.
- **PHP no WAMP não tem cacert.pem configurado**, então HTTPS via Guzzle dá `cURL error 60`. Resolvido instalando `composer/ca-bundle` e usando `verify => CaBundle::getSystemCaRootBundlePath()` em `FandomWikiClient`.
- **MySQL CLI não está no PATH:** binário em `/c/wamp64/bin/mysql/mysql8.4.7/bin/mysql.exe` (ajuste a versão).

## Cron / scheduler

`routes/console.php` registra dois jobs diários:

- `heroes:sync` às **03:30** — detecta heróis novos da wiki
- `heroes:resolve-images` às **03:50** — atualiza URLs de imagem CDN

Para o scheduler rodar de fato em produção (Linux):

```cron
* * * * * cd /path/to/afk-builder && php artisan schedule:run >> /dev/null 2>&1
```

No Windows, criar tarefa equivalente no Agendador de Tarefas chamando `php artisan schedule:run` a cada minuto.
