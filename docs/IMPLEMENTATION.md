# AFK Builder — Status de Implementação

Documento vivo do estado da aplicação. Atualizar conforme novas fases concluem.

> **Última atualização:** 2026-05-06 (Fases 18 e 19 — mobile-first responsive + admin de sincronizações)

---

## Fases

### ✅ Fase 0 — Bootstrap

- Laravel 12 React Starter Kit instalado (Inertia 2 + React 19 + Tailwind 4 + auth scaffolding pronto: login/registro/reset/perfil)
- `.env` configurado para MySQL local (`afk_builder`)
- Locale `pt_BR`, timezone `America/Sao_Paulo`
- Forçado `'engine' => 'InnoDB'` em `config/database.php` (WAMP usa MyISAM por padrão)
- Tabelas padrão do Laravel migradas (users, sessions, cache, jobs)

### ✅ Fase 1 — Schema do domínio

15 migrations rodando. Resumo das tabelas:

**Estáticas (seedadas):**
- `factions` (8) — code, name, name_pt, color_hex, advantage_over_faction_id (self-FK), special_behavior (json)
- `hero_classes` (5) — Tank/Warrior/Ranger/Mage/Support
- `tiers` (3) — Common (max 100), Legendary (max 160), Ascended (max 240)
- `challenge_types` (10) — Campaign, King's Tower, Arena of Heroes, Legends Championship, Heroes of Esperia, Dream Realm, Cursed Realm, Abyssal Expedition, Arcane Labyrinth, Bountiful Trials. Cada um define min/max teams, is_pvp, teams_must_be_unique, scoring_profile (json)

**Catálogo:**
- `heroes` — slug (único, derivado do **page title** da wiki), name, title, FK para faction/class/tier, gender, role_label, portrait_url (CDN static.wikia), source_url, is_active, pending_review
- `hero_abilities` — esqueleto pra futura importação de skills (vazio hoje)
- `hero_synergies` — pares de heróis com peso (curado, vazio hoje)

**Dados do usuário:**
- `user_heroes` — roster: level, ascension_code (string), ascension_value (int monotônico para algoritmo), signature_item, furniture, engraving, notes
- `formations` — name, mode (real/target), teams_count, total_score, FK para user e challenge_type
- `formation_teams` — N por formation (1..teams_count)
- `formation_team_heroes` — heróis nos slots (1..5 por equipe), com row (front/back) opcional
- `sync_logs` — auditoria das importações da wiki

Models Eloquent com relacionamentos completos (incluindo `Hero::synergyPartners` many-to-many self).

### ✅ Fase 2 — Importação automática do catálogo

`app/Services/HeroCatalogSync/`:

- **`FandomWikiClient`** — wrapper HTTPS da MediaWiki API (`https://afk-arena.fandom.com/api.php`). Métodos:
  - `listHeroPageTitles()` — lista todos os títulos de `Category:Heroes` paginando via `cmcontinue`
  - `fetchWikitext($title)` — pega o conteúdo bruto de uma página
  - `fetchPageThumbnails($titles[], $size)` — batch de até 50 títulos via `pageimages`, retorna URLs CDN canônicas
  - Throttle automático de 250ms entre calls, User-Agent customizado, retry 2x
  - SSL: usa `composer/ca-bundle` (sem isso quebra no Windows)

- **`WikitextHeroParser`** — parser caractere-a-caractere do template `{{Hero|...}}`. Respeita aninhamento de `{{...}}` e `[[...]]`. Extrai: name, title, faction, class, rarity (=tier), gender, role, image. Mapeia para os codes do schema (Graveborn → graveborn, Ascended → ascended, etc.).

- **`FandomSyncService`** — orquestrador. Para cada título: fetch wikitext → parse → mapeia FKs → upsert. Slug é gerado do **page title** (não do `name` interno) porque variantes Awakened têm `name = X` igual ao herói base. Heróis novos em sync subsequente entram com `pending_review=true` (modo manual de aprovação); flag `--initial` no comando bypassa.

- **`SyncOptions`** / **`SyncResult`** — value objects.

**Comandos Artisan:**
- `php artisan heroes:sync [--initial] [--dry-run] [--limit=N] [--page=Title]`
- `php artisan heroes:resolve-images [--size=400] [--force] [--dry-run]` — backfill das URLs CDN

**Scheduler diário** em `routes/console.php`:
- 03:30 → `heroes:sync`
- 03:50 → `heroes:resolve-images`

**Resultado da importação inicial:** 252 páginas vistas, **213 heróis criados** (zero updates, sem colisão de slug graças à estratégia de page title), 39 ignorados (páginas de categoria, guias, ~10 heróis com tier/classe vazios na wiki — Bronn, Daimon, Angelo, Ankhira, Dreaf, Eletha, Golus, Gourgue, Draconis Archer/Warrior). Distribuição: Maulers 30, Lightbearers 29, Wilders 29, Dimensionals 29, Graveborns 28, Celestials 24, Hypogeans 24, **Draconis 20**.

### ✅ Fase 3 — Catálogo + Roster do usuário

**Backend:**
- `HeroController@index` — lista heróis ativos + facções/classes/tiers + roster do user. Computa `is_awakened = Str::slug(name) !== slug` e injeta no payload.
- `UserHeroController` — store/update/destroy do roster. Validação completa (level capped por tier, ascension enum, SI 0-40, Furniture 0-9, Engraving 0-100). Checagem de ownership.

**Frontend (`resources/js/lib/afk.ts`):**
- `FACTION_THEME` — gradient, borda, hover glow por facção (cores que evocam o jogo: Lightbearers gold, Maulers laranja, Wilders verde, Graveborns roxo, Celestials cyan, Hypogeans red-dark, Dimensionals blue, Draconis rose)
- `CLASS_ICON` — lucide icons (Shield, Sword, Crosshair, Sparkles, Heart)
- `TIER_THEME` — Common stone, Legendary violet, Ascended gradient gold
- `ASCENSION_STAGES` — 17 stages na ordem (common → 5★ ascended), cada um com `value` (int monotônico) e `minTierMaxLevel` (filtra stages válidos por tier)

**Páginas / componentes:**
- `pages/heroes/index.tsx` — busca + filtros toggle (facção, classe), "só meus", **8 seções por facção** com header colorido, grid responsivo de 2 a 6 colunas
- `components/hero-card.tsx` — gradient da facção, badge de tier, ícone de classe, ring verde quando "no roster", **borda/glow âmbar + badge "DESPERTO"** quando is_awakened
- `components/roster-modal.tsx` — Dialog com level, ascensão (select filtrado por tier), Assinatura/Móveis/Inscrições, notas

**Imagens dos heróis:**
- Resolução via `pageimages` API (não Special:Filepath — esse dá 403 na CDN do Fandom). URLs finais em `static.wikia.nocookie.net/...`.
- Cards usam `aspect-[3/4]` + `object-contain` + `object-bottom` (heróis "pisam na mesma linha de chão"), sem cortes na cabeça nem nos pés.

**Glossário PT-BR oficial** (confirmado pelo usuário, jogador BR):
- Lightbearers → **Portadores da Luz** · Maulers → **Perversos** · Wilders → **Selvagens** · Graveborns → **Tumulares** · Celestials → **Celestiais** · Hypogeans → **Hipogeus** · Dimensionals → **Dimensionais** · Draconis → **Draconis**
- Tank → Tanque · Warrior → Guerreiro · Ranger → **Atirador** · Mage → Mago · Support → Suporte
- Signature Item → **Assinatura** (badge "AS") · Furniture → **Móveis** · Engraving → **Inscrições** · Awakened → **Desperto**

### ✅ Fase 4 — Editor de formações com drag-and-drop

`@dnd-kit/core` + `@dnd-kit/sortable` instalados.

**Backend (`FormationController`):**
- `index` / `create` / `store` / `edit` / `update` / `destroy`
- `store` cria N `FormationTeams` vazias em transação
- `update` é **full-replace**: deleta todos os `formation_team_heroes` da formação e re-insere com base no payload (atomic em transação). Valida unicidade de heróis entre equipes quando `teams_must_be_unique`. Valida que heróis estão no roster quando `mode=real`.

**Páginas:**
- `pages/formations/index.tsx` — grid de cards de formações com nome, tipo de desafio, modo, nº equipes, badges de PvP/Alvo, delete por hover
- `pages/formations/create.tsx` — meta form (nome, select de tipo de desafio, número de equipes quando o tipo permite range, escolha visual de modo Real/Alvo)
- `pages/formations/edit.tsx` — editor completo com `DndContext`, layout 2 colunas (equipes à esquerda + pool à direita)

**Componentes (`components/formation-editor/`):**
- `hero-chip.tsx` — portrait pequeno reutilizável em 3 tamanhos
- `team-slot.tsx` — droppable + draggable simultâneo (slot vazio aceita drop, ocupado também é arrastável); botão X pra remover
- `team-row.tsx` — equipe completa com contador X/5, **badges ao vivo** "5x [Facção] ativo" / "3+2: A + B" (com Celestiais como wildcard) e warnings "Sem Tanque" / "Sem Suporte"
- `hero-pool.tsx` — sidebar direita com busca + filtros de facção/classe + grid auto-fit. Heróis já em outras equipes ficam **dimmed e desabilitados** quando o desafio exige unicidade

**Comportamento DnD:**
- Drag pool → slot vazio: coloca herói
- Drag pool → slot ocupado: substitui (herói ejetado volta pro pool)
- Drag slot → slot: swap
- Sob `enforceUnique`: heróis já alocados ficam desabilitados no pool; swap entre slots de equipes diferentes mantém invariante (1 herói = 1 lugar)
- `MouseSensor` com 5px threshold (não dispara em cliques acidentais), `TouchSensor` com 150ms delay
- `DragOverlay` mostra o chip seguindo o cursor

**Save:** botão explícito com indicador "Alterações não salvas" — não autosave (você pode estar experimentando combinações).

### ✅ Fase 5 — Algoritmo de otimização

#### ✅ 5.1 — Backend de scoring (concluído)

`app/Services/Optimization/`:

- **`PowerCalculator`** — fórmula a partir de UserHero: `ascension_value*100 + level + SI*5 + furniture*30 + engraving*0.5`. No modo `target` (sem UserHero), usa baseline pelo tier do herói (common: asc=3 lvl=100; legendary: asc=9 lvl=160; ascended: asc=12 lvl=240).
- **`ScoringProfile`** — value object dos pesos vindos de `challenge_types.scoring_profile`.
- **`ScoreBreakdown`** — resultado com componentes separados (factionBonus, classBalance, synergy, power, total) + detalhes textuais.
- **`TeamScorer`** — pontua 1 equipe combinando:
  - **Faction bonus base:** 5-stack (1.0) e 3+2 (0.6), com Celestial agindo como wildcard, **Dimensionais excluídos** do stacking.
  - **Faction bonus especial:** Hypogean recebe `count*0.1` (progressivo, qualquer quantidade); Draconis ganha `+0.3` quando 2+ na equipe (self-advantage).
  - **Class balance:** tank presente (+0.3), support presente (+0.3), variedade `n_classes*0.08`.
  - **Synergy:** soma `weight/10` de cada par com entrada em `hero_synergies`.
  - **Power:** soma normalizada por 2000.
  - Pesos finais: `total = bonus*W_faction + class*W_class + synergy*W_synergy + power*W_power`.

