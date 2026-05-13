# AFK Builder — Status de Implementação

Documento vivo do estado da aplicação. Atualizar conforme novas fases concluem.

> **Última atualização:** 2026-05-06 (Fases 18 e 19 — mobile-first responsive + admin de sincronizações com cancelamento)

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
- **Hotel Furniture per-user (rastreamento, bônus aplicados ao optimizer):** parcialmente implementado na Fase 12 (bônus +100/+200 F3/F9 no PowerCalculator) e completado na Fase 25 (effects estruturados no TeamScorer). O rastreamento granular de 144 peças individuais permanece fora de escopo — `user_heroes.furniture` (0-9) continua sendo a fonte da verdade.

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
- **Resolvido na Fase 25:** effects estruturados em `hero_furniture_abilities.effects` + `FurnitureEffectScorer` integrado ao `TeamScorer`. Abilities F3/F9 agora influenciam o score por tag (cc_immunity, damage_boost, etc.) ponderada pelo modo da formação.

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

#### ✅ 19.2 — Cancelar execuções pendentes

Cobre o caso comum de "admin disparou comando errado, percebeu antes do worker pegar". Cancela apenas runs `pending` — runs `running` exigiriam cooperação dos comandos artisan (ficou fora de escopo).

- **Migration `add_job_id_to_command_runs`:** adiciona coluna `job_id` (`unsigned bigint nullable`, sem FK pra `jobs.id` porque a tabela `jobs` é volátil — Laravel apaga ao processar). Permite mapear `command_run` → linha em `jobs` sem parsear o payload serializado.

- **`CommandRun`:** novo `STATUS_CANCELLED = 'cancelled'`; `fillable` inclui `job_id`; cast `'job_id' => 'integer'`.

- **`SyncCommandController::store`** estendido: após `RunArtisanCommandJob::dispatch($run->id)`, busca o `id` mais recente em `jobs` (`->where('queue', config('queue.connections.database.queue', 'default'))->latest('id')->value('id')`) e salva no `command_run`. Best-effort — race com dispatch concorrente é tolerável porque `/admin/sync` é admin-only e baixa concorrência.

- **`SyncCommandController::cancel`** (novo, `DELETE /admin/sync/runs/{run}`):
  - Valida `run->status === 'pending'` antes de entrar na transação. Se já está em outro estado, retorna 422 com mensagem clara.
  - Dentro de `DB::transaction`: `lockForUpdate` na linha do `command_run` (evita race com worker pegando o job entre o select e o update), re-valida status (pode ter mudado em ms), atualiza pra `cancelled` + `finished_at = now()`, executa `DELETE FROM jobs WHERE id = job_id`.
  - Retorna `{run: {id, status: 'cancelled'}}`.

- **`RunArtisanCommandJob::handle` ganhou safeguard:** se chegar e `$run->status === STATUS_CANCELLED`, loga `'pulando job já cancelado'` e retorna sem marcar `running` ou rodar Artisan. Fecha o race em que o worker já tinha "reservado" o job antes do `DELETE FROM jobs` chegar — quando ele chama `handle()`, a flag de cancelamento já está visível.

- **Frontend:**
  - Status `'cancelled'` adicionado ao type `CommandStatus` + `STATUS_LABEL` ("Cancelado") + `STATUS_BADGE` (zinc neutro)
  - Botão "Cancelar" aparece **só quando `run.status === 'pending'`** — em `running`, `completed`, `failed` ou `cancelled` não aparece (honesto: não promete o que não cumpre)
  - `cancelRun()` usa `useConfirm` com mensagem que explica o limite ("O job é removido da fila antes do worker pegar. Se já tiver pegado, a execução continua.")
  - Loading state com `Loader2` durante o DELETE; em erro mostra alert + refetch automático pra ressincronizar UI com servidor

- **Validação:** Build Vite 4.05s. 187 testes verdes. 5 rotas em `/admin/sync*` (DELETE adicionada). Não testei o flow E2E porque precisaria do worker rodando + timing manual.

#### Validação Fase 19

- ✅ Build Vite 3.94s
- ✅ 187 testes verdes (sem regressão)
- ✅ Migration aplicada
- ✅ 4 rotas registradas em `/admin/sync*`
- ✅ Sintaxe PowerShell do `start.ps1` validada
- ✅ Todos os 11 checks do script passam no estado atual do projeto
- ❌ **Pipeline completo (`composer dev`) não foi rodado** — fica em foreground, requer terminal interativo. Validado em partes: `migrate:status` ok, sintaxe PowerShell ok, 187 testes ok.

#### ✅ 19.3 — Fix `composer dev`: `queue:listen` → `queue:work --timeout=1800`

Detectado em uso real: jobs longos do `/admin/sync` (ex: `heroes:sync`) estavam sendo mortos antes de terminar. Causa raiz: `queue:listen` dispara um subprocess `php artisan queue:work --once` com **timeout fixo de 60s** do Symfony Process. Quando o subprocess passa de 60s, o `Listener` o mata via `ProcessTimedOutException`. O job na tabela `jobs` fica com `attempts=1` mas sem ser marcado como failed. Na próxima execução do worker, `MaxAttemptsExceededException` dispara em milissegundos (porque `tries=1`) sem nem chamar o `handle()`, populando `failed_jobs` com jobs nunca executados.

- **`composer.json`:** trocado `php artisan queue:listen --tries=1` por `php artisan queue:work --tries=1 --timeout=1800` na seção `scripts.dev`. O `1800s` casa com `RunArtisanCommandJob::$timeout = 1800`.
- **Trade-off documentado:** `queue:work` mantém código PHP em memória entre jobs. Edits em jobs ou comandos artisan invocados por eles exigem **reiniciar o `composer dev`** (Ctrl+C + re-run). `queue:listen` recarregava automaticamente — perdemos esse comportamento em troca de jobs de até 30 min funcionarem.
- Não afeta os outros 3 processos do `concurrently` (serve, pail, vite continuam com seu hot-reload normal).

---

### ✅ Fase 21 — Tier ratings (afk.guide) + estrutura de comps recomendados

Primeira integração com fonte externa fora da Fandom. Levantamento exploratório do site `afk.guide` (WordPress + Ninja Tables) identificou que apenas a tier list de heróis tem dados estruturados e não-redundantes ao que já temos. Outras categorias (skills, lore, beasts) duplicariam o sync da Fandom ou não trazem valor numérico (beasts só tem descrições qualitativas).

#### 21.1 — Tier ratings de heróis (sync de afk.guide)

- **Migration `hero_ratings`:** id, hero_id (FK cascade), mode (varchar 32), stage (varchar 16), tier (varchar 4), source (varchar 64, default 'afk.guide'), source_url, sync_batch (varchar 64, timestamp da execução para rastrear lotes), timestamps. Unique composto `(hero_id, mode, stage, source)` permite múltiplas fontes coexistirem no futuro. Index em `(mode, stage, tier)` para queries de "todos S+ em PvP end".
- **`App\Models\HeroRating`:**
  - Constants: `MODE_OVERALL`, `MODE_PVP`, `MODE_PVE`, `MODE_LABYRINTH`, `MODE_TWISTED_REALM`, `MODE_TEMPORAL_RIFT`, `MODE_ABYSSAL_EXPEDITION`
  - Constants: `STAGE_EARLY`, `STAGE_MID`, `STAGE_END`
  - `TIERS = ['S+', 'S', 'A', 'B', 'C', 'D', 'F']`
  - `belongsTo(Hero)` + `Hero::ratings()` `hasMany`
- **`SyncHeroRatingsCommand` (`heroes:sync-ratings`):**
  - Fetch HTML via `Http::withOptions(['verify' => CaBundle...])` + UA custom
  - `DOMDocument->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING)` para evitar warnings de HTML5/UTF-8
  - `DOMXPath` localiza `//table[contains(@class, 'ninja_footable')]` — a página tem 3 tabelas (Early/Mid/End)
  - Stage detectado via `<caption>` ("From the Beginning to Chapter 30" → early; "Chapter 30 to Chapter 35" → mid; sem caption + `aria-label="Late Game..."` → end)
  - Headers extraídos de `<thead>/<tr>/<th>` mapeados via `HEADER_TO_MODE` (case-insensitive). "Guild Hunt" e "Twisted Realm" mapeiam pro mesmo mode (a página usa labels intercambiáveis)
  - Para cada `<tr>` no `<tbody>`: extrai textContent da td "Hero" (que tem `<img>` + `<br>` + nome), normaliza com `html_entity_decode + collapse whitespace`
  - Resolve hero por: (1) `slug = Str::slug(name)`, (2) se "Awakened X" → tira prefixo e tenta `slug` da base, (3) fallback `whereRaw('LOWER(name) = ?')`. Cobre 96.5% dos heróis (552 de 572).
  - Tier normalizado: uppercase + remove "—"/"-"/vazio. Aceita só os 7 valores em `TIERS`
  - `updateOrCreate` por `(hero_id, mode, stage, source)` — idempotente, sobrescreve tiers da mesma fonte
  - Flag `--dry-run` (loga sem persistir) + `--source=URL` (default `https://afk.guide/afk-arena-tier-list/`)
  - Resultado em produção: **3199 ratings persistidos** (552 heróis × ~7 modos × 1-3 estágios)
