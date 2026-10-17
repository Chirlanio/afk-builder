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

**Atalho recomendado** — sobe tudo (backend + Vite + queue worker + logs) num único terminal:

```powershell
.\start.ps1            # Windows / PowerShell
.\start.cmd            # idem (clique-duplo no Explorer também funciona)
.\start.ps1 -Migrate   # aplica migrations pendentes antes de subir
```

O script valida pré-requisitos (`.env`, `vendor/`, `node_modules/`, banco acessível) e dispara `composer dev`, que orquestra 4 processos via `concurrently`:

- `php artisan serve` — backend Laravel em `http://127.0.0.1:8000`
- `php artisan queue:listen` — worker da fila (necessário para `/admin/sync`)
- `php artisan pail` — logs em tempo real
- `npm run dev` — Vite dev server com HMR

`Ctrl+C` interrompe todos os processos.

**Se preferir terminais separados:**

```bash
php artisan serve --host=127.0.0.1 --port=8000   # backend (terminal 1)
php artisan queue:work                            # worker da fila (terminal 2)
npm run dev                                       # vite dev server (terminal 3)
```

Acesse `http://127.0.0.1:8000`. Crie uma conta em `/register` (sem verificação de email exigida no dev).

## Comandos úteis

| Comando | Descrição |
|---|---|
| `php artisan heroes:sync` | Sincroniza catálogo (incremental — heróis novos entram com `pending_review=true`) |
| `php artisan heroes:sync --initial` | Sync auto-aprovando tudo (1ª importação ou reset) |
| `php artisan heroes:sync --page=Shemira --dry-run` | Testa parser de uma página específica |
| `php artisan heroes:enrich` | Popula `lore` e `abilities` em heróis já importados (só os faltando) |
| `php artisan heroes:enrich --slug=lucius` | Enriquece um herói específico |
| `php artisan heroes:enrich --force` | Re-extrai mesmo se já populado |
| `php artisan heroes:sync-furniture` | Sincroniza habilidades de móveis (F3/F9) da página master Fandom |
| `php artisan heroes:sync-types` | Popula só `heroes.type` (Strength/Intelligence/Agility) via Fandom |
| `php artisan artifacts:sync` | Sincroniza catálogo de artefatos (Dura's + Class) da página Artifacts |
| `php artisan beasts:sync` | Sincroniza nomes de beasts (pets) da página master Beasts |
| `php artisan content:translate` | Traduz EN→PT-BR (lore, abilities, furniture, guides) via Google Translate |
| `php artisan content:translate --type=lore --limit=10` | Limita escopo do translate por tipo e quantidade |
| `php artisan heroes:resolve-images` | Resolve URLs reais da CDN (Special:Filepath → static.wikia.nocookie.net) |
| `php artisan heroes:resolve-images --force` | Re-resolve mesmo URLs já em CDN |
| `php artisan migrate:fresh --seed` | Reset total do BD (apaga catálogo importado!) |
| `php artisan schedule:list` | Lista cron registrado (sync diário 03:30, resolve-images 03:50) |
| `npm run build` | Build de produção do frontend |
| `npx tsc --noEmit` | Type-check TypeScript |

## Estrutura

```
app/
  Console/Commands/        # heroes:sync/enrich/resolve-images, artifacts:sync, beasts:sync,
                           # roster:snapshot, content:translate, ratings sync etc.
  Http/Controllers/        # Domínio (heroes, roster, formations, tier lists, crystal...)
    Admin/                   # Aprovação de heróis, beasts, coleções, stats, /admin/sync
    Settings/                # Perfil, senha, contas de jogo, backup/import, idioma
  Models/                  # Catálogo (Hero, Faction, Equipment, Beast...) + dados do
                           # jogador, todos pertencentes a uma GameAccount
  Services/
    ActiveGameAccount.php    # Resolve a conta de jogo ativa (sessão + default)
    HeroCatalogSync/         # FandomWikiClient, WikitextHeroParser, FandomSyncService
    Optimization/            # PowerCalculator, TeamScorer, optimizers, InvestmentPlanner
    Formations/              # Versionamento de formações
database/
  migrations/              # ~77 migrations (estáticas → catálogo → dados do jogador → contas)
  seeders/                 # Facções, classes, tiers, desafios, sinergias, equipamentos, guias...
resources/js/
  components/              # shadcn UI (ui/) + componentes de domínio:
    formation-editor/        # hero-chip, team-slot, team-row, hero-pool, beast-slot
    hero-detail-modal.tsx    # perfil completo do herói
    roster-edit-modal.tsx    # adicionar/editar herói no roster
  pages/                   # 38 páginas Inertia (dashboard, heroes, roster, formations,
                           # tier-lists, crystal, equipments, guides, admin, settings)
  lib/afk.ts               # Tema das facções, ícones de classe, stages de ascensão
  types/afk.ts             # Tipos compartilhados (Hero, Faction, Formation, etc.)
deploy/hostinger/          # Deploy na Hostinger (scripts, cron, watcher do CI)
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
| 6. Polimento (histórico, comparação, share) | 🚧 6.1-6.5 + 6.7 ✅ · 6.6 aberta sob demanda |
| 7. Perfil completo de heróis (página de detalhe + skills + lore + tier dinâmico) | ✅ |
| 8. Guias de mecânicas (Crítico Ressonante, Chifre de Dragão, Terreno das Bestas, Galeria Tenebrosa, Fenda Temporal) | ✅ |
| 9. Guias adicionais (Hotel Oak, Móveis, Atributos) + Dragon Trial challenge type | ✅ |
| 10. Hotel Furniture (habilidades F3/F9 per-hero, sync da Fandom, indicador no perfil) | ✅ |
| 11. i18n EN/PT-BR (lore, abilities, furniture, guides) com seletor de idioma | ✅ |
| 12. Ajuste fino do algoritmo (47 synergias, class balance refinado, F3/F9 power bonus, profiles afinados) | ✅ |
| 13a. Tipo do herói (Força/Inteligência/Agilidade do infobox + sync) | ✅ |
| 13b. Stats numéricos via UI admin (HP/ATK/DEF/Crit Rate/Crit Amp/Accuracy/Dodge/Haste) | ✅ |
| 13c. Stats integradas ao optimizer (PowerCalculator soma contribution quando cadastradas) | ✅ |
| 14a. Artefatos via wiki sync (Dura's + Class, atribuição por herói com star level) | ✅ |
| 14b. Coleções da Galeria Tenebrosa (cadastro manual admin, mutex com artefato) | ✅ |
| 14c. Beasts (pets — sync de nomes via wiki, buffs S/I/A admin, ativo por formação) | ✅ |
| 14d. Integração ao optimizer (PowerCalculator soma art/col/beast; scorer/optimizers propagam beast) | ✅ |
| 15. Equipamentos por herói (sistema T1-T6 — substituído pelos Equipamentos Míticos da Fase 58) | ✅ |
| 16. Auth + Dashboard com tema AFK (login como tela inicial, redesign de auth, dashboard com stats, i18n PT-BR via lib) | ✅ |
| 17. Sidebar customizado (logo AFK + nome) + PageShell padrão + dark global (paleta AFK) + 12 páginas migradas | ✅ |
| 18. Mobile-first responsive (DialogContent + PageShell + editor de formações com Pool em bottom sheet) | ✅ |
| 19. Admin de sincronizações (/admin/sync com 8 comandos via queue) + script start.ps1/start.cmd | ✅ |
| 20–24. Tier ratings externos, comps recomendados, tier lists customizadas, destaques por fase | ✅ |
| 25–33. Furniture no scorer, custos de SI, pesos/presets de scoring, estatísticas de uso, notas e filtros de formações | ✅ |
| 34–45. Diff de formações, tags, export PNG, favoritos, busca global (Cmd+K), backup/import JSON, atalhos, bulk edit | ✅ |
| 46–52. Histórico de power, templates de tier list, beasts por equipe, filtros avançados, nº de equipes ajustável, engravings | ✅ |
| 53–57. Analytics do roster, planner de investimento, resource tracker, Cristal Ressonante, comparação de heróis | ✅ |
| 58. Equipamentos Míticos (480 itens, bônus de facção e de set no power) | ✅ |
| 59. Importação de roster via OCR (screenshots) | ✅ |
| 60. Múltiplas contas de jogo por usuário (switcher, CRUD, backup v2) | ✅ |
| 61. Deploy na Hostinger (nexa.app.br) com CI no GitHub Actions | ✅ |

## Quirks do ambiente Windows/WAMP

- **MySQL no WAMP usa MyISAM por padrão**, o que quebra migrations com chaves longas. Já forçamos `'engine' => 'InnoDB'` em `config/database.php`. Não mexa nisso.
- **PHP no WAMP não tem cacert.pem configurado**, então HTTPS via Guzzle dá `cURL error 60`. Resolvido instalando `composer/ca-bundle` e usando `verify => CaBundle::getSystemCaRootBundlePath()` em `FandomWikiClient`.
- **MySQL CLI não está no PATH:** binário em `/c/wamp64/bin/mysql/mysql8.4.7/bin/mysql.exe` (ajuste a versão).

## Cron / scheduler

`routes/console.php` registra três jobs diários:

- `heroes:sync` às **03:30** — detecta heróis novos da wiki
- `heroes:resolve-images` às **03:50** — atualiza URLs de imagem CDN
- `roster:snapshot` às **04:00** — snapshot do power do roster (alimenta o gráfico do dashboard)

Para o scheduler rodar de fato em produção (Linux):

```cron
* * * * * cd /path/to/afk-builder && php artisan schedule:run >> /dev/null 2>&1
```

No Windows, criar tarefa equivalente no Agendador de Tarefas chamando `php artisan schedule:run` a cada minuto.