**Synergias seedadas** (17 duos clássicos AFK Arena Originals): Lucius+Rosaline (15), Eironn+Antandra (14), Ezizh+Skreg (13), Athalia+Hendrik (12), Brutus+Lyca (12), Eironn+Khazard (12), Ezizh+Twins (12), Eironn+Albedo (11), Belinda+Mehira (11), Estrilda+Rowan (11), Atheus+Solise (11), Talene+Lucius (10), Numisu+Brutus (10), Lyca+Estrilda (10), Mehira+Tasi (10), Belinda+Pippa (10), Hendrik+Rowan (10).

Não seedados (slugs ausentes na wiki sync): Saveas, Arden, Twins of Glory (encontrado como `elijah-lailah`), Daimon (em `pending_review` — tier vazio na wiki).

**Testes:** 10 cenários cobertos em `tests/Feature/Optimization/TeamScorerTest.php` — 5-stack puro, 3+2, Celestial wildcard, Dimensional excluído, Hypogean progressivo, Draconis self-advantage, sem tank/support, time balanceado, par com synergia, total ponderado.

#### ✅ 5.2 — Auto-preencher 1 equipe (concluído)

- **`SingleTeamOptimizer`** (`app/Services/Optimization/`) — preenche slots vazios da equipe-alvo:
  - **≤ 2 slots vazios** → busca exaustiva (combinatória completa).
  - **≥ 3 slots vazios** → greedy (escolhe melhor herói por vez) + hill climb (até 100 iterações de swaps locais, para por fixed-point).
  - Respeita `lockedHeroIds` (já posicionados na equipe — não mexe) e `blockedHeroIds` (alocados em outras equipes quando `teams_must_be_unique`).
  - Usa `TeamScorer` + `ScoringProfile` do `challenge_type`.

- **Endpoint** `POST /formations/{formation}/teams/{position}/suggest` (rota `formations.teams.suggest`):
  - Recebe payload com o estado completo das equipes do editor (heróis já posicionados não-salvos).
  - Carrega pool conforme `mode`: catálogo todo (`target`) ou apenas roster (`real`).
  - Carrega synergias de `hero_synergies` e UserHero do roster pra calcular power real.
  - Retorna `{additions: [{slot, hero_id}], score: ScoreBreakdown}` — apenas heróis novos, com slot atribuído nos vazios em ordem.

- **UI** (`team-row.tsx` + `edit.tsx`):
  - Botão **Sugerir** com ícone `Sparkles` aparece em cada equipe enquanto houver slots vazios.
  - Loading state com `Loader2 animate-spin` enquanto requisita.
  - Aplica heróis no estado local + marca `isDirty=true` (não salva — usuário ainda precisa clicar "Salvar").
  - Usa `axios` (CSRF via cookie XSRF-TOKEN do Laravel).

- **Testes:** 5 cenários em `tests/Feature/Optimization/SingleTeamOptimizerTest.php` — preencher 5 vazios, preencher 2 preservando locked, respeitar blocked, pool menor que 5, synergia influencia escolha. Total 15 testes verdes em `Optimization/`.

#### ✅ 5.3 — Auto-distribuir todas as equipes (concluído)

- **`MultiTeamPartitioner`** (`app/Services/Optimization/`):
  - **Initial fill:** chama `SingleTeamOptimizer` em cada equipe (1..N) com `blockedHeroIds` acumulando heróis já alocados (quando `enforceUnique`).
  - **Swap pass:** para cada par (i,j) de equipes, tenta trocar heróis não-locked, aceita se a soma dos scores melhora. Itera até convergir (max 50 passes, fixed-point).
  - Modo não-unique: pula swap pass (cada equipe é independente).

- **Endpoint** `POST /formations/{formation}/suggest-all` (rota `formations.suggest-all`):
  - Recebe estado completo do editor.
  - Retorna `{teams: [{position, additions: [{slot, hero_id}]}], total_score}` — agrupado por equipe, apenas heróis novos.

- **UI** (`edit.tsx`):
  - Botão **Sugerir todas** ao lado do **Salvar** no header do editor.
  - Aplica `additions` em cada equipe via `setTeams` no estado local; marca `isDirty=true`.

- **Testes:** 5 cenários em `tests/Feature/Optimization/MultiTeamPartitionerTest.php` — 3 equipes unique sem sobreposição, preserva locked, locked em uma bloqueia em outras, pool insuficiente, modo não-unique repete heróis. Total **20 testes verdes em `Optimization/`** e **46 testes verdes** no projeto.

### 🚧 Fase 6 — Polimento (em progresso)

#### ✅ 6.1 — UI de aprovação de heróis pendentes (concluído)

- **Schema:** migration `add_is_admin_to_users_table` (boolean, default false). Primeiro usuário do app é promovido a admin automaticamente.
- **Gate** `manage-heroes` registrada em `AppServiceProvider::boot()` checando `$user->is_admin`.
- **`HeroAdminController`** (`app/Http/Controllers/Admin/`) com `pendingIndex` (lista heróis com `pending_review=true`), `approve` (`pending_review=false, is_active=true`) e `reject` (`pending_review=false, is_active=false` — mantém o registro pra histórico).
- **Rotas** com prefixo `admin/` e middleware `can:manage-heroes`:
  - `GET /admin/heroes/pending` (admin.heroes.pending)
  - `PATCH /admin/heroes/{hero}/approve` (admin.heroes.approve)
  - `PATCH /admin/heroes/{hero}/reject` (admin.heroes.reject)
- **UI:** página `pages/admin/heroes/pending.tsx` com cards (portrait + facção/classe/tier + link Fandom + botões aprovar/rejeitar). Item **Aprovar heróis** aparece no sidebar só pra admins (via `auth.user.is_admin` no shared data).
- **Testes:** 6 cenários — guest redireciona, não-admin recebe 403 (index e action), admin lista, admin aprova, admin rejeita.

#### ✅ 6.2 — Importação manual de heróis (concluído)

- **Endpoints** (admin-only):
  - `GET /admin/heroes/create` (admin.heroes.create) — renderiza form com factions/classes/tiers nas props.
  - `POST /admin/heroes` (admin.heroes.store) — valida slug regex `[a-z0-9-]+` único, name, faction/class/tier (FK exists), gender enum opcional, role_label/portrait_url/source_url opcionais. Cria com `is_active=true, pending_review=false`.
- **UI:** página `pages/admin/heroes/create.tsx` com form (slug+name, title, faction/class/tier selects, gender, role, URLs). Botão **Adicionar manualmente** no header da página de pending. Redireciona pro catálogo `/heroes` após criar.
- **Casos de uso:** heróis ignorados pelo sync devido a tier/classe vazios na wiki (Saveas, Daimon, Arden, etc.).
- **Testes:** 6 cenários adicionais — admin acessa form, não-admin 403, admin cria com sucesso, slug duplicado falha, slug com maiúsculas falha, campos obrigatórios validados. 64 testes verdes no projeto.
#### ✅ 6.3 — Share via link público read-only (concluído)

- **Schema:** migration `add_share_token_to_formations_table` (string 40, nullable, unique).
- **`FormationShareController`** com:
  - `share` (POST `/formations/{formation}/share`) — gera `Str::random(32)` se token null; idempotente (mesma chamada retorna mesmo token). Retorna `{share_token, public_url}`.
  - `revoke` (DELETE `/formations/{formation}/share`) — zera o token.
  - `publicShow` (GET `/f/{token}`) — rota pública sem `auth`, valida regex `[A-Za-z0-9]{32}`, 404 se token não existe. Renderiza `pages/formations/public.tsx`.
- **UI:**
  - Botão **Compartilhar** no header do editor abre `Dialog`. Estados: sem token → "Gerar link público"; com token → input read-only do URL + botão **Copiar** (com feedback "Copiado!") + botão **Revogar link**.
  - Página pública `pages/formations/public.tsx`: layout sem sidebar, sem DnD, sem botões de edição. Mostra equipes com bonus de facção computado client-side, atribuição ao usuário criador, link "faça login" pra criar próprias formações.
- **Testes:** 6 cenários — dono gera; outro user 403; revoga; rota pública abre sem auth; 404 com token inválido; idempotência (segunda chamada retorna mesmo token). 58 testes verdes no projeto.
#### ✅ 6.4 — Comparação lado a lado de 2 formações (concluído)

- **`FormationCompareController::show($a, $b)`** — valida ownership de ambas, carrega heroes/userHeroes/synergies, computa `ScoreBreakdown` por equipe via `TeamScorer` usando o `scoring_profile` de cada `challenge_type` (formações de challenge types diferentes mantêm seus próprios pesos). Retorna payload com `formationA`/`formationB` + `total_score` agregado.
- **Rota:** `GET /formations/compare/{a}/{b}` (formations.compare).
- **UI:**
  - Página `pages/formations/compare.tsx` com header de cada formação (nome, challenge type, badge target/PvP, total score), bloco de pontuação total, e linhas pareadas equipe-a-equipe mostrando heróis + breakdown lado a lado (faction/class/synergy/power/total).
  - Componente `ScoreRow` destaca em verde a coluna vencedora por categoria.
  - Página `formations/index.tsx` ganhou checkbox em cada card e botão **Comparar selecionadas** que aparece quando exatamente 2 estão selecionadas. Limita a 2 (selecionar uma 3ª substitui a primeira).
- **Testes:** 3 cenários — owner compara suas próprias, 403 ao comparar de outro user, scores por equipe corretos (5-stack vs vazia). 67 testes verdes no projeto.
#### ✅ 6.5 — Histórico/versionamento de formações (concluído)

- **Schema:** tabela `formation_versions` (id, formation_id FK cascade, snapshot json, created_at). Index em `(formation_id, created_at)`.
- **`FormationVersioning` service** (`app/Services/Formations/`) com:
  - `snapshot(Formation $f)` — serializa `name`, `notes`, `teams` (com slots, hero_id, row) e cria `FormationVersion`. Faz prune se exceder `MAX_VERSIONS_PER_FORMATION = 20`.
  - `restore(Formation, FormationVersion)` — aplica snapshot dentro de transaction (delete + recreate teamHeroes).
- **Auto-snapshot:** `FormationController::update` chama `versioning->snapshot()` ANTES de aplicar o payload novo, então a versão guardada é a *anterior* à edição.
- **Rotas e controller:**
  - `GET /formations/{formation}/versions` — JSON com lista (id, created_at, team_count, hero_count, name).
  - `POST /formations/{formation}/versions/{version}/restore` — snapshot do estado atual + aplica versão escolhida.