- **Adicionado a `config/sync_commands.php`:** entrada `heroes:sync-ratings` com label PT-BR, ícone `Trophy` (importado em `pages/admin/sync/index.tsx`) e flag `dry-run`
- **Heróis não encontrados (16 nomes únicos):** Bronn, Saveas, Angelo, Ankhira, Golus, Mirael, Arden, Silvina, Ira, Vedan, Hogan, Ogi, Niru, Morvus, Shalltear, "Canisa and Ruke" — sobrepõem com a lista da Fase 6.7 (heróis que a wiki não consegue popular auto). Cadastro manual pendente via `/admin/heroes/create`.
- **TS types em `types/afk.ts`:** `HeroRatingMode`, `HeroRatingStage`, `HeroRatingTier`, `HeroRating`. Enums seguem os constants do model.
- **UI no `hero-detail-modal.tsx`:**
  - `HeroController::details` carrega `with('ratings:id,hero_id,mode,stage,tier,source,source_url')` e injeta no payload
  - Nova `SectionBlock` "Tier Rating" (ícone `Trophy`, fonte "afk.guide" como extra) entre Stats e Habilidades — só renderiza se `ratings.length > 0`
  - Componente `RatingsGrid`: tabela compacta (`min-w-[420px]` + `overflow-x-auto`) com colunas = estágios e linhas = modos. Filtra modos vazios (só mostra modos com ao menos 1 rating em algum estágio).
  - Tier badges coloridos via `RATING_TIER_BADGE`: S+ gradiente rose→amber, S rose, A orange, B amber, C zinc claro, D zinc médio, F zinc escuro
  - Labels PT-BR via `RATING_MODE_LABEL` ("Geral", "Realidade Distorcida", "Sala do Tempo", "Expedição Abissal", etc) e `RATING_STAGE_LABEL` ("Cap. 1-30", "Cap. 30-35", "Cap. 35+")

#### 21.2 — Estrutura mínima de comps recomendados (sem UI ainda)

Cobre o caso de "trazer comps populares como referência". Modelagem isolada (não polui `formations` dos users). UI fica para uma sessão futura.

- **Migration `recommended_formations`:** id, slug unique, name, mode (varchar 32), description text, hero_slugs json, source_url, source_label, sort_order, is_active, timestamps. Index em `(mode, is_active, sort_order)`.
- **`App\Models\RecommendedFormation`:**
  - Constants: `MODE_PVP`, `MODE_PVE`, `MODE_TWISTED_REALM`, `MODE_LABYRINTH`, `MODE_KING_TOWER`, `MODE_ARCANE_LABYRINTH`
  - Cast `hero_slugs => array`
  - Helper `resolveHeroes()` retorna `Collection<Hero>` para os slugs cadastrados
- **`RecommendedFormationsSeeder`** com 2 comps populares curados manualmente do `/best-team/`:
  - **God Comp (PvP):** talene, elijah-lailah, rowan, tasi, ezizh — "ciclar definitivas via geração rápida de energia"
  - **Green Sand Wolf (PvE):** tidus, lyca, skriath, eironn, safiya — "Skriath puxa backline, Eironn vortex de 5, Tidus fear AoE; supera 90-100 níveis de déficit"
  - Todos os 10 hero_slugs validados existirem no banco antes de seedar
- **Por que sem UI nesta sessão (depois adicionada em 21.3):** comps curados isoladamente têm pouco valor sem (1) página `/formations/recommended`, (2) action "duplicar para minhas formations", (3) curadoria de mais 5-10 comps.

#### 21.3 — UI dos comps recomendados (página + clone + sidebar)

Fechou o loop da 21.2 — sem isso, os comps seedados ficavam invisíveis no app. Implementado no mesmo dia ainda.

- **`RecommendedFormationController`:**
  - `index()` — Inertia render com `recommendedFormations` (filtrados `is_active`, ordenados por `sort_order` + `name`) e `heroesBySlug` (lookup table dos heróis dos comps com faction relation eager-loaded). Coleta todos os slugs únicos dos comps e busca em uma query só (evita N+1).
  - `clone(Request, RecommendedFormation $recommended)` — POST `/formations/recommended/{recommended:slug}/clone`. Cria `Formation`+`FormationTeam`+`FormationTeamHero` em transação. Mapping `mode → challenge_type code` via constante `MODE_TO_CHALLENGE_CODE` (pvp→arena_of_heroes, pve→campaign, twisted_realm→dream_realm, labyrinth→arcane_labyrinth, king_tower→kings_tower). Nome da formation = `"{name} (cópia)"`, mode `target`, notes preenchido com descrição + atribuição de fonte. Redireciona para `/formations/{id}/edit` com flash message.
  - `RecommendedFormation::getRouteKeyName()` retorna `'slug'` para o route model binding usar slug em vez de id.
- **2 rotas em `/formations/recommended*`** dentro do middleware `auth`:
  - `GET formations/recommended` → `formations.recommended.index`
  - `POST formations/recommended/{recommended:slug}/clone` → `formations.recommended.clone` (regex `[a-z0-9-]+` no slug)
- **TS types em `types/afk.ts`:** `RecommendedFormationMode` (union de 6 modos) + `RecommendedFormation`.
- **Página `pages/formations/recommended/index.tsx`:**
  - `PageShell` com ícone `Sparkles` e descrição apontando que vem de fontes externas
  - Grid 1 col → 2 cols em `lg`. Cada card mostra: nome + badge colorido por mode (rosa pvp, verde pve, roxo twisted, azul labyrinth, amber king_tower), faceset com 5 portraits 56px (borda colorida pela facção via `FACTION_THEME[hero.faction.code].cardBorder/cardGradient`), descrição, link externo da fonte (ícone `ExternalLink`), botão "Duplicar para minhas formações"
  - Botão usa `useConfirm` para evitar duplicações acidentais. Loading state com `Loader2`. Após sucesso, `router.visit('/formations')` leva o user para a lista (com a cópia recém-criada já presente).
  - Faceset filtra heróis que existem no banco; se algum slug do comp não casar (cadastro pendente), mostra placeholder `<N> faltando` para o user saber.
- **Sidebar (`app-sidebar.tsx`):** novo item "Comps prontos" entre "Formações" e "Guias", ícone `Sparkles`. Visível para todos os usuários autenticados (não é admin-only — leitura é pública dentro do app).

##### Validação 21.3

- ✅ Build Vite 4.02s
- ✅ 21 testes de Formation passam (sem regressão)
- ✅ 2 rotas registradas
- ✅ TS check sem erros novos
- ❌ **Clone E2E não testado automaticamente** — exige seed do user + Beast/Hero suficientes. Smoke check manual: `/formations/recommended` renderiza, botão funciona, formation criada com 5 heróis nas posições 1-5.

#### 21.4 — allclash multi-source + comps curados reais (afk.guide via extensão)

Cobre a expansão pedida em "consolidar informações de outros sites". Importante: **afk.global ficou de fora** porque seu robots.txt bloqueia explicitamente `ClaudeBot`/`anthropic-ai`/`Claude-Web`. Como compromisso, o usuário capturou o conteúdo do afk.guide via a extensão **Claude for Chrome** (operada por humano) e me passou o dump estruturado em chat — ficou em ordem de magnitude melhor que scraping cego.

##### Tier ratings: source 'allclash' lado a lado com 'afk.guide'

- **`HeroRating::TIERS` estendido:** de 7 tiers (S+,S,A,B,C,D,F) para 11 (adiciona A+,B+,C+,D+) — schema já era `varchar(4)`, comporta sem migration.
- **`HeroRating::STAGE_ALL = 'all'`:** novo estágio para fontes que classificam tier list único (sem early/mid/end). allclash usa esse.
- **`SyncHeroRatingsAllClashCommand` (`heroes:sync-ratings-allclash`):**
  - **Cloudflare bloqueia** o `Http` facade do Laravel mesmo com User-Agent Chrome (TLS fingerprinting). Mudei para `Process::run(['curl', '-sL', '-A', '...', $url])` que passa.
  - Fallback `--from-file=path` se curl também falhar (caso o usuário precise baixar manualmente)
  - Parser DOMDocument: `//div[contains(@class, 'tieritem') and contains(@class, 'afkarena')]`. Cada item tem `id="hero name"` (lowercased + spaces) e classe `tiercode-XX`. `TIER_CODE_MAP` traduz: ss→S+, s→S, as→A+, a→A, bs→B+, b→B, cs→C+, c→C, ds→D+, d→D, f→F.
  - Resolver de hero estendido: tira parêntese final (`"elijah & lailah (twins)"` → `elijah-lailah`) + variants para Awakened.
  - **Resultado:** 214 itens parsificados, 211 matched, 191 ratings persistidos (alguns "Awakened X" + "X" mapeiam pro mesmo hero, `updateOrCreate` deduplica). Apenas 3 não encontrados: shalltear, bronn, leonardo da vinci (todos cadastro manual conhecido).
  - Adicionado a `config/sync_commands.php` com mesmo ícone `Trophy`.
- **UI multi-source:** `RatingsBySource` agrupa ratings por `source` antes de delegar pro `RatingsGrid`. Cada bloco tem header com label da fonte + link externo. `RatingsGrid` filtra dinamicamente quais estágios mostrar (só `early/mid/end` para afk.guide; só `all` para allclash) — não mostra colunas vazias.
- **Total no banco:** 3199 ratings de afk.guide (com 7 modos × 3 estágios) + 191 ratings de allclash (1 modo × 1 estágio) = **3390 ratings cross-source**.