- **UI:** botão **Histórico** no header do editor abre `Dialog` listando versões com timestamp pt-BR e contador de heróis/equipes; botão **Restaurar** em cada item (com `confirm()` antes — restore explica que o estado atual também é guardado).
- **Testes:** 6 cenários — update gera snapshot anterior, lista versões (JSON), outro user 403 (index e restore), restore aplica corretamente, cap de 20. **73 testes verdes** no projeto.
#### ⏭️ 6.6 — Refinamentos visuais (pendente — aberto sob demanda)

---

### 🚧 Fase 7 — Perfil completo de heróis

#### ✅ 7.1 — Página de detalhe (`/heroes/{slug}`) (concluído)
- Rota `GET /heroes/{slug}` com regex `[a-z0-9-]+`. `HeroController::show` carrega faction/heroClass/tier/abilities, computa `is_awakened`, busca synergias bidirecionais e UserHero do user logado.
- Página `pages/heroes/show.tsx`: header com portrait grande, nome em gradient para Despertos, badges (facção/classe/tier dinâmico/Desperto/role/gender), info do roster (Nv, ascensão, SI, móveis, inscrições) quando aplicável, link Fandom, seções Habilidades/História com placeholders quando vazias, grid de Sinergias com cards clicáveis.
- Cards do catálogo agora navegam direto para `/heroes/{slug}` (em vez de abrir modal). Modal `RosterModal` continua acessível pela página de detalhe (botão "Adicionar/Editar roster").
- 5 testes (`HeroShowTest`): renderiza com props completas; inclui userHero quando no roster; lista synergias; 404 em slug inexistente; 404 em herói inativo.

#### ✅ 7.2 — Skills + Background do wiki (concluído)
- **Schema:** `heroes.lore` (text nullable) — adicionado via migration.
- **Parser** (`WikitextHeroParser`):
  - `parseLore($wikitext)` — extrai seção `== Background ==` (ou `History`/`Lore`/`Story`); descarta placeholders padrão da wiki ("There is no available background..."); strip de wikitext (links `[[X|Y]]`→`Y`, refs, `<gallery>`, templates, HTML, bold/italic).
  - `parseAbilities($wikitext)` — encontra a tabela de Skills (`{| class="wikitable" ... |}`), parseia cada linha em cells (split por `|`/`!` no início de linha), agrupa por nome (mantém só a primeira ocorrência da skill com descrição completa, ignora upgrades subsequentes), retorna array de `{name, ascension_unlock: 'Nv N', description, display_order, type}`.
- **`FandomSyncService`** agora chama parseLore + parseAbilities a cada hero processado e:
  - Persiste `lore` em `heroes`.
  - `syncAbilities(Hero, abilities[])` → delete + recreate em `hero_abilities` (idempotente).
- **Comando `heroes:enrich`** — popula lore/abilities em heróis já importados sem refazer o sync inteiro:
  - Sem flags: processa só heróis com `lore` null OU sem abilities (idempotente, pode rodar várias vezes).
  - `--force`: re-extrai mesmo já populados.
  - `--slug=X`: processa só esse herói.
  - `--limit=N`: limita batch.
  - Resolve título da wiki via `source_url` (mais robusto que reverter slug).
- 5 testes adicionais no parser (parseLore extrai, retorna null quando ausente ou placeholder; parseAbilities extrai e agrupa por nome; retorna [] sem seção Skills). Validação manual: 6 heróis enriquecidos com sucesso (lore 2.9k-7.6k chars, 4 abilities cada). **94 testes verdes** no projeto.

**Bugfix (parser de tabelas Despertos):** páginas de Despertos usam tabela com 3 colunas (Name | Icon | Description) sem coluna de Unlock Level — parser exigia 4 colunas e descartava todas as linhas. Corrigido para suportar ambos formatos + ignorar linha de fechamento `|}` da tabela. Adicionado teste do formato 3-colunas. Re-rodado `heroes:enrich` em 42 Despertos: agora 212/213 heróis ativos com abilities (Gavus - Keeper of Order é o único restante porque a página dele na wiki não tem seção `== Skills ==`).

#### ✅ 7.3 — Tag de tier dinâmica por ascensão (concluído)

---

### ✅ Fase 8 — Guias de mecânicas

- **Schema:** tabela `guides` (slug unique, title, category enum-like, summary, content longtext, source_url, display_order). Constantes em `Guide` model: `CATEGORY_MECANICAS / CATEGORY_MODOS / CATEGORY_EVENTOS`.
- **Rotas:** `GET /guias` (index agrupado por categoria) e `GET /guias/{slug}` (show com regex `[a-z0-9-]+`).
- **`GuideController`** index agrupa via `groupBy('category')` e mapeia categorias com seus labels e listas. Show usa `firstOrFail` no slug.
- **UI:**
  - `pages/guides/index.tsx` — header + seções por categoria com ícone (`Cog`/`Map`/`Sparkles`); cards com hover para detalhes.
  - `pages/guides/show.tsx` — layout máx 3xl com header (badge categoria, título, summary, link Fandom) e body em `whitespace-pre-wrap`.
  - Item **Guias** adicionado no sidebar.
- **`GuideSeeder`** — busca conteúdo via `FandomWikiClient::fetchWikitext`, faz strip via `WikitextHeroParser::stripWikitext` (tornado público), trunca em 6k chars com indicador. Idempotente via `updateOrCreate(['slug'])`. 5 entradas iniciais:
  - Crítico Ressonante → `Resonating Crystal` (mecânicas)
  - Chifre de Dragão → `Dragon's Rest` (modos)
  - Terreno das Bestas → `Beast Grounds` (mecânicas)
  - Galeria Tenebrosa → `Ghoulish Gallery` (modos)
  - Fenda Temporal → `Temporal Rift` (modos)
- **Limitações conhecidas:**
  - Mapeamento Português → Wiki em Inglês foi inferido (ver `GuideSeeder::ENTRIES`); pode precisar ajustes conforme o usuário valide os termos.

**Refinamento:** novo método `WikitextHeroParser::wikitextToMarkdown()` converte wikitext em Markdown (CommonMark + GFM tables) — substitui o `stripWikitext` cru em guias, lore de heróis e descrição de habilidades. Tabelas `{|...|}` viram tabelas markdown, headers `==X==` viram `## X`, listas `*` e `**` viram `-` e `  -`, formatação `'''bold'''`/`''italic''` vira `**bold**`/`*italic*`, links `[[X|Y]]` viram `Y`. Renderização frontend via `react-markdown` + `remark-gfm` no novo `components/markdown-content.tsx`. Aplicado em `pages/guides/show.tsx`, `pages/heroes/show.tsx` (lore + abilities). 7 testes adicionais no parser. Re-executados: re-seed dos guides + `heroes:enrich --force` em todos os 213 heróis.

---

### ✅ Fase 9 — Guias adicionais e Dragon Trial

- **3 guias novos** populados via `GuideSeeder`:
  - `hotel-oak` (mecânicas) — feature do Oak Inn (hotel) onde heróis Ascendidos ganham bônus de móveis.
  - `moveis-oak-inn` (mecânicas) — sistema de furniture (16 sets × 9 peças × 4 raridades; Mítico exclusivo desbloqueia abilities).
  - `atributos` (mecânicas) — stats dos heróis e bônus de formação (3+2, 4-stack, 5-stack).
- **Novo `ChallengeType` `dragon_trial`** (display_order 110) — "Testes da Força Dracônica":
  - Single team, PvE, sem unicidade.
  - Scoring profile com `power_weight=1.5`, `class_balance_weight=0.7` (foco DPS, classes menos críticas), `dps_focus=true`.
  - User cria formação desse tipo no editor e usa optimizer existente (Sugerir todas / Sugerir esta equipe) — sugestões priorizam heróis de maior power e bônus de Draconis quando aplicável.
- **Hotel Furniture per-user (rastreamento, bônus aplicados ao optimizer):** NÃO implementado — escopo grande (schema com 144 peças possíveis, UI de cadastro, integração com TeamScorer). Reservado para fase futura caso seja prioridade.

8 guias totais publicados em `/guias`. 11 challenge types disponíveis ao criar formação. **106 testes verdes** mantidos.

---

### ✅ Fase 10 — Hotel Furniture (habilidades F3/F9 por herói)

- **Schema:** tabela `hero_furniture_abilities` (id, hero_id FK unique cascade, name, f3_description text nullable, f9_description text nullable). Model `HeroFurnitureAbility` com relação `belongsTo Hero`. Hero ganha `hasOne furnitureAbility`.
- **Parser** — novo método `WikitextHeroParser::parseFurnitureAbilitiesIndex($wikitext)`:
  - Recebe wikitext da página master `Hero Furniture Abilities` (~78k chars).
  - Regex multiline captura cada linha de wikitable: `[[File:...]]<br>[[Hero Name]]` na coluna 1, `[[icon]]<br> Ability Name` na coluna 2, dois `<div>...</div>` blocks na coluna 3 com prefixos `'''[3/9 Mythic Pieces]'''` e `'''[9/9 Mythic Pieces]'''`.
  - Retorna array de `{hero_name, ability_name, f3_description, f9_description}`. F9 é nullable quando só existe F3 na wiki.
  - Descrições passam por `wikitextToMarkdown` (depois de remover prefixo F3/F9) — ficam formatadas como markdown.
- **Comando `heroes:sync-furniture`** (com `--dry-run`):
  - Fetch único da página master.
  - Parseia 121 entradas.
  - Match em duas etapas: por nome exato (case-insensitive) e fallback por slug (kebab-case do nome wiki).
  - `updateOrCreate` por `hero_id` (idempotente).
  - Reporta criados/atualizados/sem-match com lista dos missing.
- **Resultado da execução real:** 117 abilities sincronizadas (114 base + 3 awakened). 1 sem match: Daimon (precisa rodar `heroes:sync` primeiro pra importá-lo no DB).
- **UI no perfil do herói** (`pages/heroes/show.tsx`):
  - Nova seção **Habilidade de Móvel** entre Habilidades e Sinergias.
  - Header com nome da ability + badge de progresso `{userFurniture}/9` quando user possui o herói.
  - Dois cards lado-a-lado (F3 e F9) com badges Lock/Unlock (verde quando user.furniture >= 3, dourado/gradient quando >= 9).
  - Descrições renderizadas via `MarkdownContent`.
- **Testes:**
  - Parser: novo cenário com 3 entradas exemplo (2 com F9, 1 só com F3); verifica nome, ability, descrições limpas (sem prefixo `[N/9 Mythic Pieces]`), F9 null quando ausente.
  - HeroShow: payload inclui `furniture_ability` quando existe; `null` quando não. **109 testes verdes** no projeto.