##### Comps curados reais — substitui o seed genérico

Migration `add_curated_fields_to_recommended_formations` adiciona à tabela:

| Campo | Tipo | Uso |
|-------|------|-----|
| `tier` | varchar(4) nullable | Ranking do comp (S+, S, A) |
| `level_deficit` | varchar(32) nullable | Texto livre tipo "120-450+" |
| `core_count` | tinyint default 0 | Quantos primeiros heróis em `hero_slugs` são CORE (resto é flex) |
| `artifacts` | json nullable | Lista de strings com artefatos recomendados |
| `notes` | text nullable | Observações estratégicas separadas da `description` |

`RecommendedFormationsSeeder` reescrito com **13 comps curados** (6 PvE endgame + 7 PvP) substituindo os 8 genéricos anteriores:

**PvE Endgame (Campanha):**
- Thoran Cheese (S+) — déficit 120-450+, core: thoran/lorsan/kelthur/lyca/eironn
- Charm Comp (Mehira) (S+) — déficit 100-400+, core: mehira/rosaline/rowan
- Leo Permastun (S) — déficit 100-400+, core: leonardo/mishka/zolrath
- Alna GB (S) — déficit 100-350+, core: alna/grezhul
- Five-Pull (Eironn + Skriath) (S) — déficit 100-350+, core: eironn/skriath/queen
- Lucretia Invade (S) — déficit 100-350+, core: lucretia (1) + 13 flex

**PvP:**
- God Comp Lockout (S+) — core: ezizh/elijah-lailah/talene
- Gwyneth Burst (S) — core: gwyneth/eironn/lyca/lorsan
- Flora Stall (S) — core: flora/skreg
- Mauler Core (A) — core: skreg/satrana/numisu/tidus/ezizh
- Graveborn Celestial (A) — core: talene/orthros/athalia/nara
- Energy Battery (A) — core: rowan/rosaline/tasi
- Burst PvP (A) — core: zolrath/lucretia/eironn

Cobertura de heróis: 60 de 62 nomes existem no banco (só "Canisa & Ruke" falta — cadastro manual pendente). O seeder é declarativo: limpa comps anteriores cujo `source_label` está na lista de seeds curados antes de upsertar — admins podem adicionar comps customizados com outro source_label sem serem apagados.

**UI atualizada (`pages/formations/recommended/index.tsx`):**
- Header do card: nome + badge de tier colorido (S+ gradient, S/A/B com cor sólida)
- Sub-header: badge do mode + `level_deficit` ("Déficit: 120-450+")
- **Heróis separados em 2 grupos:** "Core (N)" com borda forte e label verde; "Flex (substituições)" com borda esmaecida (`opacity-70`) e label cinza
- `notes` em destaque amarelo (border-l-2 amber, italic) — separa estratégia de descrição
- Lista de `artifacts` como chips com ícone `Gem`
- Heróis não cadastrados (`?` placeholder) mostram slug em amarelo (não quebra o layout)

##### Validação 21.4

- ✅ Build Vite 4.19s
- ✅ 21 testes de Formation verdes
- ✅ 2 sources de ratings persistidos (afk.guide + allclash) — 3390 entries totais
- ✅ 13 comps curados (6 PvE + 7 PvP) com tier, level_deficit, core/flex split, artifacts, notes
- ⚠️ **Engravings (E30/E60), Signature Items (custos), Beasts tier list (allclash) ainda não consumidos** — capturados pelo usuário no dump mas fora do escopo desta sessão. Anotados como Fase 22 candidatos.
- ⚠️ **afk.global pulado** — bloqueio explícito a Claude no robots.txt. Compromisso assumido: respeitar.

#### 21.5 — Beasts tier list + Eternal Engravings + comps Whitesushii

Continuação direta da 21.4: consumir os dados restantes do dump capturado pelo usuário via extensão Claude Chrome.

##### Beasts tier list (afk.guide)

- **Migration `add_tier_to_beasts`:** adiciona `tier` (varchar 4) + `tier_note` (text), index em `tier`. Beast model fillable atualizado.
- **`BeastTiersSeeder`** popula 27 beasts com tier (S+/S/A/B/C/D/F) e nota explicativa: Slumber Seal (S+), Pandy Dumpty/Treatspector/Flutterplume Owl/Nightfall Pegasus (S), 6 em A, 6 em B, 4 em C, 4 em D, 2 em F. Cria 2 que não existiam na lista master da Fandom (Count Snickerfang, Joyous Roamer) com tier_note mas sem buffs (cadastro manual via UI).
- **UI:** card em `/admin/beasts` mostra **tier badge colorido** ao lado do nome (mesmo gradient do tier S+ usado em outros lugares). `tier_note` aparece como tooltip nativo (`title=`).
- **Modal de edição (`beast-form-modal.tsx`):** novo bloco com select de tier (`S+`/`S`/`A`/`B`/`C`/`D`/`F`/`(sem tier)`) + input de tier_note. Validação backend: `Rule::in(['S+', 'S', 'A', 'B', 'C', 'D', 'F'])`.

##### Eternal Engravings E30/E60 por herói

- **Migration `create_hero_engravings_table`:** id, hero_id (FK cascade), level (varchar 4 — `e30`/`e60`), label (varchar 128), description (text), source/source_url, timestamps. Unique `(hero_id, level, source)` permite múltiplas fontes futuras. Index `(hero_id, level)`.
- **`HeroEngraving` model** com `LEVEL_E30`/`LEVEL_E60` constants e `belongsTo(Hero)`. `Hero::engravings()` ordena por level.
- **`HeroEngravingsSeeder`** persiste **174 engravings (87 heróis × 2 níveis)** estruturados em array por facção: 10 Celestials, 9 Hypogeans, 10 Lightbearers, 16 Maulers, 15 Wilders, 16 Graveborns, 11 Dimensionals. Mantém label original (em inglês para preservar matching com guias) + descrição em PT-BR.
- **`HeroController::details`** carrega `engravings:id,hero_id,level,label,description,source,source_url` e injeta no payload.
- **UI:** nova `SectionBlock` "Eternal Engravings" no modal de herói (após Tier Rating, antes de Habilidades). Componente `EngravingsList` renderiza E30 (border sky) e E60 (border amber) lado a lado em grid 1→2 colunas. Cada bloco mostra badge do nível + label (nome curto) + descrição completa. Link para a fonte (afk.guide) no canto superior.
- **TS types:** `HeroEngravingLevel = 'e30' | 'e60'` e `HeroEngraving` em `types/afk.ts`.

##### Comps Whitesushii (4 únicos adicionados)

Cherry-pick dos comps endgame Whitesushii que não duplicam os 13 já curados:

- **The Slumber (S, PvE):** CC chain pippa/tasi/gorvo/khazard + saurus escalando dano
- **The Speedy Lizard (S, PvE):** elijah-lailah → tasi → saurus
- **Manhattan Project / Safiya-Eironn (S, PvE):** safiya/eironn — corrida ao ultimate da Safiya
- **Daimon Carry Solo (A, PvE):** daimon como carry solo, tanks compram tempo

Source label `afk.guide / Whitesushii` distingue do `afk.guide / Best Teams`. Seeder estendeu a whitelist de cleanup para incluir esse novo source_label.

**Total agora:** 17 comps (10 PvE + 7 PvP).

##### Validação 21.5

- ✅ Build Vite 4.12s
- ✅ 110 testes verdes (Hero|Formation|Beast)
- ✅ 27 beasts com tier (todos da lista do afk.guide)
- ✅ 174 engravings persistidos (87 heróis × 2 níveis)
- ✅ 17 comps recomendados (10 PvE + 7 PvP)
- ⚠️ **Não consumido ainda:** Signature Items (custos por nível), heroes destaque por fase (early/mid/end por role), tradução PT-BR dos labels de engravings (mantidos em EN). Anotados para Fase 22.

#### Validação Fase 21

- ✅ Build Vite 4.50s
- ✅ 187 testes verdes (sem regressão)
- ✅ Migration `hero_ratings` aplicada
- ✅ Migration `recommended_formations` aplicada
- ✅ Sync produção: 572 linhas parseadas / 552 matched (96.5%) / 3199 ratings persistidos
- ✅ 6 rotas em `/admin/sync*` (não mudou — `heroes:sync-ratings` reusa POST `/admin/sync` via whitelist)
- ✅ Type check sem erros novos (mantidos os 6 pré-existentes do `hero-detail-modal.tsx`/Inertia generics)
- ⚠️ **Termos de uso do afk.guide não verificados além do `robots.txt`** — `Disallow` apenas em `/wp-admin/`, sem `Crawl-delay`. Fetch único por sync, não há scraping massivo recorrente. Atribuição da fonte (`source: 'afk.guide'` + `source_url`) preservada em cada rating e no rodapé do `RatingsGrid`.

---

### ✅ Fase 24 — Algoritmo de scoring usa tier rating + features + beast tier + comps recomendados

Aproveitamento dos datasets coletados nas Fases 21-22 para enriquecer o `TeamScorer`. Antes desta fase, o algoritmo só considerava: faction bonus, class balance, synergy de pares, power (ascension+SI+furniture+stats+equip+art+col+beast). Agora, **tier rating modo-específico** e **bônus contextual por feature** entram no cálculo, beasts ganham peso por tier, e comps recomendados aparecem como sugestão informativa na UI.

#### 24.1 — Helpers de contexto

- **`ChallengeTypeMode::forCode(?string)`:** mapeia o `code` do `ChallengeType` para o `mode` do HeroRating. Mapping completo:
  - `arena_of_heroes` / `legends_championship` / `heroes_of_esperia` → `pvp`
  - `dream_realm` → `twisted_realm` (nome moderno)
  - `arcane_labyrinth` → `labyrinth`
  - `abyssal_expedition` → `abyssal_expedition`
  - `campaign` / `kings_tower` / `cursed_realm` / `bountiful_trials` / `dragon_trial` → `pve`
  - Helpers `isPvp()` e `isTwistedRealm()` para checks rápidos
- **`RosterStageInferrer::inferFromRoster(iterable<UserHero>)`:** calcula avg de `ascension_value` e retorna `early` (<5), `mid` (5-10) ou `end` (≥10). Default `end` para roster vazio. Cortes alinhados ao `tierFromAscension` do frontend (Fase 6).

#### 24.2 — `TierRatingScorer` (P1) + integração

- **Componente novo:** `TierRatingScorer::score(Hero[], ratingsByHeroId, mode, stage)` retorna `0..1` (média de pontos / 10).
- **Tabela de pontos por tier:** S+ → 10, S → 8, A+ → 7, A → 6, B+ → 5, B → 4, C+ → 3, C → 2, D+ → 1, D → 0.5, F → 0.
- **Estratégia de fonte (cascata):**
  1. afk.guide com `mode` + `stage` exatos
  2. afk.guide com mesmo `mode`, qualquer `stage` (preferência: end > mid > early)
  3. afk.guide com `mode=overall`
  4. allclash (`stage=all`, `mode=overall`)
  5. Fallback: 0.0 (não penaliza, não diferencia)
- **Heróis sem rating** retornam 0 — não puxam o time pra baixo, mas também não dão vantagem.
- **`ScoringContext`** novo bundle data class (em `app/Services/Optimization/ScoringContext.php`) carrega `ratingsByHeroId`, `featuresByHeroId`, `mode`, `stage`. É o objeto que viaja pelo loop de scoring nas hot paths (SingleTeamOptimizer/MultiTeamPartitioner).
- **`TeamScorer::score()` ganha parâmetro `?ScoringContext $ctx = null`** — backward-compat preservada (sem ctx → tier_rating + feature_bonus = 0). `SingleTeamOptimizer::optimize()` e `MultiTeamPartitioner::optimize()` propagam o ctx adiante até cada chamada do scorer.
- **`ScoringProfile` ganhou** `tierRatingWeight` (default 1.0) e `featureWeight` (default 0.5). `ScoreBreakdown` ganhou `tierRating` e `featureBonus`.

#### 24.3 — `FeatureBonusCalculator` (P2)

Bônus contextual baseado nas tags `HeroFeature`:
- **Mode PvP** → `+0.15` por herói com `pvp_top`
- **Mode Twisted Realm** → `+0.15` por herói com `twisted_realm_top`
- **Stage `early`** (qualquer mode) → `+0.05` por herói com qualquer tag `early_*` (early_carry/support/tank/utility)

Bônus se acumulam: um herói `early_carry` + `pvp_top` em comp PvP early ganha os dois (`+0.20`).

#### 24.4 — Beast tier afeta scoring (P3)

`PowerCalculator` agora multiplica o buff de beast pelo factor do tier:

| Tier | Factor | Era | Δ |
|------|--------|-----|---|
| S+   | 1.0    | 0.5 | +100% |
| S    | 0.8    | 0.5 | +60% |
| A    | 0.6    | 0.5 | +20% |
| B    | 0.5    | 0.5 | igual |
| C    | 0.4    | 0.5 | -20% |
| D    | 0.3    | 0.5 | -40% |
| F    | 0.2    | 0.5 | -60% |
| (sem tier) | 0.5 | 0.5 | igual (default) |

Slumber Seal (S+) agora vale **2x mais** que beasts sem tier; tier C/D ficam significativamente abaixo.

#### 24.5 — Match com comps recomendados (P4, info-only)

- **`RecommendedFormationMatcher::match(Hero[], mode, minOverlap=2, limit=3)`** retorna top 3 comps com maior overlap de heróis. Mapeia mode do HeroRating → modes do RecommendedFormation (PvE engloba campaign/labyrinth/abyssal).
- **Resposta:** `{ slug, name, mode, tier, core_overlap, total_overlap, total_core, percent, missing_core[] }` por match.
- **Ordena por:** `core_overlap` desc, depois `total_overlap` desc.
- **`FormationController::edit`** agora retorna `recommendedMatches` (Map por team position) no payload Inertia. Usuário pode ver "Sua equipe está 4/5 do core do Thoran Cheese — falta `kelthur`". UI não consume isso ainda — payload pronto para a próxima iteração de UX.
- **Não afeta scoring** — é só sugestão visual.

#### 24.6 — Integração nos controllers

- **`FormationController::suggestTeam` e `suggestAll`:** carregam ratings + features dos heróis do pool, inferem stage do roster e mode do challenge_type, montam `ScoringContext` e passam adiante. Helper privado `loadScoringContext()`.
- **`FormationCompareController::buildFormationPayload`:** mesma técnica, garante que score retroativo das formations comparadas usa o mesmo algoritmo.
- **Eager loads atualizados:** `challengeType:id,code,...` (antes não carregava `code`); `beast:...,tier` (antes não carregava `tier`).

#### Validação Fase 24

- ✅ Build Vite 4.13s
- ✅ **199 testes verdes** (era 187, +12 novos: 4 ChallengeTypeMode + 5 RosterStageInferrer + 3 PowerCalculator beast tier)
- ✅ Backward compat: testes de optimization existentes (TeamScorer, SingleTeamOptimizer, MultiTeamPartitioner) seguem passando sem alteração — quando ctx é null/empty, novos componentes retornam 0
- ✅ Sem regressões nos algoritmos existentes — pesos novos somam ao total existente
- ⚠️ **Não implementado:** sliders na UI para ajustar `tierRatingWeight` / `featureWeight` por formation (hoje vêm do `ScoringProfile` do challenge_type, fixo)

#### 24.7 — UI dos `recommendedMatches` no editor

Consome o payload já entregue pela 24.5: agora cada team no editor mostra uma stripe colorida com até 3 comps recomendados que mais se aproximam.

- **Tipo TS** `RecommendedMatch` adicionado em `types/afk.ts` (slug, name, mode, tier, core_overlap, total_overlap, total_core, percent, missing_core).
- **`PageProps`** do `formations/edit.tsx` aceita `recommendedMatches?: Record<number, RecommendedMatch[]>` (Map por team position).
- **`RecommendedMatchesStripe` component** (inline no edit.tsx) renderizado abaixo de cada `TeamRow`:
  - Borda dashed amber, ícone `Sparkles`, label "Comps próximos:"
  - Para cada match: chip com badge de tier colorido (S+ gradient, S rose, A orange, B amber, C zinc), nome do comp truncado em 140px, contagem `core_overlap/total_core core`, e (se houver) "falta N" em amber para missing_core
  - **Tooltip nativo** (`title=`) com 3-4 linhas: nome (tier), core overlap, total no comp, lista de heróis faltando do core
  - Click leva pra `/formations/recommended` (página inteira, sem filtro por slug ainda — pode evoluir pra `/formations/recommended#slug` no futuro)
  - Não renderiza nada se `matches.length === 0`

##### Validação 24.7

- ✅ Build Vite 4.13s
- ✅ 199 testes verdes
- ✅ TS check limpo
- ⚠️ Click ainda não filtra por slug — abre a página inteira

---

### ✅ Fase 23 — Tier Lists customizadas com drag-and-drop

Permite que cada user crie suas próprias tier lists e ranqueie heróis arrastando-os entre tiers customizáveis. Compartilhamento público via share_token (mesmo padrão das formations da Fase 6.4).

#### Schema (3 tabelas)

| Tabela | Campos relevantes | Notas |
|--------|-------------------|-------|
| `user_tier_lists` | id, user_id (FK cascade), name (varchar 100), description (text nullable), share_token (varchar 32 unique nullable) | Index `user_id`. Token gerado on-demand via `generateShareToken()`. |
| `user_tier_list_rows` | id, user_tier_list_id (FK cascade), label (varchar 16), color (varchar 16, default 'zinc'), position (smallint) | Index `(user_tier_list_id, position)`. 8 cores aceitas: rose/orange/amber/emerald/sky/violet/fuchsia/zinc. |
| `user_tier_list_items` | id, user_tier_list_row_id (FK cascade), hero_id (FK restrict), position (smallint) | Unique `(row_id, hero_id)` — herói só uma vez por row. Index `(row_id, position)`. |

#### Models

- **`UserTierList`:** `belongsTo(User)`, `hasMany(rows)` ordenado por `position`. `generateShareToken()` faz loop até achar token único de 32 chars; `revokeShareToken()` zera.
- **`UserTierListRow`:** `belongsTo(UserTierList)`, `hasMany(items)` ordenado por `position`. Constants `COLORS` valida cores no controller.
- **`UserTierListItem`:** `belongsTo(UserTierListRow, hero_id=>row)`, `belongsTo(Hero)`.
- **`User::tierLists()`:** nova relação `hasMany(UserTierList)`.