- **Não implementado** (escopo futuro): bônus do Furniture aplicado ao optimizer (`TeamScorer`) — atualmente o `power_rating` do `user_heroes.furniture` (0-9) já entra na `PowerCalculator` como `furniture * 30`, mas as abilities específicas F3/F9 não influenciam o score (seria preciso modelar effects estruturados).

---

### ✅ Fase 11 — i18n EN/PT-BR para conteúdo

- **Schema:** colunas `_pt` ao lado dos campos originais (mantém EN como fonte da verdade) + `users.locale` (string 2, default `en`):
  - `heroes.lore_pt`
  - `hero_abilities.name_pt`, `description_pt`
  - `hero_furniture_abilities.name_pt`, `f3_description_pt`, `f9_description_pt`
  - `guides.title_pt`, `summary_pt`, `content_pt`
  - `users.locale` + `User::LOCALES = ['en', 'pt']`
- **Endpoint:** `PATCH /settings/locale` (`locale.update`) valida via `Rule::in(User::LOCALES)` e atualiza o user.
- **UI:**
  - `components/locale-switcher.tsx` — `DropdownMenu` no rodapé do sidebar com bandeiras 🇺🇸/🇧🇷 e label "atual" no idioma corrente. Click → `router.patch('/settings/locale')` com `preserveState: false` pra refetch dos dados localizados.
  - Helper `localized(item, field, locale)` em `lib/afk.ts` retorna `{field}_pt` se locale='pt' e preenchido; senão fallback pro `{field}` original.
  - Aplicado em `pages/heroes/show.tsx` (lore, abilities, furniture name+f3+f9), `pages/guides/index.tsx` (title, summary), `pages/guides/show.tsx` (title, summary, content). Mensagens de fallback distinguem "ainda não traduzido" de "ainda não importado".
  - `User` type ganhou `locale?: Locale`. Shared data `auth.user.locale` já fluía via Inertia.
- **Comando `content:translate`** (`--type=lore|abilities|furniture|guides|all`, `--limit=N`, `--slug=X`, `--force`):
  - Usa `stichoza/google-translate-php` (free, sem API key — scrapping do Google Translate).
  - Throttle 200ms entre calls + try/catch por item (não interrompe batch em caso de bloqueio temporário).
  - Idempotente — só traduz onde `_pt` está null (a menos que `--force`).
  - SSL via `Composer\CaBundle` para WAMP.
- **Validação:** lore + 4 abilities do Lucius + 3 furniture abilities + 1 guide traduzidos com qualidade aceitável (ex: "Heaven's Protection" → "Proteção do Céu", "Divine Strike" → "Golpe Divino"). Markdown preservado nas tabelas e formatação.
- **Testes:** novo `LocaleTest` (4 cenários — default 'en', endpoint atualiza, rejeita locale inválido, guest redireciona). **113 testes verdes** no projeto.
- **Para popular o resto:** `php artisan content:translate` (sem flags) pega tudo que falta. ~213 lores + ~850 abilities + 117 furniture + 8 guides → estimativa ~30-60 min com throttle, sem garantia (scraping pode bloquear).

---

### ✅ Fase 12 — Ajuste fino do algoritmo de scoring

Sem dados novos — só refinamento de lógica e expansão de dados curados.

#### Synergias expandidas (17 → 47 duos)
`HeroSynergySeeder` ganhou 30 novos pares (peso 9-12) cobrindo:
- **Maulers:** Khazard+Brutus, Peggy+Brutus, Mishka+Tarnos, Vurk+Khazard
- **Lightbearers:** Ezio+Lucius, Gwyneth+Ezio, Lucretia+Lucius, Estrilda+Lucius
- **Wilders:** Flora+Atheus, Nakoruru+Atheus, Solise+Flora, Saurus+Atheus
- **Graveborns:** Thoran+Grezhul, Kelthur+Thoran, Olgath+Thoran, Crassio+Grezhul
- **Mages:** Walker+Tasi, Mehira+Walker, Rem+Tasi
- **Speed/Burst:** Eironn+Lyca, Drez+Zikis, Ezio+Joker, Theowyn+Ezizh
- **Hypogeans:** Ezizh+Mehira, Baden+Ezizh
- **Celestials/Revive:** Talene+Gwyneth, Talene+Saurus
- **Collabs/Dimensionais:** Ainz+Albedo, Wu Kong+Nakoruru, Mulan+QUEEN

#### `TeamScorer::classBalance` refinado
Antes: `tank?+0.3 / support?+0.3 / unique*0.08`. Agora:
- Mantém presença de tank (+0.3) e support (+0.3)
- Variedade reduzida: `unique * 0.05` (antes 0.08)
- **Penalidade de over-stacking**: `-0.15` quando 4+ heróis da mesma classe
- **Bônus de comp balanceada**: `+0.10` quando ≥3 classes únicas E inclui tank
- Score garantido ≥ 0 (max já era ~1.0 + bônus)

Resultado: time balanceado padrão (tank+support+5 classes) = 0.95 (antes 1.0). Time 4-rangers + tank = 0.25 (antes 0.62). Time sem tank/support com 3 classes = 0.15 (antes 0.24).

#### `PowerCalculator` com bônus F3/F9
Antes: `asc*100 + level + SI*5 + furniture*30 + eng*0.5`. Agora soma:
- `+100` quando `furniture >= 3` (F3 unlock — habilidade transformadora)
- `+200` adicional quando `furniture >= 9` (F9 unlock — versão mais forte)

Reflete que os móveis F3/F9 desbloqueiam habilidades transformadoras (não apenas o stat boost de 30/peça). Herói full power: 2360 → 2660 (+12.7%).

#### Profiles de scoring afinados
- **Dream Realm**: power 1.5 → 2.0; class 0.6 → 0.4; faction 0.7 → 0.5 (boss = pure DPS)
- **Arena of Heroes**: synergy 1.3 → 1.5; class 0.8 → 0.9 (PvP exige meta comp)
- **Cursed Realm**: faction 1.0 → 0.9; class 1.0 → 1.3 (modificadores recompensam variedade)

#### Testes
- `TeamScorerTest`: 3 cenários atualizados pros novos valores + 1 novo teste cobrindo over-stacking penalty.
- Novo `tests/Unit/Optimization/PowerCalculatorTest.php` (4 cenários — fórmula base, F3, F3+F9 cumulativo, threshold entre 3 e 9).
- **118 testes verdes** no projeto.

---

### ✅ Fase 13a — Tipo do herói (Strength/Intelligence/Agility)

Categorização básica do herói disponível no infobox da Fandom (`type=`), proxy do perfil de stats: Strength → high HP/DEF, Intelligence → high Magic, Agility → high ATK/Crit.

- **Schema:** `heroes.type` enum `strength|intelligence|agility` nullable (migration `2026_05_03_120000_add_type_to_heroes_table`).
- **Parser** (`WikitextHeroParser`): nova `TYPE_MAP` aceita `Strength/Intelligence/Agility` (e abreviações `Str/Int/Agi`) → mapeia para os 3 codes. Retorna `null` quando ausente.
- **`FandomSyncService`**: persiste `type` no upsert de cada hero.
- **`heroes:enrich`** estendido: agora também atualiza `type` e `role_label` em heróis já importados (mesmo when-null que lore/abilities).
- **Novo comando `heroes:sync-types`** (`--force`, `--limit`, `--slug`): comando dedicado que só faz fetch + parse + update da coluna type, sem tocar lore/abilities. ~1 min para os 213 heróis.
- **UI:** badge novo no `pages/heroes/show.tsx` ao lado da classe — Força (rose, ícone Dumbbell), Inteligência (sky, Brain), Agilidade (emerald, Wind). Helpers `HERO_TYPE_LABEL/ICON/BADGE` em `lib/afk.ts`.
- **Resultado do backfill:** 208/208 heróis ativos populados — Força 62, Inteligência 85, Agilidade 66.
- **Testes:** 3 novos cenários no `WikitextHeroParserTest` (Strength, Intelligence/Agility extra, ausência → null). **120 testes verdes** no projeto.

---

### ✅ Fase 13b — Stats numéricos via UI admin

Stats base (Nv 1) cadastradas manualmente por admin — Fandom não publica numéricos. Usuários veem read-only.

- **Schema:** tabela `hero_stats` (id, `hero_id` FK unique cascade, hp/attack/defense unsigned int, crit_rate/crit_amp/accuracy/dodge/haste unsigned smallint, todos nullable). Model `HeroStat` 1:1 com `Hero` via `hasOne stats`.
- **Controller** `Admin\HeroStatsAdminController` (`Gate manage-heroes`):
  - `edit($slug)` — renderiza `admin/heroes/stats` com hero+stats atual.
  - `update($slug)` — valida 8 campos (HP/ATK/DEF max 999_999; Crit/Acc/Dodge/Haste max 9_999), normaliza ausentes para null. Se TODOS null → deleta o registro. Senão `updateOrCreate`.
- **Rotas:**
  - `GET /admin/heroes/{slug}/stats` (admin.heroes.stats.edit)
  - `PATCH /admin/heroes/{slug}/stats` (admin.heroes.stats.update)
- **UI admin** (`pages/admin/heroes/stats.tsx`): grid 2 colunas com 8 campos numéricos, helps por campo, botão "Salvar" / "Cancelar".
- **UI no perfil** (`pages/heroes/show.tsx`):
  - Nova seção **Estatísticas Base** entre o header e Habilidades.
  - Componente `StatsGrid` com 8 cards (label + valor formatado pt-BR).
  - Quando vazio: dashed placeholder com link "Cadastrar agora" (visível só pra admin) ou mensagem genérica.
  - Botão **Editar stats** no header da seção (visível só pra admin).
- **Tipos TS:** `HeroStats` em `types/afk.ts` + `Hero.stats?: HeroStats | null`.
- **Testes:** 10 cenários (`HeroStatsAdminTest`) — guest redirect, não-admin 403 (GET+PATCH), admin acessa form, cria, atualiza, payload vazio apaga, validação (negativo, string, acima do max), 404 em slug inexistente, payload `show` inclui stats. **130 testes verdes** no projeto.

---

### ✅ Fase 13c — Stats integradas ao optimizer

`PowerCalculator` agora soma uma contribuição linear das stats quando cadastradas. Heróis sem stats não são penalizados (contribution = 0).

- **`statContribution(?HeroStat)`** — fórmula linear: `HP*0.1 + ATK*1.0 + DEF*0.7 + (CritRate+CritAmp)*0.3 + (Acc+Dodge+Haste)*0.2`. Soma assimétrica que privilegia ATK puro mas valoriza HP/DEF; stats secundárias entram com peso menor.
- **`forUserHero(UserHero, ?Hero)`** — assinatura estendida com hero opcional pra lookup de stats. Backward-compat preservado: chamada sem o segundo argumento mantém a fórmula antiga.
- **`targetBaseline(Hero)`** — também soma stats quando o hero tem (modo target sem roster).
- **Eager loading:** `FormationController::loadHeroPool` e `FormationCompareController` agora incluem `'stats'` no `with()` pra que o calculator não dispare N+1.
- **Comportamento:** com hero típico (HP=1500, ATK=200, DEF=100, demais=50), stats contribuem ~480 pontos — ~20-25% do total. Suficiente pra diferenciar heróis com mesmo level/ascension mas perfis diferentes; insuficiente pra dominar bonificações de facção/sinergia.
- **Testes:** 6 novos cenários no `PowerCalculatorTest` (zero quando null, soma de pesos, campos nulos parciais, integração com forUserHero, backward-compat sem hero, targetBaseline com stats). **136 testes verdes** no projeto.