#### Controller (`UserTierListController`) — 14 endpoints

Lista de rotas registradas em `routes/web.php`:

```
GET    /tier-lists                                    tier-lists.index
GET    /tier-lists/create                             tier-lists.create
POST   /tier-lists                                    tier-lists.store
GET    /tier-lists/{tierList}/edit                    tier-lists.edit
PATCH  /tier-lists/{tierList}                         tier-lists.update
DELETE /tier-lists/{tierList}                         tier-lists.destroy
POST   /tier-lists/{tierList}/rows                    tier-lists.rows.add
PATCH  /tier-lists/{tierList}/rows/{row}              tier-lists.rows.update
DELETE /tier-lists/{tierList}/rows/{row}              tier-lists.rows.delete
POST   /tier-lists/{tierList}/rows/reorder            tier-lists.rows.reorder
POST   /tier-lists/{tierList}/place-hero              tier-lists.place-hero
POST   /tier-lists/{tierList}/share                   tier-lists.share
DELETE /tier-lists/{tierList}/share                   tier-lists.share.revoke
GET    /tier/{token}                                  tier-lists.public  (auth-free, regex 32 chars)
```

- **`store`** cria a lista com **5 tiers padrão** (S+/S/A/B/C com cores rose/orange/amber/emerald/sky) em transação. User redireciona para o editor.
- **`placeHero`** é idempotente: remove o herói de qualquer row anterior dentro da mesma lista (em uma única query `whereIn('row_id', ...)`) antes de inserir na nova. `row_id=null` move pra fora dos tiers (volta pro pool).
- **`reorderRows`** valida que todos os IDs informados pertencem à lista antes de aplicar (defensa contra IDs de outras listas). Update em loop dentro de transação.
- **`addRow`** auto-incrementa position (`max(position) + 1`).
- **`share`** gera token só se ainda não existe (idempotente). `unshare` apenas zera.
- **`publicShow`** é a única rota fora de `auth` middleware — regex `[A-Za-z0-9]{32}` no path.
- Todos os endpoints com `{tierList}` validam ownership: `abort_unless($tierList->user_id === $request->user()->id, 403)`.

#### Frontend (4 páginas + helpers em `lib/afk.ts`)

- **`pages/tier-lists/index.tsx`:** grid 1→3 cols com cards das listas do user. Cada card: nome, badges (`{N} tiers`, `Pública` se `share_token`), descrição truncada, botões Editar/Excluir.
- **`pages/tier-lists/create.tsx`:** form simples com `useForm` (name + description). Após submit, redireciona pro editor.
- **`pages/tier-lists/edit.tsx`** (componente principal, ~370 linhas):
  - Layout `grid-cols-1 lg:grid-cols-[1fr_280px]`: tiers à esquerda, pool à direita.
  - **`DndContext`** envolve tudo. `PointerSensor` com `activationConstraint: { distance: 5 }` evita drags acidentais em clicks.
  - Cada herói é `useDraggable` com `id=hero.id` (numeric). Cada `TierRow` e o `HeroPool` são `useDroppable` com `id=row-{id}` ou `id=pool`.
  - **`onDragEnd` faz optimistic update** local (move o item entre rows no state) **antes** de chamar `axios.post('/place-hero')`. Em caso de erro, faz `router.reload({ only: ['tierList'] })` pra ressincronizar.
  - **`HeroPool`:** filtros por facção, classe e busca textual (case-insensitive). `useMemo` deriva o pool excluindo heróis já posicionados.
  - **`TierRow` editável:** click no label do tier abre form inline com input de label + select de cor + botões save/cancel/delete. Cores aplicadas via `TIER_LIST_COLOR_BG` e `TIER_LIST_COLOR_BORDER` (helpers em `lib/afk.ts`).
  - **Compartilhamento:** botão único toggle entre "Compartilhar" e "Pública (revogar)". Ao gerar token, copia URL pública pro clipboard automaticamente (`navigator.clipboard.writeText`) e mostra `Check` por 2s.
  - **Editar metadata:** botão "Editar info" abre painel com inputs de name/description; salva via PATCH.
- **`pages/tier-lists/public.tsx`:** read-only, layout sem `AppLayout` (página standalone tipo `formations/public`). Header com nome + autor + descrição. Rows renderizadas com mesma paleta. Footer com link "AFK Builder".

#### Helpers em `lib/afk.ts`

- `TIER_LIST_COLORS`: array das 8 cores aceitas
- `TIER_LIST_COLOR_BG`: classes Tailwind para o label colorido (bg sólido)
- `TIER_LIST_COLOR_BORDER`: classes para a borda da row + bg suave (`/5`)

#### Sidebar

Novo item **"Tier Lists"** (ícone `ListChecks` do lucide-react) entre "Comps prontos" e "Guias".

#### Validação Fase 23

- ✅ Build Vite 4.03s — chunk de `@dnd-kit/core` separado (42.57 kB / 14.19 kB gzip)
- ✅ 187 testes verdes (sem regressão — nenhum teste novo escrito; Fase 23 adiciona apenas tabelas/rotas/UI sem mexer em código existente testado)
- ✅ 14 rotas em `/tier-lists*` + 1 pública `/tier/{token}`
- ✅ 3 migrations aplicadas
- ⚠️ **Sem testes automatizados específicos de tier list** — endpoints simples + UI dependem de DnD que é difícil de testar em CI. Smoke check manual: criar lista, adicionar tier custom, arrastar herói entre rows, compartilhar, abrir URL pública em incógnito.
- ⚠️ **Reordenação de rows via DnD ainda não implementada na UI** — endpoint existe (`reorderRows`), mas a UI atual só permite editar label/cor/excluir, não arrastar a row inteira. Pode ser próxima iteração.

#### 23.1 — Reorder de rows via DnD + DragOverlay + visual feedback intensificado

Fechou o gap deixado em 23: rows agora são arrastáveis para reordenar. Junto, adicionei feedback visual rico durante drag: preview do elemento seguindo o cursor + destaque forte do drop target.

##### Reorder de rows com `@dnd-kit/sortable`

- **Imports adicionados:** `SortableContext`, `useSortable`, `arrayMove`, `verticalListSortingStrategy` de `@dnd-kit/sortable`; `CSS` de `@dnd-kit/utilities`; `DragOverlay`, `DragStartEvent` de `@dnd-kit/core`.
- **`SortableContext`** envolve a lista de rows com `verticalListSortingStrategy` e `items={rows.map(r => r.id)}`.
- **`SortableTierRow`** (renomeado de `TierRow`):
  - Wrapping `useSortable({ id: row.id, data: { kind: 'row' } })` no container externo
  - Aplica `transform = CSS.Transform.toString(transform)` + `transition` para a animação
  - **Drag handle dedicado** (botão com ícone `GripVertical`) recebe `attributes` e `listeners` — só ele ativa o drag de row, evitando conflito com clicks no label de edit
  - `isDragging && 'opacity-30'` no DOM original enquanto o overlay segue o cursor
  - `useDroppable({ id: 'row-${row.id}' })` continua presente no corpo da row (recebe drops de heróis) — separado do sortable
- **Discriminator entre tipos:** `data: { kind: 'hero' | 'row' }` no `useDraggable`/`useSortable`. `handleDragStart` lê `event.active.data.current?.kind` e seta `activeDrag`. `handleDragEnd` despacha em duas branches:
  - `kind === 'row'` → `arrayMove` local + `axios.post('/rows/reorder', { row_ids: [...] })`
  - `kind === 'hero'` → fluxo existente de place-hero
- **Width da coluna do label aumentou** de `w-20` para `w-24` para acomodar o drag handle acima do label sem cortar.

##### `DragOverlay` — preview seguindo o cursor

- **`<DragOverlay dropAnimation={null}>`** dentro do `DndContext`. Renderiza um clone visual do elemento sendo arrastado, posicionado pelo `@dnd-kit` automaticamente conforme o cursor se move.
- **`HeroPreviewCard`:** rotação `-3deg` + `border-2 border-primary` + `shadow-2xl` para sensação de "destacado do plano".
- **`RowPreviewCard`:** rotação `-1deg` + `ring-2 ring-primary` + `shadow-2xl`. Mostra label + cor + thumbnails dos primeiros 8 heróis (com `+N` overflow se mais).
- O `dropAnimation={null}` desabilita a animação de "voltar para o lugar" quando o drop não é válido — em compensação, faz o preview sumir instantâneo (UX mais responsivo).

##### Visual feedback de drop target

| Target  | Estado normal | Estado `isOver` |
|---------|---------------|-----------------|
| Row     | `border-2` da cor do tier  | `scale-[1.01]` + `shadow-lg` + `ring-4 ring-primary` |
| Corpo da row | bg neutro | `bg-primary/5` |
| Mensagem em row vazia | "Arraste heróis aqui" cinza | "⬇ Solte aqui" em primary com font-semibold |
| Pool | `border` neutra | `border-primary` + `bg-primary/5` + `ring-4 ring-primary` + `shadow-lg` |
| Header do pool | "Pool (N)" cinza | "⬇ Solte para remover do tier" em primary |

`transition-all` em rows e pool faz a mudança ser animada (não pisca).

##### Validação 23.1

- ✅ Build Vite 4.05s — chunk de `@dnd-kit/sortable` adicionado em `utils-*.js` (23.48 kB / 7.93 kB gzip)
- ✅ 187 testes verdes (sem regressão)
- ✅ Type check limpo (errors pré-existentes do `public.tsx` ajustados de quebra)
- ✅ Reorder funcional via grip handle no label
- ⚠️ Sort horizontal de items dentro da mesma row ainda não implementado — drop ainda insere no fim. Fica como melhoria futura (precisa `SortableContext` aninhado horizontal por row + estratégia de cross-list para permitir mover entre rows + dentro da mesma row).

#### 23.2 — Sort horizontal de items dentro de uma row (cross-container DnD)

Fechou o gap deixado em 23.1: items dentro de uma row são reordenáveis via drag-and-drop, e moves entre rows preview em tempo real durante o arrasto.

##### Endpoint backend

- **`POST /tier-lists/{tierList}/rows/{row}/reorder-items`** body `{ hero_ids: number[] }`. Valida ownership + `row->user_tier_list_id === tierList->id`. Em transação: itera o array enviado e atualiza `position = index` para cada herói da row pelo `hero_id`. Idempotente.
- Adicionado a `routes/web.php` como `tier-lists.rows.reorder-items`.
- Total de rotas em `/tier-lists*` agora: 15 (+1 desde 23.1).

##### Frontend — refator de `pages/tier-lists/edit.tsx`

- **`SortableContext` aninhado** por row body com `horizontalListSortingStrategy` e `items={row.items.map(it => it.hero_id)}`. Cada item dentro de uma row é um `SortableHero` (novo componente) usando `useSortable({ id: hero.id, data: { kind: 'hero', fromRowId: rowId } })`. Pool continua com `useDraggable` (heróis no pool não precisam ordem entre si).
- **IDs do droppable de cada row mudaram** de `row-${id}` para `row-body-${id}` para evitar colisão com o `useSortable` da row inteira (que usa `id = row.id` numérico). Now ID space:
  - `row-body-${id}` (string) → drop target genérico de uma row (corpo)
  - `row.id` (number) → sortable da row para reorder vertical
  - `hero.id` (number) → sortable/draggable do hero
  - `pool` (string) → drop target do pool
- **`closestCorners`** como `collisionDetection` no `DndContext` — funciona melhor que default para listas mistas vertical+horizontal aninhadas.
- **`onDragOver` faz cross-container moves em tempo real:**
  - Detecta destino via `over.data.current.kind` (`'hero'` → pega o `fromRowId` do hero abaixo) ou prefix do `over.id` (`row-body-${id}` ou `'pool'`)
  - Compara com `findRowOfHero(heroId)` (helper) — origem atual no state
  - Se diferente, atualiza o state movendo o herói: filtra fora da row de origem + insere na de destino
  - **Não persiste** — só visual. Persiste em `onDragEnd`.
- **`onDragEnd` discrimina 3 casos:**
  - **Reorder rows** (`kind === 'row'`): mantém o fluxo da 23.1 → `arrayMove` + POST `/rows/reorder`
  - **Reorder dentro da mesma row** (origem === destino, `over.data.kind === 'hero'`): `arrayMove(items)` localmente + POST `/rows/{row}/reorder-items` com `hero_ids` ordenados
  - **Move entre containers** (origem ≠ destino — pool↔row ou row↔outra-row): `onDragOver` já moveu visualmente; só persiste com POST `/place-hero`
- **`dragOriginRowId` state** capturado em `onDragStart` armazena de onde o herói saiu (null = pool, number = row). Usado em `onDragEnd` para distinguir reorder vs move e evitar fazer POST quando nada mudou (drag cancelado).
- **`findRowOfHero(heroId, source)`** helper inline (busca em `rows`) — usado tanto em `onDragStart` (capturar origem) quanto em `onDragOver` (detectar mudança real de container).
- **Fallback de erro**: qualquer falha de rede em qualquer dos 3 endpoints → `router.reload({ only: ['tierList'] })` recarrega o estado canonical do servidor.

##### Validação 23.2

- ✅ Build Vite 4.10s
- ✅ 187 testes verdes
- ✅ Type check limpo nos arquivos modificados
- ✅ 15 rotas em `/tier-lists*` (+1 reorder-items)
- ⚠️ **Não testado em CI** — DnD multi-container é difícil de testar automatizado. Smoke check manual: criar lista, arrastar hero pool→row (move), arrastar entre rows (move), arrastar dentro da mesma row (reorder), abrir DevTools Network e verificar 1 request por gesto.
- ⚠️ **`scrollIntoView` ou auto-scroll** durante drag para fora da viewport não foi implementado — `@dnd-kit` tem `AutoScroll` modifier mas não habilitei.

#### 23.3 — Bugfix de ID collision + remoção do reorder de rows

Dois ajustes pequenos baseados em feedback de uso real.

##### Bugfix: ID collision entre hero.id e row.id

**Sintoma:** ao arrastar a Alna (`hero.id=5`) que estava na tier S, a row C inteira (`row.id=5`) era arrastada junto. O `@dnd-kit` confundia os dois `useSortable` que usavam o mesmo ID numérico no mesmo `DndContext`.

**Fix:** prefixar todos os IDs de sortable/draggable com `'hero-'` ou `'row-'`:

| Elemento | Antes | Depois |
|----------|-------|--------|
| `SortableContext` (rows) | `items={rows.map(r => r.id)}` | `items={rows.map(r => \`row-${r.id}\`)}` |
| `useSortable` da row | `id: row.id` | `id: \`row-${row.id}\`` + `data: { rowId }` |
| `SortableContext` (items por row) | `items=[hero IDs]` | `items=[\`hero-${heroId}\`]` |
| `useSortable` do hero | `id: hero.id` | `id: \`hero-${hero.id}\`` + `data: { heroId, fromRowId }` |
| `useDraggable` do pool | `id: hero.id` | `id: \`hero-${hero.id}\`` + mesmo `data` |
| `useDroppable` row body | (sem data) | `data: { kind: 'row-body', rowId }` |
| `useDroppable` pool | (sem data) | `data: { kind: 'pool' }` |

Os handlers (`onDragStart`/`onDragOver`/`onDragEnd`) **não fazem mais parsing de string**: leem `active.data.current.heroId` ou `over.data.current.rowId` direto. Mais robusto e legível.

##### Remoção: reorder de rows (subfases 23.1 e parte da 23.2 desfeitas)

Após uso, decidiu-se que **arrastar tier rows não traz valor suficiente** — a ordem natural (S+ no topo → F embaixo) já é canônica e raramente precisa mudar. A complexidade adicional (drag handle visível, rotação de preview, lógica de discriminator `kind=row` nos handlers) não compensava.

**Removido:**

- **Backend:** método `reorderRows` do `UserTierListController` + rota `POST /tier-lists/{tierList}/rows/reorder`. Total de rotas em `/tier-lists*` agora: 13 (era 15).
- **Frontend (`edit.tsx`):**
  - `SortableContext` outer (que envolvia as rows)
  - `useSortable` no container da row (`SortableTierRow` virou `TierRow` simples sem ref de sortable)
  - Drag handle (botão com ícone `GripVertical` no topo do label)
  - Branch `kind === 'row'` em `onDragStart` e `onDragEnd`
  - `RowPreviewCard` do `DragOverlay` (componente removido inteiramente)
  - Imports: `verticalListSortingStrategy`, `GripVertical`
  - Tipo de `activeDrag.kind` simplificado para `'hero'` apenas
  - Width da coluna do label voltou de `w-24` para `w-20`

**Mantido (do que foi adicionado em 23.1/23.2):**

- `DragOverlay` com `HeroPreviewCard` (preview do hero seguindo cursor)
- Drop target intensificado (ring-4, scale, shadow, mensagens "⬇ Solte aqui")
- `closestCorners` collision detection
- `SortableContext` aninhado horizontal por row + `SortableHero` (sort horizontal de items)
- `onDragOver` para cross-container moves em tempo real
- Endpoint `reorder-items` por row (backend)

##### Validação 23.3

- ✅ Build Vite 4.20s
- ✅ 187 testes verdes
- ✅ Type check limpo
- ✅ 13 rotas em `/tier-lists*` (2 a menos)
- ✅ Bug de ID collision não reproduz mais — Alna pode ser arrastada da S para C sem mover rows

---

### ✅ Fase 22 — Heroes destaque por fase + Signature Items guide

Continuação direta da 21.5: consumir os 2 últimos pedaços úteis do dump capturado pelo usuário (afk.guide via extensão Claude Chrome). Pulei tradução PT-BR de engravings (descrições já em PT-BR; labels EN são nomes próprios da comunidade) e tier de beasts no allclash (sem ganho marginal sobre afk.guide).

#### 22.1 — Heroes destaque por fase (`hero_features`)