---

### ✅ Fase 14a — Artefatos via wiki sync

Catálogo de Artefatos da Fandom (Dura's + Class) sincronizado, atribuível a heróis no roster com nível de estrelas.

- **Schema:** tabela `artifacts` com `slug`, `name`, `kind` enum `dura|class`, `hero_class_id` FK nullable (só pra class), `description`, `attributes` json (`[{stat:'HP',value:'8%'}]`), `powers` json (`[{stars:1,description:'...'}]`), `obtained_in`, `portrait_url`, `max_stars` (5 dura/3 class), `is_active`. `user_heroes.artifact_id` FK nullable + `artifact_star_level` tinyint nullable.
- **Parser** (`WikitextHeroParser`):
  - `parseArtifactsIndex($wikitext)` retorna `['dura' => [...], 'class' => [...]]`.
  - Helper `parseArtifactsTable()` lida com 5 colunas (Dura's) e 6 colunas (Class — com coluna de classe). Cells de attributes/powers vêm como `<div>...</div>` divididos via `parseDivList()`.
  - `parseAttributeLine()` parseia `STAT: VALUE`. `parsePowerLine()` detecta `(Enhance to N stars to unlock)` e atribui `stars` (default 1).
  - Bugfix: regex de nome agora aceita apóstrofes (`'''(.+?)'''` lazy) — antes falhava em `Dura's Eye`.
- **Comando `artifacts:sync`** (`--dry-run`): fetch único da página `Artifacts`, parseia ambas as tabelas, mapeia `class_code` → `hero_class_id`, gera URL CDN via `Special:Filepath`, idempotente via `Hero::where('slug')->first()` + update/create. Auto define `max_stars` (5 dura, 3 class).
- **Resultado da sync real:** **22 artefatos** criados — 7 Dura's (Grace, Eye, Call, Drape, Blade, Chalice of Vitality, Conviction) + 15 Class (Mage, Tank, Warrior, Ranger, Support distribuídos).
- **Backend:**
  - `UserHeroController::store/update` aceita `artifact_id` + `artifact_star_level`. `resolveArtifactFields()` valida ownership de classe (artifact class precisa bater com `hero.hero_class_id`) e clampa stars no `max_stars`.
  - `HeroController::show` carrega `userHero.artifact` (com select específico de `description, portrait_url, max_stars`) + `availableArtifacts` (Dura's + class apropriada, ordenados Dura's primeiro via `orderByRaw`).
- **UI:**
  - `RosterModal` ganhou `<Select>` de artefato com 2 grupos (Dura's / Classe) + `<Input>` de stars (1 a max do artifact). Aparece só quando `availableArtifacts.length > 0`. Setar "nenhum" zera stars.
  - `pages/heroes/show.tsx` ganhou seção **Artefato Equipado** com badge de kind, badge de estrelas (★/☆), portrait + nome + descrição. Mostrada só quando `userHero?.artifact` existe.
- **Testes:**
  - 2 novos no `WikitextHeroParserTest` (parse_artifacts_index_extrai_duras_e_class, retorna_vazio_sem_secoes).
  - 8 novos em `UserHeroArtifactTest` (equipa Dura's, equipa class da mesma classe, ignora class de classe diferente, stars clamped no max, validação de FK, remove setando null, show carrega availableArtifacts filtrado por classe, show inclui artifact relation quando equipado).
  - **146 testes verdes** no projeto.

---

### ✅ Refactor de UI — Modal unificada de herói

Migração de fluxos de página dedicada para modal. Visualização, criação e atualização de heróis e stats agora acontecem em modais unificados.

- **Endpoint JSON novo**: `GET /heroes/{slug}/details` retorna todo o payload (hero + userHero + synergies + availableArtifacts) consumido por `axios` quando o modal abre.
- **`HeroDetailModal`** — componente único em `components/hero-detail-modal.tsx`:
  - Header com portrait + badges (facção/classe/tipo/tier/role/desperto).
  - Tabs: **Detalhes** (default, scroll) e **História** (tab separada).
  - Aba Detalhes contém: roster (info + form inline), stats base (com edição inline pra admin), habilidades, móveis F3/F9, artefato equipado, sinergias.
  - Sinergias funcionam como navegação interna — clicar em parceiro troca o `slug` do modal sem fechar.
  - Forms inline (`RosterFormInline`, `StatsFormInline`) usam `axios` direto. Após salvar: `refetch` + `router.reload({only: ['roster']})` pra sincronizar o índice.
- **Página `/heroes`** — `pages/heroes/index.tsx`:
  - Card click abre o modal (não navega mais).
  - Detecta `?open=slug` na URL e abre modal automaticamente; limpa o param via `replaceState`.
- **Permalink legacy** — `GET /heroes/{slug}` redireciona pra `/heroes?open={slug}`. Bookmarks e links externos continuam funcionando.
- **Admin: criar herói via modal** — `CreateHeroModal` em `components/create-hero-modal.tsx`. Removida rota GET `/admin/heroes/create` e página correspondente. Botão "Adicionar manualmente" em `/admin/heroes/pending` agora abre o modal. `pendingIndex` passa `factions/heroClasses/tiers` no payload.
- **Admin: editar stats via modal** — Removida página `/admin/heroes/{slug}/stats`. Endpoint PATCH agora retorna **JSON** (`{ ok: true, stats }`) consumido pelo `StatsFormInline` dentro do `HeroDetailModal`. Botão "Editar stats" só aparece pra admin (`auth.user.is_admin === true`).
- **Componentes novos**: `components/ui/tabs.tsx` (radix wrapper), `components/hero-detail-modal.tsx`, `components/create-hero-modal.tsx`.
- **Componentes/páginas removidos**: `pages/heroes/show.tsx`, `pages/admin/heroes/create.tsx`, `pages/admin/heroes/stats.tsx`.
- **Testes**: `HeroShowTest` reescrito pra cobrir endpoint `/details` (JSON) + redirect do permalink. `HeroStatsAdminTest` adaptado pra patchJson (sem rota GET). `HeroAdminTest` removido `test_admin_acessa_pagina_de_criacao_manual` (página não existe mais). **144 testes verdes** no projeto.

---

### ✅ Fase 14b — Coleções da Galeria Tenebrosa (cadastro manual admin)

Wiki da Fandom não tem dados estruturados de Collections (apenas stub). CRUD totalmente manual via admin. Coleção e Artefato dividem o mesmo slot (mutuamente exclusivos, conforme regra do jogo).

- **Schema:** tabela `collections` com `slug`, `name`, `description`, `hp_bonus`, `attack_bonus`, `defense_bonus` (unsigned int), `portrait_url`, `source_url`, `is_active`. `user_heroes.collection_id` FK nullable.
- **Model**: `App\Models\Collection` com casts numéricos + `userHeroes()` hasMany. `UserHero` ganha `belongsTo collection`.
- **Controller** `Admin\CollectionAdminController` (Gate manage-heroes):
  - `index` (Inertia, lista todas)
  - `store/update` retornam JSON consumido pelo modal
  - `destroy` redirect com flash
  - Validação: slug regex `[a-z0-9-]+` único, name obrigatório, bônus 0-999_999, URLs opcionais.
- **Rotas:** `GET /admin/collections`, `POST /admin/collections`, `PATCH /admin/collections/{collection}`, `DELETE /admin/collections/{collection}`.
- **UI:**
  - `pages/admin/collections/index.tsx` — grid de cards com portrait + nome + bônus + ações Edit/Delete.
  - `components/collection-form-modal.tsx` — modal dialog (max-w-3xl) seguindo estilo dos demais modais (cabeçalho com título + descrição, form em grid, botões Cancelar/Salvar). CRUD via axios.
  - Sidebar admin ganhou item **Coleções** (ícone Package).
- **Integração ao roster:**
  - `UserHeroController` ganha validação de `collection_id` (Rule::exists com `is_active=true`).
  - Lógica de mutex em `resolveEquipmentFields`: quando ambos artifact_id e collection_id vêm preenchidos, **Coleção tem prioridade** e zera o artefato (regra do jogo: Collection sobrescreve Artifact). Quando só artifact vem, zera collection.
  - `HeroController::details` carrega `userHero.collection` (relação) + `availableCollections` (todas ativas).
- **UI no perfil:**
  - Slot "Coleção" no `HeroDetailModal` (placeholder antigo) agora mostra o item equipado (portrait + nome + bônus HP/ATK/DEF).
  - `RosterEditModal` ganhou Select de coleções no slot esquerdo. Selecionar coleção zera artefato no estado local; selecionar artefato zera coleção.
- **Tipo TS:** `Collection` em `types/afk.ts`. `UserHero` ganha `collection_id` + `collection`.
- **Testes:**
  - `CollectionAdminTest`: 8 cenários (auth, list, create, validação slug único/pattern, update, delete).
  - `UserHeroArtifactTest`: 5 novos cenários (equipa coleção, coleção sobrescreve artifact no mesmo payload, artifact substitui coleção em update, validação FK, details inclui availableCollections filtrado por is_active).
  - **157 testes verdes** no projeto.

---

### ✅ Fase 14c — Beasts (pets)

Beasts são os "pets" do AFK Arena. Cada beast aplica buffs aos heróis do tipo correspondente (Strength/Intelligence/Agility) na equipe ativa. Apenas 1 beast ativo por formação.

- **Schema:** tabela `beasts` (slug, name, description, `strength_buff`, `intelligence_buff`, `agility_buff` unsigned smallint, portrait_url, source_url, is_active). `formations.beast_id` FK nullable.
- **Sync via wiki:** comando `beasts:sync` faz fetch da página master `Beasts` e extrai a lista de nomes na seção `== Beast List ==`. Aceita `[[Name]]` (com link) e texto plano. **Wiki só tem nomes — buffs precisam ser cadastrados manualmente via admin.** Resultado: 27 beasts persistidos (Dreary Ball, Tufty Ears, Polar Beast, Nightfall Pegasus, etc).
- **Admin CRUD** (`Admin\BeastAdminController`): index Inertia + JSON store/update/destroy. Página `/admin/beasts` com grid de cards mostrando portrait, badges de buffs (S/I/A com cores correspondentes), descrição. `BeastFormModal` para CRUD.
- **Sidebar admin** ganhou item **Beasts** (ícone PawPrint).
- **Atribuição em formação:** dropdown no header do `/formations/{id}/edit` permite escolher beast ativo. Mostra nome + buffs no formato `Beast · F+100 / I+50 / A+75`. Salva em `formations.beast_id` no PATCH. Validação inclui `Rule::exists('beasts')->where('is_active', true)`.
- **Tipo TS:** `Beast` em `types/afk.ts` + `Formation.beast_id` / `Formation.beast?`.
- **Testes:**
  - `BeastAdminTest`: 7 cenários (auth, list, create, validação slug único, update, delete).
  - `FormationBeastTest`: 5 cenários (carrega lista, atribui beast, remove com null, rejeita inexistente, rejeita inativo).
  - **169 testes verdes** no projeto.

---

### ✅ Fase 14d — Integração ao optimizer

`PowerCalculator` agora soma contribuições de Artefato/Coleção/Beast quando dados disponíveis. `TeamScorer` e os optimizers propagam o beast através das chamadas.

- **`PowerCalculator` ganhou 3 métodos novos:**
  - `artifactContribution(?Artifact, ?int $stars)`: bônus base por estrelas (50 * stars; 1★=50, 5★=250) + somatório leve dos atributos numéricos (`HP%`, `CRIT`, `HASTE`, etc) com multiplicador 2.
  - `collectionContribution(?Collection)`: usa os bônus HP/ATK/DEF da Coleção com a mesma escala da `statContribution` (HP*0.1, ATK*1.0, DEF*0.7).
  - `beastBuffForType(?Beast, ?string $heroType)`: retorna o buff bruto do beast para o tipo do herói (Strength/Intelligence/Agility) ou 0 quando não bate.
- **`forUserHero(UserHero, ?Hero, ?Beast)`** estendido: soma `artifactContribution` (via relação `artifact` carregada), `collectionContribution` (via relação `collection`), e `beastBuffForType * BEAST_BUFF_FACTOR (0.5)`. Acessos a relations protegidos por `relationLoaded` pra não disparar lazy load (importante pra testes unitários).
- **`forHero(Hero, ?UserHero, ?Beast)`** também aplica beast no modo target (sem userHero), já que beast vem da formação não do roster.
- **Helper privado `safeStats(?Hero)`**: blinda acesso a `$hero->stats` com `relationLoaded` check — evita N+1 queries em optimizers e falhas de unit test sem DB.
- **`TeamScorer::score(...$profile, ?Beast $beast = null)`**: assinatura estendida; passa beast para `powerScore` que repassa ao `forHero` por hero. Backward-compat: chamadas existentes sem beast continuam funcionando.
- **`SingleTeamOptimizer::optimize`** + helpers `exhaustive` e `greedyWithHillClimb` ganham param `?Beast` opcional, propagado em todas as chamadas internas a `scorer->score`.
- **`MultiTeamPartitioner::optimize`** + `initialFill` + `swapOptimize` mesma extensão.
- **Controllers atualizados:**
  - `FormationController::suggestTeam` e `suggestAll` carregam `formation->beast` (select otimizado: id+buffs) e passam ao optimizer.
  - `FormationCompareController` carrega `beast` em ambas formações e passa ao scorer no `buildFormationPayload`.
  - `loadUserHeroes` e o eager-load no Compare agora incluem `artifact:id,attributes` e `collection:id,hp_bonus,attack_bonus,defense_bonus` pra que `PowerCalculator` veja as relações como carregadas.
- **Testes:** 8 novos cenários no `PowerCalculatorTest` (artifact/collection/beast contributions, integração com forUserHero). **177 testes verdes** no projeto.

---

### ✅ Fase 15 — Equipamentos por herói (4 slots: Arma/Capacete/Peitoral/Bota)

Cada herói no roster pode ter 4 peças de equipamento equipadas. Cada peça tem **tier** (T1 Comum → T6 Ascendido) e **level** (1-20). Influenciam no power score.

- **Schema:** 8 novas colunas em `user_heroes` (4 pares tier+level). Tiers: T1..T6 (varchar 8 nullable). Levels: tinyint nullable 1-20.
- **Constantes** em `App\Models\UserHero`:
  - `EQUIPMENT_TIERS = ['T1', ..., 'T6']`
  - `EQUIPMENT_TIER_LABELS` mapping (Comum, Raro, Elite, Lendário, Mítico, Ascendido)
  - `EQUIPMENT_LEVEL_MAX = 20`
  - `EQUIPMENT_SLOTS = ['weapon', 'helmet', 'chest', 'boots']`
- **Validação no `UserHeroController`:**
  - Cada slot: `tier` em `Rule::in(EQUIPMENT_TIERS)`, `level` 1-20.
  - `equipmentSlotFields()` normaliza: tier sem level (ou vice-versa) vira null pra evitar registros parciais.
- **PowerCalculator:**
  - Novo `equipmentContribution(?string $tier, ?int $level)`: fórmula `tier_value * 10 + level * 2`. Ex: T6 Lv20 = 100, T1 Lv1 = 12. Quatro peças max ≈ 400 (significativo mas não dominante vs ~1500-2500 de base).
  - `totalEquipmentContribution(UserHero)` soma os 4 slots.
  - `forUserHero` agora inclui `totalEquipmentContribution`.
- **UI no `RosterEditModal`:**
  - 4 slots laterais (Arma/Capacete/Peitoral/Bota) ganharam componente `EquipmentInputs` com Select de tier + Input de level.
  - Trigger do Select recebe a cor temática do tier (T1 cinza → T6 dourado gradient) via `EQUIPMENT_TIER_BADGE`.
  - Selecionar tier auto-define level=1; "vazio" zera ambos.
- **UI no `HeroDetailModal`:**
  - Componente `EquipmentSlot` mostra tier + Level quando equipado, vazio caso contrário.
  - Borda do slot adota a cor do tier.
- **Tipo TS:**
  - `EquipmentTier` em `types/afk.ts`.
  - 8 campos de equipamento adicionados ao `UserHero` interface.
  - `EQUIPMENT_TIERS`, `EQUIPMENT_TIER_LABEL`, `EQUIPMENT_TIER_BADGE`, `EQUIPMENT_LEVEL_MAX` em `lib/afk.ts`.
- **Testes:**
  - `PowerCalculatorTest`: 5 novos cenários (zero quando null, fórmula T1-T6 Lv1-20, clamp acima de 20, soma 4 slots, integração forUserHero).
  - `UserHeroArtifactTest`: 4 novos cenários (equipa 4 peças, valida tier inválido, valida level >20, normaliza tier sem level).
  - **186 testes verdes** no projeto (era 177, +9).

---

### ✅ Fase 16 — Auth + Dashboard com tema AFK, login como tela inicial, i18n PT-BR

Redesign completo do entry point da aplicação para refletir a identidade visual do jogo, e instalação de pacote de localização PT-BR para mensagens nativas do Laravel (validação, auth, reset).

- **Rota raiz redireciona** (`routes/web.php`):
  - `GET /` → `redirect()->route(auth()->check() ? 'dashboard' : 'login')`
  - Nome `home` preservado (referenciado em layouts e links)
  - Removido import `Inertia` (rota não renderiza mais closure)

- **Imagens oficiais** baixadas para `public/images/auth/`:
  - `athalia-teaser.png` (1623×2087, ~500KB) — Athalia Awakened, key art oficial
  - `afk-arena-logo.png` (941×429, ~78KB) — logo oficial AFK Arena
  - Fonte: Fandom Wiki via API MediaWiki (mesma CDN `static.wikia.nocookie.net` já usada para portraits)

- **`layouts/auth/auth-afk-layout.tsx` (novo):**
  - Split 2 colunas (mobile: 1 col; lg+: split 1.1fr / 1fr)
  - **Painel esquerdo:** Athalia teaser de fundo com overlay gradient escuro. Logo no topo com glow âmbar. Tagline "Domine cada formação, em todos os modos.". Badges coloridos das 8 facções via `FACTION_THEME`.
  - **Painel direito:** card central border âmbar, gradient zinc-900/950/black, shadow dourado glow. Título com `bg-clip-text` gradient âmbar. Footer "AFK Builder · Não-oficial · Arte © Lilith Games".
  - Background ambient: 3 radial gradients (âmbar/roxo/rosa) com `blur-3xl` + `opacity-40` — clima místico in-game.

- **`auth-layout.tsx`** (alias): trocou template interno de `auth-simple-layout` para `auth-afk-layout`. Todas as páginas que usam `@/layouts/auth-layout` herdam o novo visual.

- **Páginas reescritas em PT-BR** (4):
  - `login.tsx` — "Entre na sua conta" / "Acesse seu roster e suas formações". Inputs com tema (border âmbar, fundo black/40, focus dourado). Botão "Entrar" com gradient âmbar/yellow + glow + ícone `Sparkles`. Links "Esqueci a senha" e "Crie a sua".
  - `register.tsx` — "Crie sua conta" / "Comece a montar suas formações em segundos".
  - `forgot-password.tsx` — "Esqueci minha senha". Ícone `Mail`.
  - `reset-password.tsx` — "Redefinir senha". Ícone `ShieldCheck`. E-mail readonly em opacity-60.

- **`DashboardController` (novo, `app/Http/Controllers/`):**
  - `index(Request)` retorna:
    - `stats`: `roster_total`, `roster_ascended` (asc≥10), `roster_awakened` (SQL: `LOWER(REPLACE(heroes.name, ' ', '-')) <> heroes.slug`), `catalog_total` (ativos não-pending), `factions_count`, `formations_total`
    - `rosterByFaction`: `GROUP BY` facção do roster com `code`, `name_pt`, `total` (8 entradas)
    - `recentFormations`: 3 últimas formações ordenadas por `updated_at DESC` com challenge label e `diffForHumans`
  - `dashboard` migrou de closure pra `[DashboardController::class, 'index']` em `routes/web.php`.

- **`pages/dashboard.tsx` redesenhado:**
  - **Hero card:** gradient zinc-900 → black com radial overlay âmbar/roxo. Badge "Bem-vindo de volta", saudação `auth.user.name.split(' ')[0]`. CTAs "Nova formação" (gradient dourado) e "Meu roster" (outline).
  - **4 stat cards:** Roster (com `% do catálogo` derivado), Ascendidos, Despertos, Formações. Cores temáticas âmbar/yellow/purple/rose.
  - **Roster por facção:** grid 2×4 (sm: 4 cols) com 8 facções usando `FACTION_THEME[code]` para cores coerentes (cardGradient, cardBorder, headerText). Total + label "herói/heróis".
  - **3 atalhos em coluna:** Catálogo de heróis, Minhas formações, Guias — cada um com ícone temático e seta animada no hover.
  - **Formações recentes:** grid 1-3 cols com badges Real/Alvo (emerald/sky), challenge type + nº equipes, "Editado há X". Empty state com ícone `Sword` e CTA pra criar a primeira.

- **i18n PT-BR via lib `lucascudo/laravel-pt-br-localization v3.0.4`:**
  - `composer require --dev lucascudo/laravel-pt-br-localization`
  - `php artisan lang:publish` (cria estrutura `lang/`)
  - `php artisan vendor:publish --tag=laravel-pt-br-localization` (publica `lang/pt_BR/{auth,validation,passwords,pagination}.php` + `lang/pt_BR.json`)
  - `APP_LOCALE=pt_BR` já estava no `.env` — Laravel passa a usar essas traduções automaticamente em validação, auth e reset
  - Sem mudanças de código: mensagens como "Estas credenciais não correspondem aos nossos registros." aparecem nativamente

- **Validação:**
  - Build Vite: `dashboard-FIQK23h-.js` 9.79KB / `auth-layout-DsTfHbyB.js` 4.16KB / `login-CMaVS9dK.js` 3.42KB. Build em **4.45s**.
  - **187 testes verdes** no projeto (era 186, +1 — `DashboardTest` continua passando contra o controller real).
  - Smoke test curl: `GET /` → 302 → `/login`, `GET /login` → 200, `GET /dashboard` (guest) → 302 → `/login`.
  - Type-check: zero novos erros em arquivos tocados (os ~14 erros pré-existentes do starter kit em `useForm<X>` constraint seguem fora de escopo).

---

### ✅ Fase 17 — Sidebar customizado, PageShell padrão, dark global

Padronização visual da aplicação inteira: sidebar com identidade própria, layout reutilizável para páginas internas, e tema escuro AFK global.

- **Sidebar com identidade AFK:**
  - **`app-logo-icon.tsx`** reescrito como SVG: escudo hexagonal com fundo `#0a0613`, borda gradient dourada (amarelo→âmbar→bronze), letra **A** central com gradient claro→âmbar e gema dourada no topo. Estilo RPG/fantasy.
  - **`app-logo.tsx`** atualizado: texto **"AFK Builder"** com tracking-wide, subtítulo **"COMPANION APP"** em letter-spacing largo. Removido `bg-sidebar-primary` (ícone tem fundo escuro próprio). Drop-shadow âmbar discreto.
  - **Footer items** em `app-sidebar.tsx` e `app-header.tsx`: trocados links Repository/Documentation do starter kit Laravel por **Repositório/Documentação** apontando para `github.com/Chirlanio/afk-builder` e `docs/IMPLEMENTATION.md`.

- **`<PageShell>` (`resources/js/components/page-shell.tsx`)** — componente reutilizável de layout de página:
  - **Props:** `title` (obrigatório), `description?`, `icon?` (LucideIcon), `actions?`, `children`, `width?: 'full' | 'narrow'` (default `full`), `contentClassName?`.
  - **Header:** ícone em quadrado âmbar com border âmbar e shadow glow dourado (`shadow-[0_0_18px_-4px_rgba(251,191,36,0.4)]`), título 2xl/3xl âmbar `text-amber-50 font-bold tracking-tight`, descrição muted `text-amber-100/60`, ações alinhadas à direita.
  - **Container:** `flex flex-1 flex-col gap-6 p-4 md:p-6`. Width `narrow` aplica `mx-auto w-full max-w-3xl`.

- **Dark global (tema AFK forçado):**
  - **`use-appearance.tsx` simplificado:** `initializeTheme()` sempre adiciona `dark` ao `<html>`. `useAppearance()` mantém shape compatível mas é no-op. Removido `localStorage` lookup, `prefers-color-scheme` listener.
  - **`app.css` `.dark` rebrand:** trocada paleta cinza shadcn por **paleta AFK**:
    - `--background: hsl(258, 56%, 5%)` (preto-roxo profundo, próximo ao `#0a0613` do auth)
    - `--foreground: hsl(48, 96%, 95%)` (âmbar warm em vez de branco neutro)
    - `--primary` / `--ring: hsl(43, 96%, 56%)` (âmbar dourado)
    - `--card`, `--popover`, `--secondary`, `--muted`, `--accent`, `--border`, `--input`: tons coerentes em `hsl(258, ...)`
    - `--sidebar-*`: paleta sidebar coerente com background mais profundo `hsl(258, 40%, 6%)`
    - `--chart-1` agora é âmbar dourado (em vez de azul)

- **12 páginas migradas para PageShell:**
  | Página | Width | Notas |
  |---|---|---|
  | `guides/index` | full | título "Guias do jogo" |
  | `guides/show` | narrow | título dinâmico do guia, action "Voltar" |
  | `heroes/index` | full | "Catálogo de Heróis", search/filtros como children |
  | `formations/index` | full | actions: "Comparar selecionadas" + "Nova formação" |
  | `formations/create` | narrow | form em card |
  | `formations/compare` | full | action "Voltar" |
  | `admin/heroes/pending` | full | action "Adicionar manualmente" |
  | `admin/collections/index` | full | action "Nova coleção" |
  | `admin/beasts/index` | full | action "Novo beast" |
  | `settings/profile` | (via SettingsLayout) | traduzido PT-BR |
  | `settings/password` | (via SettingsLayout) | traduzido PT-BR |
  | `settings/appearance` | (via SettingsLayout) | virou página informativa "Tema AFK fixo" |

- **Não migrado (intencional):**
  - `dashboard.tsx` — mantém hero card customizado de boas-vindas (visual estrela da home com saudação personalizada)
  - `formations/edit.tsx` — header customizado complexo (Input editável + badges + beast selector + 4 botões); só ajustado padding/gap pra `p-4 md:p-6 gap-6` consistente com PageShell

- **`SettingsLayout` refatorado:** agora usa PageShell internamente como wrapper externo (`title: "Configurações"`, `icon: Settings`). Sidebar interno mantém nav lateral mas com labels PT-BR (Perfil/Senha). Item "Aparência" removido do nav (tema é fixo).

- **Traduções extras PT-BR:**
  - `delete-user.tsx`: "Delete account" → "Apagar conta", "Warning" → "Atenção", etc.
  - Breadcrumbs settings: "Profile settings" → "Perfil", "Password settings" → "Senha".
  - `settings/appearance.tsx` virou página informativa: "O AFK Builder usa um tema escuro fixo, inspirado nas noites de Esperia" com badge "Tema AFK ativo".

- **Validação:**
  - Build Vite: 3.87s. PageShell isolado em `page-shell` chunk pequeno.
  - **187 testes verdes** (sem regressão).
  - Type-check: zero novos erros nos arquivos tocados (os pré-existentes em `useForm<X>` e `localized()` continuam fora de escopo).

---

### ✅ Fase 18 — Mobile-first responsive (Etapas 1 e 2)

Adaptação da app pra funcionar bem em telas estreitas (375px+, iPhone SE como baseline). Dividida em duas etapas commitadas separadamente.

#### ✅ 18.1 — Quick wins (DialogContent + PageShell)

Mudanças globais que afetam todos os modais e todas as páginas migradas pra PageShell:

- **`DialogContent`** (`components/ui/dialog.tsx`):
  - `w-[calc(100vw-1rem)]` em mobile (8px gutter cada lado em vez de quase grudado), `sm:w-full`
  - `max-h-[calc(100dvh-1rem)] sm:max-h-[calc(100dvh-2rem)]` + `overflow-y-auto` — modal alto não vaza, scroll interno garantido. Usa `100dvh` (dynamic viewport) que respeita a barra do Safari iOS aparecendo/sumindo.
  - Padding `p-4 sm:p-6` (mobile economiza largura útil)
  - Botão close 36×36px (`size-9`) — touch-friendly em vez de área diminuta

- **`PageShell`** (afeta as 12 páginas migradas):
  - Padding/gap responsivos: `p-3 sm:p-4 md:p-6`, `gap-4 sm:gap-6`
  - Header empilha em mobile (`flex-col`), vira row em `md:` (≥768px)
  - Ícone escala `size-10 sm:size-11` (40px → 44px)
  - Title escala `text-xl sm:text-2xl md:text-3xl`
  - Description `text-xs sm:text-sm`
  - **Actions full-width inteligente em mobile**: `[&>*]:flex-1` faz cada filho crescer ao quebrar linha. Em `sm:` vira `flex-initial` (tamanho natural); `md:flex-nowrap` evita quebra desnecessária.

#### ✅ 18.2 — Editor de formações mobile

`formations/edit.tsx` é o lugar mais doloroso em mobile (DnD com pool lateral 320px). Refatorado:

- **Header em 2 linhas:**
  - Linha 1 — nome (Input editável) + ações.
    - Desktop (`md+`): 4 botões inline (Histórico/Compartilhar/Sugerir/Salvar)
    - Mobile: só `Salvar` (CTA) + ícone `MoreHorizontal` → `DropdownMenu` com as outras 3 ações
  - Linha 2 — badges (challenge type, modo, PvP, único) + beast selector
  - Indicador "Alterações não salvas" migra pra linha própria em mobile

- **Pool de heróis:**
  - **Desktop (`lg+`):** coluna lateral 320px, como antes
  - **Mobile/tablet:** Sheet bottom (`h-[80dvh]`) acessível via FAB fixo no bottom-right (gradient âmbar com glow `shadow-[0_0_24px_-4px_rgba(251,191,36,0.7)]`)
  - Sheet **fecha automaticamente em `onDragStart`** (`setPoolSheetOpen(false)` dentro do `handleDragStart`) — usuário revela os slots-alvo enquanto arrasta o herói flutuante
  - `pb-20 lg:pb-0` nas equipes evita que o último time fique escondido atrás do FAB

- **Touch ergonomia:** FAB 56×56px (`size-14`), botão close de modal 36×36px, dropdown items com gap-2.

- **Não testado em device físico** (você precisa) — validado em DevTools mobile emulation.

- **Validação:** Build Vite 3.96s. **187 testes verdes** (sem regressão).

---

### ✅ Fase 19 — Admin de sincronizações (`/admin/sync`) + dev script

Permite que admins disparem comandos artisan de sync via UI sem precisar de SSH. Substitui o caminho atual de "abrir terminal e rodar `php artisan heroes:sync --initial`".

- **Migration `command_runs`:** id, command (varchar 64), args (json nullable), status (varchar 16, default 'pending'), started_at, finished_at, exit_code (smallint), output (mediumText, capacity ~16MB mas truncado em 64KB pelo job), user_id (FK nullable), timestamps. Index composto `(status, created_at)` + index `command`.

- **`App\Models\CommandRun`:**
  - Constants: `STATUS_PENDING`, `STATUS_RUNNING`, `STATUS_COMPLETED`, `STATUS_FAILED`; `ACTIVE_STATUSES = [pending, running]`; `OUTPUT_MAX_BYTES = 65536`
  - Casts `args => array` (JSON), `started_at`/`finished_at` => datetime
  - Helpers `isActive()`, `durationSeconds()`
  - Relação `user()`

- **`config/sync_commands.php` — whitelist com 8 comandos:**
  - `heroes:sync` (initial bool, dry-run bool, limit int 1-500, page string regex)
  - `heroes:resolve-images` (force bool, dry-run bool, size int 100-1200)
  - `heroes:enrich` (force bool, limit int, slug string)
  - `heroes:sync-furniture` (dry-run)
  - `heroes:sync-types` (force, limit, slug)
  - `artifacts:sync` (dry-run)
  - `beasts:sync` (dry-run)
  - `content:translate` (type enum: all/lore/abilities/furniture/guides; limit; slug; force)
  - Cada entrada tem label PT-BR, descrição, ícone (mapeado pra Lucide no frontend) e options tipadas com validações.

- **`App\Jobs\RunArtisanCommandJob`** (queueable, timeout 1800s, tries 1):
  - Recebe `commandRunId`. Busca o CommandRun, atualiza status `running` + `started_at`.
  - Captura stdout via `Symfony\Component\Console\Output\BufferedOutput`.
  - `Artisan::call($command, $args, $output)` retorna exit code.
  - Atualiza status `completed` (exit=0) ou `failed` (exit≠0), `finished_at`, `exit_code`, `output` (truncado em 64KB com sufixo `[...output truncado]`).
  - Captura exception, persiste stderr + traço, loga no Laravel logger e re-throw (job vai pra `failed_jobs`).

- **`App\Http\Controllers\Admin\SyncCommandController`:**
  - `index` — Inertia render com whitelist (já mapeada com options expandidas), últimas 50 execuções e `queueDriver` (alerta se ≠ database).
  - `runs` (GET JSON) — para polling do frontend
  - `show` (GET JSON) — detalhes do run incluindo `output` completo
  - `store` (POST 202) — valida command está na whitelist, valida args via `validateArgs()` que despacha por type:
    - `bool`: presença = true
    - `int`: numeric + min/max do schema
    - `string`: trim + regex se schema tem `pattern`
    - `enum`: in array de keys do `options`
    - Retorna args já formatados como `['--key' => value]` pra `Artisan::call`
    - Cria CommandRun em transação, dispatcha `RunArtisanCommandJob` async
  - 4 rotas em `/admin/sync*` sob `can:manage-heroes`

- **Frontend `pages/admin/sync/index.tsx`:**
  - Cards dos 8 comandos: ícone (mapping `Users → Image → BookOpen → Sofa → Dumbbell → Sparkles → PawPrint → Languages → Terminal`), label, signature em `font-mono`, descrição, expand pra options (Checkbox/Input/Select por tipo)
  - Tabela responsiva (com `overflow-x-auto min-w-[700px]`) das últimas 50 execuções: comando + args formatados (`key=value`, `bool=✓`), badge de status colorido (pending/running/completed/failed), iniciado, duração, executor, botão "Ver"
  - Modal com output em `<pre>` estilo terminal (`bg-black/60 text-emerald-200 max-h-[60vh]`)
  - **Polling automático:** `useEffect` cria `setInterval` de 3s enquanto há run ativo (`pending` ou `running`), faz GET `/admin/sync/runs` e atualiza state. Para automaticamente quando todos os runs estão done.
  - Banner amarelo se `queueDriver !== 'database'` ou de qualquer forma alertando que `php artisan queue:work` precisa estar rodando.

- **Sidebar admin:** novo item "Sincronizações" com ícone `RefreshCw` apontando para `/admin/sync`.

#### ✅ 19.1 — Script `start.ps1`/`start.cmd` para orquestrar dev

Para resolver "quais terminais preciso abrir e quais comandos rodar pra começar a desenvolver":

- **`start.ps1`** (raiz do projeto):
  - Valida pré-requisitos (PHP/Composer/Node/npm no PATH, `.env` existe, `vendor/` + `node_modules/` presentes, banco acessível via `migrate:status`, número de migrations Pending)
  - Flags: `-SkipChecks` (pula validação em re-runs), `-Migrate` (aplica migrations pendentes antes de subir)
  - Chama `composer dev` que orquestra 4 processos via `concurrently` (já existia no `composer.json` do starter, agora documentado):
    - `php artisan serve` (backend Laravel)
    - `php artisan queue:listen --tries=1` (worker da fila — necessário para `/admin/sync`)
    - `php artisan pail` (logs em tempo real)
    - `npm run dev` (Vite dev server com HMR)
  - Saída colorida: `Write-Step` cyan, `Write-Ok` green, `Write-Warn` yellow, `Write-Fail` red

- **`start.cmd`** — wrapper batch para clique-duplo no Explorer (passa `-NoProfile -ExecutionPolicy Bypass` ao PowerShell). Aceita os mesmos argumentos.

- **README atualizado:** nova seção "Rodar em desenvolvimento" com o atalho do `start.ps1` recomendado e fallback de "terminais separados" (3 terminais: serve + queue:work + npm run dev).

#### Validação Fase 19

- ✅ Build Vite 3.94s
- ✅ 187 testes verdes (sem regressão)
- ✅ Migration aplicada
- ✅ 4 rotas registradas em `/admin/sync*`
- ✅ Sintaxe PowerShell do `start.ps1` validada
- ✅ Todos os 11 checks do script passam no estado atual do projeto
- ❌ **Pipeline completo (`composer dev`) não foi rodado** — fica em foreground, requer terminal interativo. Validado em partes: `migrate:status` ok, sintaxe PowerShell ok, 187 testes ok.

---

- **Testes:** 4 cenários (`GuideTest`) — guest redireciona, index agrupa por categoria com counts, show renderiza com categoryLabel, 404 em slug inexistente. **98 testes verdes** no projeto.
- Helper `tierFromAscension(ascensionValue)` em `lib/afk.ts`: 0-3 → `common`, 4-9 → `legendary`, 10+ → `ascended`.
- `hero-card.tsx`: quando `userHero` está presente, badge mostra o tier ATINGIDO (estado real do user). Sem roster, mostra o max potencial do herói.
- Mesma lógica aplicada na página `heroes/show.tsx`.
- Resultado: badge "Ascendido" só aparece em heróis cujo `ascension_value >= 10` no roster do user.
#### ✅ 6.7 — Investigar heróis ignorados na wiki (concluído)

Inspeção página por página dos 12 heróis que o sync ignorava:

**Parser corrigido** (recuperam-se automaticamente no próximo sync):
- **Daimon, Arden** — usavam `nickname=` no lugar de `name=`. Adicionado fallback em `WikitextHeroParser`. Bonus: também adicionado fallback `role` → `role1` (mais comum nas páginas modernas).

**Wiki sem dado essencial** (parser não tem como inferir; precisa entrada manual via `/admin/heroes/create`):
- **Sem `class=`:** Saveas, Angelo, Ankhira, Dreaf, Eletha, Golus, Gourgue, Draconis Archer, Draconis Warrior. Wiki tem apenas `type=` (Strength/Intelligence/Agility) e `role1/role2`, que não mapeiam 1:1 para Tank/Warrior/Ranger/Mage/Support.
- **Sem `rarity=`:** Bronn (resto dos campos presentes).
- **Página inexistente:** Twins of Glory (título não existe no Fandom como esse texto — `Elijah & Lailah` cobre o conteúdo dos "Celestial Twins").

**Arden ainda ignorado** (não-fix:) Arden tem `nickname=` (que o fix novo agora detecta) MAS também não tem `class=` no wiki, então cai na categoria manual.

**Testes:** novo `tests/Unit/HeroCatalogSync/WikitextHeroParserTest.php` com 11 cenários cobrindo o parser direto: template padrão, fallback name→nickname, fallback role→role1, ausência de class/rarity, plurais de facção, rarity intermediário→legendary, extração de imagem (simples + gallery), templates aninhados. Total **84 testes verdes** no projeto.

---

## Convenções importantes

### Slug de heróis

Slug é derivado do **page title da wiki** (`Str::slug($pageTitle)`), **não** do campo `name` do template `{{Hero}}`. Motivo: variantes Awakened (Shemira → "Shemira - The Soulreaver") têm o mesmo `name = Shemira` no template. Slugar pelo `name` causaria colisão. Slugar pelo título: `shemira` vs `shemira-the-soulreaver` — separados.

### Detecção de Awakened

`is_awakened = Str::slug($hero->name) !== $hero->slug`. Computado on-the-fly no `HeroController` e no `FormationController::buildHeroPool`. Não persistido no DB (deriva do dado existente). Conhecido falso positivo: **Ukyo** (slug "ukyo", name "Ukyo Tachibana" — wiki tem dado inconsistente). 1 falso positivo em ~26 awakened reais.

### Idioma na UI

Tudo em PT-BR. Glossário oficial vem do jogo BR (ver Fase 3 acima). Se aparecer um termo novo, **perguntar** antes de assumir uma tradução — o cliente BR usa termos específicos que não são traduções literais.

### Seeders idempotentes

Todos os seeders usam `updateOrCreate(['code' => ...], ...)`, então re-executar não duplica e atualiza valores. Para mudar um label PT-BR, basta editar o seeder e rodar `php artisan db:seed --class=FactionSeeder` (ou o que for relevante).

### Frontend ↔ Backend de labels

Os labels PT-BR vivem em **dois lugares** que devem coincidir:
1. `database/seeders/*.php` → coluna `name_pt` no DB → carregada pelo controller → usada em headers/filtros (`{faction.name_pt}`)
2. `resources/js/lib/afk.ts` → `FACTION_THEME[code].label`, `CLASS_LABEL`, `TIER_THEME[code].label` → usada em componentes que não recebem o objeto completo via props (ex: HeroChip recebe só o code)

Ao mudar uma tradução, atualizar os dois.

### Faction bonus logic

No editor, `team-row.tsx::computeFactionBonus` calcula o bônus ao vivo:
- 5x mesma facção → "5x [Facção] ativo"
- 3 + 2 (3 de A, 2 de B) → "3+2: A + B"
- Celestiais contam como wildcard tanto pra completar 5-stack quanto pra 3+2
- Implementação simplificada — refinar com regras de Hypogeans/Dimensionals quando entrar no algoritmo da Fase 5

---

## Issues conhecidos

| # | Severidade | Descrição |
|---|---|---|
| 1 | baixa | Hero "Ukyo" detectado como awakened (false positive — slug "ukyo" mas name "Ukyo Tachibana" na wiki) |
| 2 | baixa | 9 heróis ignorados pelo sync por `class=''` na wiki (Saveas, Angelo, Ankhira, Dreaf, Eletha, Golus, Gourgue, Draconis Archer, Draconis Warrior) — entrada manual via `/admin/heroes/create`. Bronn idem por `rarity=''`. Daimon e Arden recuperados após parser fix da 6.7. |
| 3 | informativa | Adrian & Elyse usa só a imagem do Adrian (escolha da wiki como "page representative image", não bug nosso) |
| ~~4~~ | ~~baixa~~ | ~~UI de aprovação `pending_review`~~ — resolvido na 6.1. |
| 5 | melhoria | Mobile: drag-and-drop funciona via TouchSensor mas UX não foi otimizada para touch (pool fica oculto em telas < lg) |