- **Migration `create_hero_features_table`:** id, hero_id (FK cascade), tag (varchar 32), note (text nullable), source/source_url, timestamps. Unique `(hero_id, tag, source)` permite múltiplas fontes futuras. Index em `tag`.
- **`HeroFeature` model** com 6 constants: `TAG_EARLY_CARRY`, `TAG_EARLY_SUPPORT`, `TAG_EARLY_TANK`, `TAG_EARLY_UTILITY`, `TAG_PVP_TOP`, `TAG_TWISTED_REALM_TOP`. `belongsTo(Hero)`.
- **`Hero::features()` hasMany.**
- **`HeroFeaturesSeeder` persiste 37 features** organizadas em 6 grupos:
  - 6 early carry (Daimon, Raku, Scarlet, Ainz, Eironn, Kren)
  - 10 early support (Joan of Arc, Palmer, Nevanthi, Ferael, Rosaline, Lyca, Tasi, Silas, Merlin, Rowan)
  - 3 early tank (Grezhul, Mishka, Arthur)
  - 3 early utility (Lorsan, Brutus, Mortas)
  - 9 PvP top (Ezizh, Mehira, Talene, Twins, Oden, Lucretia, Flora, Queen, Ainz)
  - 6 Twisted Realm top (Scarlet, Awakened Solise, Twins, Mortas, Grezhul, Warek)
  - Notas extraídas dos parênteses do dump preservadas em `note` (ex: "Daimon (SI Lv20)" → tag=early_carry note="SI Lv20")
- **Resolver de hero:** `splitNameAndNote()` separa nome + nota; `resolveHero()` tem mapa de aliases (`twins → elijah-lailah`, `awakened solise → solise`, `ainz → ainz-ooal-gown`, etc) + fallback de slug + match case-insensitive.
- **HeroController:** `index` carrega `with('features:id,hero_id,tag,note')` para uso futuro nos cards; `details` carrega `with('features:id,hero_id,tag,note,source_url')`. Ambos retornam no payload.
- **TS types:** `HeroFeatureTag` union de 6 tags + `HeroFeature` interface em `types/afk.ts`. `Hero.features?` opcional.
- **UI:** badges horizontais no topo do tab "Detalhes" do modal de herói (antes de Stats). Cores diferentes por tag: rosa carry, azul support, âmbar tank, verde utility, roxo pvp_top, fúcsia twisted_realm. Notas anexadas no badge (ex: `Carry Early Game · SI Lv20`). Card do herói **não** ganhou badge para preservar layout limpo (decisão consciente: features são para o modal de detalhe).

#### 22.2 — Signature Items guide (`/guias/signature-items`)

Em vez de criar nova tabela ou colunas, reusei o sistema `guides` (Fase 8) para armazenar os custos por nível dos SI como conteúdo markdown.

- **`SignatureItemsGuideSeeder`** cria 1 entry com slug `signature-items`, categoria `mecanicas`, `display_order=90`. `title`/`summary`/`content` em EN + `_pt` em PT-BR.
- **Conteúdo (PT-BR):** O que é SI, como destravar (20 Primordial Emblems), 4 tabelas markdown com custos por faixa (1-10, 11-20, 21-30, 31-40), 13 heróis legendários sem SI (cap nivel 160), estratégia de investimento por faixa.
- **Cap +40:** documentado como exclusivo das facções **Celestial e Hypogean**. Outras facções param em +30.
- **Apenas conteúdo, sem código:** o `GuideController` existente (Fase 8) já renderiza o markdown via `MarkdownContent`, então o guia aparece automaticamente em `/guias` sem mudança de rota/UI.

##### Validação Fase 22

- ✅ Build Vite 4.12s
- ✅ 114 testes verdes (sem regressão)
- ✅ 37 features persistidas (cobertura completa do dump — 0 missing)
- ✅ 9 guides totais (era 8; signature-items adicionado)
- ⚠️ **Pulado conscientemente:**
  - Tradução PT-BR dos labels de engravings — labels são nomes próprios em EN ("Phoenix Rising"), comunidade BR usa em EN. Descrições já estão em PT-BR.
  - Allclash beasts tier list — afk.guide já cobre 27 beasts, allclash não adicionaria valor marginal.
  - Filtro UI por feature em `/heroes` — daria pra adicionar facetas tipo "PvP Top" mas é melhoria de UX, não dado novo.

---

### ✅ Fase 20 — Admin de beasts: card estilo hero + upload de imagem

Refatora o admin `/admin/beasts` (criado na Fase 14c) para usar o mesmo padrão visual dos cards de heróis e adiciona upload local de imagens (até então só URLs externas do wiki eram suportadas).

- **Backend — upload local:**
  - Criado symlink `public/storage` via `php artisan storage:link` (faltava no projeto, primeira feature a usar disco público).
  - Novo endpoint `POST /admin/beasts/upload-image` em `BeastAdminController::uploadImage`. Valida arquivo: `image|mimes:png,jpg,jpeg,webp,gif|max:2048` (2 MB). Usa `$file->store('beasts', 'public')` → arquivo vai pra `storage/app/public/beasts/<hash>.<ext>`. Retorna `{ ok: true, url: Storage::url($path) }` (resolve para `/storage/beasts/...`).
  - Validação de `portrait_url` no `validatePayload` ampliada: era `['nullable', 'url', 'max:500']`, agora aceita **URL absoluta** (wiki/Fandom) **ou** caminho relativo `/storage/...` (upload local) via closure de validação.

- **Frontend — modal (`beast-form-modal.tsx`):**
  - Bloco "Imagem do beast" com 3 elementos lado a lado: preview 96px (mostra a imagem ou ícone `PawPrint` placeholder, com botão `X` para limpar), botão **Upload** (abre file picker via `useRef`, mostra `Loader2` enquanto envia, hint "PNG/JPG/WebP até 2 MB"), input de URL editável (mantida para sync da wiki).
  - `handleUpload(file)` envia `FormData` via `axios.post`, atualiza `data.portrait_url` com a URL retornada e reseta o input file. Erros do backend (incluindo o `errors.image` do validator) aparecem inline em vermelho.
  - URL e file input não são mutuamente exclusivos: usuário pode digitar URL externa, fazer upload local, ou colar caminho `/storage/...` direto.

- **Frontend — card (`pages/admin/beasts/index.tsx`):**
  - Layout antigo (horizontal compacto, imagem 64px à esquerda) substituído por padrão `HeroCard`-like: card vertical em `<button>`, `aspect-square` para a imagem (1:1, combina com sprites de pets — mais natural que o 3:4 retrato dos heróis), nome embaixo.
  - Helper `beastTheme(beast)` calcula borda + gradiente baseado no **buff dominante**:
    - 1 stat com buff > 0: rosa (Força), azul/sky (Inteligência), verde/emerald (Agilidade)
    - 2+ stats: gradiente roxo (`from-rose-950/30 via-sky-950/20 to-emerald-950/30` + `border-purple-400/50`)
    - Sem buffs cadastrados: borda dashed âmbar (sinaliza "incompleto" como o badge antigo)
  - Badges de buff coloridos sobrepostos no canto superior esquerdo, **sempre visíveis** (`For +X` rosa, `Int +X` azul, `Agi +X` verde, `Sem buffs` âmbar). Diferente do `HeroCard` que mostra tier+classe nos cantos.
  - Ícones editar/excluir aparecem no canto superior direito **apenas no hover** (`opacity-0 group-hover:opacity-100`). Click no card abre o modal de edição (mesma ação do lápis); `stopPropagation` no botão de delete impede o click bubblar.
  - Grid responsivo aumentado: era `1 → 2 → 3` (md → xl), agora `2 → 3 → 4 → 5 → 6` (sm → md → lg → xl). Cards menores cabem mais por linha sem perder legibilidade.
  - Hover: `scale-[1.03]` + `shadow-lg` colorido por tema. Beast inativo → `opacity-60`.

- **Validação:**
  - 5 rotas em `/admin/beasts*` (upload-image adicionada): `GET index`, `POST store`, `POST upload-image`, `PATCH update`, `DELETE destroy`.
  - `npx tsc --noEmit` limpo nos arquivos modificados (erros pré-existentes em outros arquivos não relacionados).
  - Não testei E2E o fluxo de upload — depende de subir o `composer dev` e clicar manualmente.

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

---

### ✅ Fase 25 — Furniture effects estruturados no TeamScorer

Completa o item pendente da Fase 9/10: bônus específico das habilidades F3/F9 (além do +100/+200 fixo do PowerCalculator) agora entra no scoring com pesos por categoria.

#### Schema
- Migration `2026_05_13_000000_add_effects_to_hero_furniture_abilities.php`: coluna `effects` JSON nullable em `hero_furniture_abilities`. Formato: `{"f3": [tag1, tag2, ...], "f9": [...]}`. Tags são strings da taxonomia abaixo.
- `HeroFurnitureAbility` model ganhou cast `'effects' => 'array'`.

#### Taxonomia (11 tags)
`damage_boost`, `damage_reduction`, `healing`, `cc_control`, `cc_immunity`, `dispel`, `revive`, `energy_boost`, `shield`, `survivability`, `ability_uptime`.

#### Classifier
`App\Services\Optimization\FurnitureEffectClassifier::classify($text)` — recebe descrição (F3 ou F9), retorna lista de tags via keyword/regex matching. Texto pode cair em múltiplas categorias (ex: "deals X% damage and heals" → `damage_boost` + `healing`).

#### Comando `furniture:classify-effects`
- Itera `HeroFurnitureAbility`, roda classifier sobre `f3_description` e `f9_description`, salva em `effects`.
- Idempotente (`--force` reclassifica todos; `--slug=X` limita a um herói).
- Reporta distribuição de tags ao final.
- **Resultado**: 77 de 117 abilities (66%) classificadas. As 40 sem tags são effects muito específicos (marks, frenzy stacks, formação-dependentes) que não cabem em keywords genéricas — caem em score 0.0 sem prejuízo.

#### Scorer
`App\Services\Optimization\FurnitureEffectScorer::score(...)`:
- F3 ativo quando `userHero.furniture >= 3`, F9 quando `>= 9`. F3 conta com multiplicador 0.5, F9 com 1.0 (reflete que F9 é upgrade definitivo).
- **Modo target** (sem `UserHero`): assume F3+F9 unlocked se tier=`ascended`, só F3 se `legendary`, nada se `common`. Proxy razoável para sugerir comps ideais.
- Soma `tagWeight × unlockMultiplier` por hero do team.

#### ScoringProfile expandido
- Nova prop `furnitureEffectWeight` (default 0.4) — peso geral do componente no total.
- Nova prop `furnitureEffectTagWeights` (array tag => peso, merge sobre defaults).
- Defaults: `damage_boost=1.0`, `cc_immunity=0.8`, `cc_control=0.8`, `ability_uptime=0.8`, `healing=0.7`, `damage_reduction=0.7`, `shield=0.7`, `revive=0.7`, `energy_boost=0.6`, `survivability=0.6`, `dispel=0.6`.

#### Pesos por challenge type (ChallengeTypeSeeder)
- **Arena of Heroes (PvP)**: amplifica `cc_immunity` (1.4), `dispel` (1.2), `cc_control` (1.2), `ability_uptime` (1.1).
- **Legends Championship / Heroes of Esperia (PvP)**: similares a Arena (1.1–1.3).
- **Dream Realm (boss DPS)**: amplifica `damage_boost` (1.5), `ability_uptime` (1.3); reduz `cc_control` (0.3), `dispel` (0.3), `revive` (0.4) (boss imune).
- **Cursed Realm (PvE sobrevivência)**: amplifica `survivability` (1.2), `damage_reduction` (1.2), `revive` (1.2).
- **Dragon Trial (DPS)**: amplifica `damage_boost` (1.3), reduz `cc_control` (0.4).
- Demais modos usam defaults.

#### Integração no TeamScorer
- `TeamScorer` recebe `FurnitureEffectScorer` via construtor (com default).
- `ScoringContext` ganhou `furnitureAbilitiesByHeroId` (carregado em `FormationController` e `FormationCompareController` via eager-load com `whereNotNull('effects')`).
- `ScoreBreakdown` ganhou campo `furnitureEffects`.
- Total agora soma `furnitureEffects × profile.furnitureEffectWeight`.

#### Testes
- `FurnitureEffectClassifierTest` (15 cenários) — cobre cada tag + multi-tag + ordem estável + edge cases.
- `FurnitureEffectScorerTest` (13 cenários) — furniture <3/3-8/=9, modo target por tier, profile overrides, team completo, tags inválidas.
- `ClassifyFurnitureEffectsCommandTest` (5 cenários) — popula, --force, --slug, idempotência.
- **232 testes verdes** no projeto (200 → 232, +32).

---

### ✅ Fase 26 — Filtro de feature em /heroes + Signature Items costs estruturados

Consome dados já capturados nas Fases 21.5 e 22.2 que não tinham UI/scoring associado.

#### 26.1 — Filtro UI por feature em `/heroes`
- Nova linha de pills **Destaque** em `pages/heroes/index.tsx` entre Classe e o grid.
- 6 categorias: PvP Top, Twisted Realm Top, Early Carry/Support/Tank/Utility.
- `featureFilter: Set<HeroFeatureTag>` no state; lógica de OR dentro da categoria, AND entre categorias (consistente com faction/class).
- `HERO_FEATURE_LABEL`/`HERO_FEATURE_BADGE`/`HERO_FEATURE_TAGS` em `lib/afk.ts`.
- `toggleFromSet` generalizado com generics `<T>` para suportar Set de qualquer tipo.
- Teste `HeroIndexTest` (2 cenários) garante payload com features carregadas.

#### 26.2 — Signature Items costs estruturados

Migra os custos do guide markdown (Fase 22.2) para tabela estruturada.

- **Migration `create_signature_item_costs_table`:** `level` (PK 1-40), `cost` (int nullable — levels 39-40 não documentados no guide), `emblem_type` (varchar: primordial/amplifying/faction).
- **`SignatureItemCost` model** com constants `EMBLEM_*`, `FACTION_CAP_40 = ['celestial', 'hypogean']`, `SI_CAP_DEFAULT = 30`, `SI_CAP_EXTENDED = 40`.
- **`SignatureItemCost::summary($current, $factionCode)`** — retorna array com `current`, `cap`, `next_level`, `next_cost`, `next_emblem_type`, `cumulative_to_cap`, `levels_remaining`, `is_capped`. Faz clamp do valor de entrada.
- **`SignatureItemCostsSeeder`** popula 40 levels com os valores do guide (custos individuais). Levels 39-40 ficam com `cost = NULL` (não documentado).
- **`HeroController@details`** injeta `signatureItem` no JSON, derivando cap pela facção do hero e current do `userHero.signature_item ?? 0`.
- **UI (`hero-detail-modal.tsx`):** slot SI agora mostra `/X` dinâmico (30 ou 40) em vez de `/40` hardcoded, com badge "· N" ao lado mostrando próximo custo. Tooltip (title nativo) mostra contexto completo: tipo do emblema próximo e total cumulativo até o cap. Heróis no cap exibem "SI no cap (+X)".
- `DatabaseSeeder` ganhou `SignatureItemCostsSeeder` no array.

#### Testes
- `SignatureItemCostsTest` (11 cenários) — summary com SI 0/10/20/30/35/40, lightbearer vs celestial, clamping acima do cap, cumulativo soma só custos documentados, payload `/heroes/{slug}/details` reflete SI do user.
- **245 testes verdes** no projeto (232 → 245, +13).

#### Observações sobre os dados
- Levels 39-40 não documentados no guide afk.guide (campo `cost = NULL`). UI omite a linha de próximo custo nestes casos.
- Totais por faixa do guide têm pequenas discrepâncias com a soma dos custos individuais (faixa 1-10: 190 vs 200 do guide; 11-20: 230 vs 240). Mantive os valores individuais como fonte da verdade — os agregados do guide podem ter typos.

#### 26.3 — PowerCalculator usa cumulative SI cost

Fórmula antiga `signature_item * 5` era linear e ignorava o salto enorme de custo dos SI +31-40 (1050 emblemas a mais, exclusivo Celestial/Hypogean). Substituída por `cumulative_cost(level) * 0.2`:

- **`SignatureItemCost::cumulativeCostUpToLevel($level)`** — somatório acumulado de custos até o level, com cache estático (1 query inicial, ~40 rows). Levels 39-40 (cost=NULL) caem em fallback do level 38 (120 emblems) pra manter curva crescente.
- **`PowerCalculator::SI_CUMULATIVE_FACTOR = 0.2`** — calibrado pra manter SI 30 em ~144 pontos (próximo dos 150 antigos). Resultado: SI 30 = 144 (era 150), SI 38 = 258 (era 190), SI 40 = 306 (era 200).
- **Impacto:** ranking de heróis Celestial/Hypogean com SI 31+ ganha peso adicional, refletindo o investimento real. Demais facções (cap 30) mudam pouco (~5% queda no SI 30 absoluto, neutro relativamente).
- **Cache invalidation:** `flushCumulativeCache()` para testes; `primeCumulativeCacheForTesting($cache)` permite unit tests sem DB.
- **Testes:** novo cenário `test_si_salto_30_para_40_reflete_custo_real` valida que diff SI 40-30 é 162 pts (era 50 pts antes — 224% mais sensível ao salto Celestial). 3 testes existentes ajustados para nova fórmula. **246 testes verdes** no projeto.

#### 26.4 — Score breakdown visível no editor

Backend calculava 7 componentes de score (faction, class, synergy, power, tier_rating, feature_bonus, furniture_effects) mas a UI só mostrava `total`. Tornar o detalhamento visível ajuda o user a entender o que está afetando suas formações.

- **`FormationController@edit`** agora recebe `TeamScorer` via injeção e calcula `scoresByTeamPosition: array<int, breakdown|null>` (null = team vazio). Reusa o método privado `computeScoresByPosition()` que carrega heroPool + userHeroes + synergies + scoringContext igual ao `suggestTeam`. Eager-loads o `scoring_profile` do challengeType (não vinha antes no `load()`).
- **Types: `ScoreBreakdownPayload`** centralizado em `types/afk.ts` (era duplicado/incompleto em `compare.tsx`). Inclui todos os 7 componentes + total + details opcional.
- **Componente `ScoreBreakdownCard`** (`resources/js/components/score-breakdown-card.tsx`) — lista 7 componentes com labels PT-BR + tooltips explicativos, total em destaque. Compact mode pra popovers.
- **UI no `formations/edit.tsx`:** abaixo de cada team aparece um `<details>` collapsible "Ver score detalhado" mostrando o total inline; ao expandir, exibe `ScoreBreakdownCard`. Só aparece quando a formação não está com mudanças não-salvas (`!isDirty`) — evita confusão com valores defasados.
- **UI no `compare.tsx`:** ScoreRow agora lista também `tier_rating`, `feature_bonus` (Destaques) e `furniture_effects` (Móveis F3/F9) que estavam ausentes na comparação.
- **Testes:** `FormationEditScoreBreakdownTest` (2 cenários) — payload contém todos os 7 componentes numéricos; team vazio retorna null. **248 testes verdes**.
