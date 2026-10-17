# AFK Builder — Status de Implementação

Documento vivo do estado da aplicação. Atualizar conforme novas fases concluem.

> **Última atualização:** 2026-10-16 (Fases 59–61 — importação via OCR, múltiplas contas de jogo, deploy — e correções pós-auditoria)

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
  - **Faction bonus especial:** Hypogean recebe `count*0.1` (progressivo, qualquer quantidade); Draconis ganha `+0.3` quando 2+ na equipe (self-advantage). *(Removido na Fase 65.)*
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
| ~~5~~ | ~~melhoria~~ | ~~Mobile: drag-and-drop funciona via TouchSensor mas UX não foi otimizada para touch~~ — resolvido na Fase 29 (tap-to-add + touch targets ampliados). |

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

#### 26.5 — Sliders UI dos pesos no editor

Com o breakdown visível, fazia sentido permitir tunagem dos pesos para entender o impacto de cada componente. Implementação MVP client-side — sliders mexem em estado local, total é recalculado on-the-fly somando `component × weight`. Não muda o suggest (segue usando defaults do challenge_type).

- **Backend (`FormationController@edit`):** novo payload `scoringProfileDefaults` extraído via `extractScoringWeights()` helper. Retorna os 7 pesos (com fallbacks idênticos ao `ScoringProfile` PHP) — frontend usa pra slider defaults e botão reset.
- **Componente `WeightTunerSheet`** (`resources/js/components/weight-tuner-sheet.tsx`):
  - Sheet (drawer) lateral com 7 sliders (`<input type="range">`, sem dep nova) cobrindo cada peso (0.0 a 3.0, step 0.1).
  - Cada slider mostra valor atual e default; quando alterado, badge âmbar e botão ↶ para reset individual.
  - Botão "Resetar tudo" no footer.
  - **Persistência:** `localStorage` por `formation_id` (key `afk:weight-tuner:{id}`). Quando weights = defaults, limpa a entrada.
  - `onChange` callback dispara em cada movimento de slider — parent decide o que fazer.
- **`ScoreBreakdownCard` ganhou prop `weights?: WeightOverrides`**:
  - Quando passado, recalcula total client-side somando cada `component × weights[weightKey]`.
  - Cada linha exibe o multiplicador (×0.5, ×1.2, etc.) ao lado do valor raw.
  - Total fica em amber e mostra o valor default tachado ao lado para comparação.
- **Editor (`pages/formations/edit.tsx`):**
  - Botão **Pesos** no header desktop com badge "custom" quando weights ≠ defaults.
  - State `customWeights` + `weightsAreDefault` atualizados via callback do Sheet.
  - Total inline no `<details>` summary recalcula client-side com os pesos custom (texto em amber quando custom).
  - `ScoreBreakdownCard` recebe `weights` só quando ≠ defaults (evita ruído visual).
- **Limitações conscientes do MVP:**
  - Sliders só afetam **visualização**, não a sugestão automática. Suggest/SuggestAll continuam usando os defaults do challenge_type. Permitir overrides no backend = expansão futura.
  - Persistência só em `localStorage` (per browser, per formation). Sem sync entre devices.
- **Testes:** `test_edit_inclui_scoring_profile_defaults` valida os 7 pesos vindos do `ChallengeType::arena_of_heroes` no payload. **249 testes verdes**.

#### 26.6 — Suggest com pesos custom (fecha o ciclo de tunagem)

Extensão da 26.5: agora o `suggestTeam` e `suggestAll` aceitam `weight_overrides` no body e usam esses pesos para o optimizer. O ciclo de tunagem está completo — user mexe sliders, clica "Sugerir", recebe sugestão otimizada pelos pesos custom.

- **`ScoringProfile::withOverrides(array $overrides): self`** — método imutável que retorna nova ScoringProfile aplicando overrides nos 7 pesos. Demais campos preservados (pvp, dpsFocus, partitionOptimization, furnitureEffectTagWeights). Aceita strings numéricas (cast via float).
- **`FormationController@suggestTeam` e `@suggestAll`:**
  - Validação: `'weight_overrides' => ['nullable', 'array']` + `'weight_overrides.*' => ['numeric', 'min:0', 'max:3']` (mesma faixa do slider).
  - Quando presente e não vazio, aplica `$profile = $profile->withOverrides($data['weight_overrides'])` antes de chamar o optimizer.
  - Quando ausente, comportamento idêntico ao anterior (usa defaults do challenge_type).
- **Frontend (`pages/formations/edit.tsx`):** ambos `suggestForTeam()` e `suggestAll()` adicionam `weight_overrides: customWeights` no body quando `!weightsAreDefault`. Mesmo state da 26.5 (vindo do `WeightTunerSheet`).
- **Testes:**
  - `ScoringProfileWithOverridesTest` (4 cenários) — overrides parciais, preservação de flags/tagWeights, overrides vazio, string numérica.
  - `FormationSuggestWeightOverridesTest` (5 cenários) — suggest team aceita overrides, rejeita peso > 3 e < 0, suggest-all aceita, omitir usa defaults.
  - **258 testes verdes**.

#### 26.7 — Auto-scroll durante DnD

Fecha a pendência da SubFase 23.2 (linha 1357): durante arrasto de herói para fora da viewport, o scroll automático mantém o destino visível.

- **Hook reusável `useAutoScrollOnDrag`** (`resources/js/hooks/use-auto-scroll-on-drag.ts`):
  - Listeners globais `pointermove`/`touchmove` capturam Y do ponteiro.
  - `requestAnimationFrame` loop quando ativo; mede distância da borda (top/bottom) do container ou da viewport.
  - Velocidade proporcional: `((edgeThreshold - distance) / edgeThreshold) * maxSpeed` em px/frame.
  - Defaults: `edgeThreshold = 80px`, `maxSpeed = 18px/frame`.
  - API: `setActive(true|false)` — controlado pelo consumidor via `onDragStart`/`onDragEnd`/`onDragCancel`.
  - Cleanup automático no unmount + ao desativar.
- **Integrado em:**
  - `pages/tier-lists/edit.tsx` (caso original da pendência — listas verticais grandes com vários tiers).
  - `pages/formations/edit.tsx` (DnD principal do app — pool de heróis × slots de team).
- **Decisão:** scroll de viewport (window) em vez de container específico. Editor de formação tem layout fluido (`overflow-y-auto pr-1 pb-20`) onde a barra de scroll do navegador é a referência prática. Aceita customização via prop `container` no hook se algum caso futuro precisar.
- Cobertura: ambos os DnD agora suportam arrastar pra baixo/cima da viewport sem perder o destino. Mobile já chega via `touchmove` listener.

---

### ✅ Fase 27 — Weight overrides persistidos no DB

Conclui o ciclo de tunagem aberto pelas Fases 26.5/26.6. Os pesos custom agora persistem no DB por formação, não só em localStorage. Resolve "sem sync entre devices" e "perde tudo se limpar cache".

#### Schema
- Migration `2026_05_13_020000_add_scoring_weights_override_to_formations.php`: coluna JSON nullable `scoring_weights_override` em `formations` (após `total_score`).
- `Formation` model: cast `'scoring_weights_override' => 'array'` + fillable.

#### Endpoint `PATCH /formations/{id}/scoring-weights`
- `name=formations.scoring-weights.update`
- Body: `{ weights: { power_weight: 1.5, ... } | null | {} }`
- Validação: `weights` é nullable|array; cada `weights.*` é numeric|min:0|max:3 (mesma faixa do slider e dos suggest endpoints).
- Comportamento: array vazio ou null → limpa o override (volta a usar profile do challenge_type).
- 403 quando outro user tenta editar.
- Retorna `{ scoring_weights_override: <atualizado|null> }`.

#### Backend `FormationController@edit`
- Novo payload `scoringProfileOverrides` (vindo do DB; null quando não setado).
- `computeScoresByPosition()` aplica overrides salvos via `withOverrides()` antes de calcular breakdowns — o user vê o score já refletindo seus pesos.

#### Backend `suggestTeam` / `suggestAll`
- Prioridade clarificada: `body.weight_overrides > formation.scoring_weights_override > defaults`.
- Permite preview com pesos não-salvos (body) sem invalidar os salvos no DB.
- Quando body omite, suggest usa os salvos (consistência com o que aparece no breakdown).

#### Frontend
- `WeightTunerSheet` ganhou prop `savedOverrides?: Partial<WeightOverrides> | null` e callback `onSave?`.
- Inicialização: `savedOverrides do DB > localStorage (compat 26.5) > defaults`.
- Botão **Salvar como padrão** no footer:
  - Ícone alterna `Save → Loader2 (saving) → Check (salvo)`.
  - Habilitado só quando weights ≠ `savedOverrides ?? defaults`.
  - Texto "Salvo" + ícone ✓ quando state == persisted.
- `pages/formations/edit.tsx::saveScoringWeights()` chama `PATCH`, atualiza `savedOverrides` local, e dispara `router.reload({ only: ['scoresByTeamPosition', 'scoringProfileOverrides'] })` pra refresh dos breakdowns.

#### Testes
- `FormationScoringWeightsTest` (8 cenários): persistência, omitir/array vazio limpa, peso > 3 / negativo rejeitado, 403 cross-user, payload `edit` inclui overrides quando salvos e null quando não.
- **266 testes verdes** (+8 novos).

---

### ✅ Fase 28 — Dashboard com estatísticas expandido

O dashboard tinha 6 stats básicas (roster total, ascendidos, despertos, catálogo, facções, formações). Agora ganha milestones de investimento, distribuições por classe/tier, top 5 power e heroes destaque do roster.

#### Novas queries no `DashboardController@index`
- **`rosterByClass`** — agrupa user_heroes por hero_classes.code (tank/warrior/ranger/mage/support), ordena por display_order.
- **`rosterByTier`** — agrupa por tiers.code (common/legendary/ascended).
- **Milestones** (`stats.*`): `roster_si_30` (`signature_item >= 30`), `roster_si_40` (cap Celestial/Hypogean), `roster_f9` (`furniture >= 9`), `roster_eng_80` (`engraving >= 80`).
- **`topPowerHeroes`** — top 5 do roster por `PowerCalculator::forUserHero()`. Eager-load completo (faction, class, tier, stats, artifact, collection) pra evitar N+1.
- **`featuredInRoster`** — heroes do roster que têm tag PvP Top ou Twisted Realm Top (Fase 21.5). Agrupado por tag, com slug/name/portrait para link de navegação.

#### UI no `pages/dashboard.tsx`
- 2ª linha de StatCards (milestones): SI +30, Móveis F9, Inscrições 80+, Top power.
- Seção "Distribuição por classe" e "Distribuição por tier" lado a lado, com barras de progresso percentuais.
- Seção "Top 5 power" — cards horizontais com posição (#1-5), portrait, badges (Nv/SI/F), e número de power formatado em pt-BR. Linka pra `/heroes?open={slug}`.
- Seção "Heroes destaque no roster" — 2 cards (PvP Top + Twisted Realm Top) com chips clicáveis. Mostra mensagem amigável quando vazio ("boa oportunidade de progressão").

#### Testes (`DashboardTest`)
- 2 cenários novos:
  - `test_payload_inclui_estatisticas_novas_e_milestones` — fixtures: 4 heroes diversos (SI 30/40, F9, Eng 80+), 2 com features. Valida contagens, distribuições, top 5, featured agrupado por tag.
  - `test_roster_vazio_retorna_distribuicoes_vazias` — edge case do user novo sem roster.
- **268 testes verdes** (+2 novos).

#### Performance
- Top 5 power é calculado em PHP (não SQL) porque a fórmula combina ascension, level, SI cumulativo, equipamentos, artifact/collection, stats. Hoje 100% do roster cabe em memória; se passar de ~500 heroes (impossível no jogo) viraria gargalo — então deixei como está sem cache.

---

### ✅ Fase 29 — Mobile DnD touch UX

Fecha a issue conhecida #5 (linha 1560 da tabela acima). Em telas `< lg`, o pool fica num bottom-sheet acessível via FAB (Fase 18.2). O DnD funcionava via `TouchSensor`, mas exigia drag-precision em targets pequenos. Agora há **tap-to-add** como alternativa rápida que não conflita com o drag.

#### Tap-to-add no `HeroPool`
- `HeroPool` ganhou prop opcional `onTapHero?: (heroId: number) => void`.
- `DraggableChip` ganhou prop `onTap` — quando definida e o chip não está desabilitado/sendo arrastado, dispara via `onClick`.
- **Não conflita com DnD:** o `TouchSensor` já está configurado com `activationConstraint: { delay: 150, tolerance: 5 }`. Um tap rápido (< 150ms) cai no `onClick`; um press-and-hold dispara o drag. Cursor muda pra `cursor-pointer` (com `active:scale-95`) quando há `onTap` — feedback visual de que é tocável.
- Chip ganha `role="button"` e `aria-label` ("Adicionar X ao próximo slot") quando tap está ativo.

#### Função `addHeroToTeam(heroId, teamPos)` no editor
- Encontra primeiro slot vazio da team alvo. Se cheia, retorna `false`.
- Respeita `enforceUnique`: se o herói já está em outra team, faz swap (remove do antigo, adiciona no novo).
- Se já está nesta team, no-op.
- Marca `isDirty=true` apenas quando há mudança real.

#### `handleTapHero` com fallback inteligente
- Tenta a team alvo selecionada no segmented control.
- Se cheia, faz fallback pra primeira team com slot livre (atualiza o segmented control também).
- Fecha o `Sheet` ao adicionar com sucesso.

#### Segmented control no Sheet (multi-team)
- Quando `teams.length > 1`, header do Sheet mostra botões "Equipe N · M/5" pra cada team.
- Team cheia (5/5) fica disabled (opacity 50, cursor not-allowed).
- Team ativa highlight (`bg-primary text-primary-foreground`).
- Mensagem de instrução: "Toque num herói para adicioná-lo ao próximo slot livre, ou arraste pra um slot específico."
- Botões com `min-h-9` (36px) para touch confortável.

#### Touch targets ampliados
- **Botão X de remover no `TeamSlot`**: era `size-5` (20×20px) — abaixo do mínimo WCAG. Agora `size-7` (28×28) em mobile + `size-5` em `lg:`. Posição offset `-top-2 -right-2` em mobile (era `-1.5`) pra acomodar o tamanho.
- **Pills de filtro de facção/classe no `HeroPool`**: ganharam `min-h-7` (28px) e `py-1` em mobile (eram `py-0.5` = ~18px de altura total). Em `lg:` mantêm o look compacto original.

#### Trade-offs
- **Por que tap, não duplo-tap?** Duplo-tap exige timing humano (>200ms typical) e tem latência percebida. Tap simples + cursor pointer + active:scale-95 dá feedback imediato. O risco de tap acidental enquanto rolando é mitigado pelo Sheet bottom (90% da área é o pool, scroll vertical funciona normalmente em `touch-none` porque o gesture só captura durante drag de fato).
- **Por que segmented control e não dropdown?** Em ~3-4 teams (cenário típico), pills são mais rápidos: 1 toque vs 2 (abre + escolhe). Acima disso o wrap rola naturalmente.
- **Por que fechar o Sheet automaticamente após tap?** Pra que o usuário veja o slot recém-preenchido antes de decidir adicionar mais. Reabrir é 1 toque no FAB.

#### Testes
- Sem novos testes — toda a lógica é client-side React; backend não mudou. Os **268 testes PHPUnit continuam verdes**.
- Validado manualmente em DevTools mobile emulation (iPhone/Android). Falta confirmação em device físico.

#### Issue conhecida fechada
- Issue #5 da tabela "Issues conhecidos" foi marcada como resolvida — tabela agora tem 3 issues abertas + 2 fechadas.

---

### ✅ Fase 30 — Presets de pesos reutilizáveis

Complementa as Fases 26.5 e 27. Antes, os pesos custom viviam só na formação corrente: criar outra formação significava re-tunar do zero. Agora o user pode salvar configurações nomeadas (ex: "PvP agressivo", "DPS Dream Realm") e aplicá-las em qualquer formação compatível.

#### Schema
- Migration `create_scoring_weight_presets_table`: `id`, `user_id` FK cascade, `name` (string 60), `challenge_type_id` FK nullable (null = global), `weights` JSON, timestamps. Unique composto `(user_id, name)` impede duplicidade de nome por user. Index `(user_id, challenge_type_id)` para query rápida do payload de edit.
- Model `ScoringWeightPreset` com `WEIGHT_KEYS` const (whitelist das 7 chaves do scoring), cast `weights => array`, relations `user()` e `challengeType()`.
- `User` model ganhou `scoringWeightPresets()` hasMany.

#### Controller (CRUD)
- `ScoringWeightPresetController` com 4 endpoints:
  - `GET /scoring-weight-presets` (index) — retorna apenas presets do user autenticado, ordenados por nome.
  - `POST /scoring-weight-presets` (store) — cria. Valida nome único por user via `Rule::unique` (defesa em profundidade junto com o unique do schema).
  - `PATCH /scoring-weight-presets/{preset}` (update) — atualiza. Ownership via `abort_unless($preset->user_id === $request->user()->id, 403)`.
  - `DELETE /scoring-weight-presets/{preset}` (destroy) — idem ownership.
- **Validação dos pesos:** itera `WEIGHT_KEYS` e adiciona `weights.{key} => ['nullable','numeric','min:0','max:3']` ao array de regras. Chaves desconhecidas no payload são **silenciosamente ignoradas** pelo `validate()` — não rejeitam o request, simplesmente não chegam ao `$data`. Isso evita 422 hostis se o frontend mandar campos legados.

#### Payload do `FormationController@edit`
- Agora retorna `weightPresets`: presets filtrados ao `user_id` da requisição **+ scope** (globais OU específicos do `challenge_type_id` da formação). Presets de outros challenge types ficam fora — evita lista poluída.

#### UI no `WeightTunerSheet`
- Bloco de presets adicionado no topo do Sheet, antes dos sliders:
  - **Dropdown "Presets"** com a lista. Cada item mostra `name` + sub-label "Global" ou "Específico do desafio". Botão lixeira inline em cada item (com `confirm()` antes de deletar).
  - **Item "Salvar pesos atuais como preset…"** abre um mini-form inline: campo de nome (autofocus) + segmented control "Global" vs "Só para este desafio" + erro inline. Mensagem de erro do backend (ex: nome duplicado) é exibida na própria caixa.
- Estado dos presets é controlled pelo componente pai (prop `presets` + callback `onPresetsChange`) pra que o pai recarregue Inertia se quiser.
- Click num preset substitui imediatamente os sliders pelos pesos salvos; `onChange` dispara dirty se diferente do default.
- Props novas: `challengeTypeId` (pra criar preset escopado), `presets`, `onPresetsChange`.

#### Trade-offs registrados
- **Por que `confirm()` nativo do browser pra deletar?** Decisão de não construir um `AlertDialog` separado: a operação é reversível (basta re-criar o preset, são 7 números), e o fluxo já está dentro de um Sheet — empilhar mais um modal/sheet ficaria pesado em mobile. Se o user pedir desfazer no futuro, dá pra trocar.
- **Por que filtrar presets de challenge types diferentes do dropdown do edit?** Um preset de "Cursed Realm" não é semanticamente útil quando a formação é "Campaign" (os defaults do scoring profile são radicalmente diferentes — boost de `survivability` perto de zero em Campaign vs 1.2 em Cursed). Reduz ruído. O user ainda pode aplicar manualmente via apply-to-any se quiser, mas a discoverability deliberadamente prioriza presets compatíveis.
- **Por que validar chave-por-chave em vez de `weights.* => numeric|max:3`?** A wildcard pegaria `weights.malicious_key = 99` e respondería 422 com erro hostil. Validar nome explícito ignora silenciosamente — futuro frontend pode mandar campos extras sem quebrar.
- **Por que `name` único só por user (não global)?** Cada user tem mental model próprio (`"PvP"` pra mim significa Arena, pra você pode ser Esperia). Forçar nomes globais únicos exigiria namespace (`@user/PvP`) sem ganho.

#### Testes (`ScoringWeightPresetTest`)
- 12 cenários:
  - `lista_apenas_presets_do_user` — isolamento básico
  - `cria_preset_valido` — happy path
  - `rejeita_nome_duplicado_no_mesmo_user`
  - `dois_users_podem_ter_presets_com_mesmo_nome` — scope correto do unique
  - `rejeita_peso_fora_do_range` (5.0 → 422)
  - `rejeita_challenge_type_inexistente`
  - `filtra_pesos_invalidos_silenciosamente` — `malicious_key=99` aceita, `weights` final só tem 7 chaves
  - `atualiza_preset_proprio`
  - `nao_atualiza_preset_de_outro_user` — 403
  - `deleta_preset_proprio`
  - `nao_deleta_preset_de_outro_user` — 403
  - `edit_payload_inclui_presets_filtrados_por_challenge_type` — Global + Campaign-specific visíveis; Arena-only fora
- **280 testes verdes** (+12 novos).

---

### ✅ Fase 31 — Estatísticas de uso de heróis

Nova página `/heroes/usage` que cruza roster com formações pra revelar **onde está seu investimento real** vs **onde estão suas escolhas táticas**. Útil pra revisar se heróis com SI alto realmente justificam o investimento ou se estão parados no banco.

#### Controller `HeroUsageController@index`
4 queries cruzando `formation_team_heroes` → `formation_teams` → `formations` filtrado por `user_id`:

1. **Top 10 mais utilizados** — `GROUP BY hero_id, COUNT(*) team_count, COUNT(DISTINCT formation_id) formation_count, COUNT(DISTINCT challenge_type_id)`. Desempate: team_count > formation_count.
2. **Roster nunca usado** — `array_diff(rosterHeroIds, usedHeroIds)`. Mostra os primeiros 50; expõe `never_used_total` no header. Inclui `level`, `signature_item`, `furniture`, `engraving` do `UserHero` pra contexto.
3. **Investidos + esquecidos** (red flag) — UserHeroes com `signature_item ≥ 20 OR furniture ≥ 3 OR engraving ≥ 60` e zero uso. Sort interno: `SI × 10 + furniture × 50 + engraving` (furniture pesa mais porque é o investimento de "endgame deliberado"). Limite 10.
4. **Top 5 por challenge type** — join adicional em `challenge_types`. Agrupa por code, ordena por team_count dentro de cada bucket. Mostra apenas challenge types onde o user tem formações (challenge types não usados ficam fora).

Eager-load completo das relations `faction:id,code,name_pt`, `heroClass:id,code,name_pt`, `tier:id,code,name_pt` numa única query por seção.

#### UI `pages/heroes/usage.tsx`
Layout em seções verticais (mobile-friendly):
- **4 StatCards** no topo: heróis utilizados, roster total, nunca usados (tone amber se > 0), investidos+esquecidos (tone rose se > 0).
- **Top 10 mais utilizados** — grid 2 cols com #rank, avatar, contador "N times em M formações" + "X desafios" quando > 1, badge count grande na direita.
- **Investidos + esquecidos** (só aparece se houver) — card destacado em rose-950 com avisos visuais. Subtexto explica o critério.
- **Top 5 por tipo de desafio** — grid 2 cols, cada bucket é um card com nome do challenge type + lista compacta dos 5.
- **Nunca usados no roster** — grid 2→6 cols com avatar central + nome + tier badge. Mostra header com "Mostrando os 50 primeiros de N" quando aplicável.

Todos os cards linkam pra `/heroes?open={slug}` (deep-link pro modal de detalhes existente).

#### Sidebar
Item novo "Uso de heróis" com ícone `BarChart3` posicionado entre "Heróis" e "Formações" — agrupamento natural (todos relacionados ao roster).

#### Trade-offs
- **Por que `IN_FORMATION` e não "in active formation"?** Não existe conceito de "formação ativa" no app — qualquer formação salva conta. Se o user limpar formações antigas, o reset é automático.
- **Por que mostrar `never_used_total` mas só listar os 50 primeiros?** Em rosters acima de 150 heróis, listar tudo polui a tela sem agregar (o usuário não vai escanear todos). O total no header diz "tem N esperando você" sem entrar em cada um.
- **Por que SI ≥ 20 OR F ≥ 3 OR Eng ≥ 60 como threshold de "investido"?** SI 20 = ~360 cumulativos (já é gasto sério); F3 = primeiro F bonus; Eng 60 = level 2 fora de free. Heroes abaixo disso são roster casual — não são "investimento desperdiçado", só "ainda não trabalhado".
- **Por que sort do invested_forgotten dá `furniture × 50` peso?** Furniture é o eixo mais caro e mais lento de subir; um F9 idle representa muito mais "dor" que um SI 30 idle.

#### Testes (`HeroUsageTest`)
9 cenários cobrindo:
- `guest_redirecionado_para_login` + `autenticado_acessa_pagina` — autenticação básica
- `top_used_conta_corretamente` — hero em 2 formações de 2 challenges diferentes
- `never_used_lista_heroes_do_roster_fora_de_formacoes` — exclui hero usado
- `invested_forgotten_destaca_heroes_com_si_alto_sem_uso` — só "rich" entra, "basic" fica fora
- `hero_em_formacao_nao_aparece_em_invested_forgotten` — mesmo com SI 40 + F9 + Eng 80
- `usage_by_challenge_type_agrupa_e_ordena_por_uso` — campaign tem heroA (2×) > heroB (1×), arena só heroB
- `payload_isola_dados_por_user` — formação do userA não vaza pro userB
- `roster_vazio_retorna_estados_vazios` — edge case

**289 testes verdes** (+9 novos).

---

### ✅ Fase 32 — Notas em formações (UI completa)

Fecha o ciclo da feature de notas: schema (`formations.notes TEXT NULL`), validação backend (`max:2000`) e render público (`whitespace-pre-wrap`) já existiam desde a Fase 4, mas **faltava a UI de edição** — o campo era sempre `null` na prática. Agora há editor markdown com preview e o render público usa o renderizador markdown completo do app.

#### Novo componente `FormationNotesSheet`
- Sheet lateral (`side=right`, `sm:max-w-xl`) com 2 modos via segmented control:
  - **Editar**: `<textarea>` mono-fonte 12px, autosize via flex; placeholder com exemplo realista de estratégia (`# Estratégia, ## Quando usar, ## Riscos`).
  - **Preview**: `<MarkdownContent>` (mesmo componente usado em guides, lore e habilidades) — assim o user vê exatamente o que verá quem abrir o link público.
- Contador de caracteres `N / 2000` no rodapé; vira destrutivo + bold acima do limite.
- **Modo controlled opcional**: aceita `open` + `onOpenChange` props e `hideTrigger`. Permite a mesma instância ser acionada por múltiplos triggers (ex: botão desktop + item de dropdown mobile) sem duplicar o sheet.

#### Integração no editor
- State `notes` controlado em `formations/edit.tsx`, inicializa com `formation.notes ?? ''`.
- Botão "Notas" no header desktop (ícone `FileText`) com **bolinha amber** indicando presença de conteúdo.
- Item "Notas" no `DropdownMenu` mobile (mesma indicação de bolinha).
- Submit: envia `notes.trim() === '' ? null : notes` — string vazia vira `null` no DB (consistente com nullable column).
- Marca `isDirty=true` ao alterar conteúdo.

#### Render público melhorado (`formations/public.tsx`)
- Substituído `<p className="whitespace-pre-wrap">` por `<MarkdownContent>` dentro de um container `rounded-lg border bg-muted/30 p-3`.
- Markdown formatado (headers, listas, bold/italic, links, tabelas via GFM) renderiza com o mesmo design dos guias.

#### Trade-offs
- **Por que apenas 1 instância controlled em vez de 2 com triggers internos?** Duas instâncias significariam 2 estados de `open` que poderiam conflitar (abrir os 2 simultaneamente, race conditions ao escrever). Uma instância + prop `hideTrigger` mantém SSOT.
- **Por que segmented control em vez de split-pane lado a lado?** Em mobile, split-pane fica claustrofóbico (~150px de cada lado). Segmented dá 100% da largura pra cada modo. Em desktop seria possível split, mas optei por consistência cross-device.
- **Por que não autosave no `onChange`?** O save da formação já é manual (botão "Salvar" no header). Autosave separado pra notes criaria 2 fluxos de salvamento — confuso.
- **Por que aceitar markdown e não rich text editor?** Markdown é portável, copy-pastable, sem JS adicional de WYSIWYG (~100KB+ de bundle). Public render já usa `react-markdown` (instalado pra guias). Custo marginal de bundle: zero.

#### Testes (`FormationNotesTest`)
6 cenários:
- `update_aceita_notas_markdown` — round-trip de markdown via `PATCH /formations/{id}`
- `update_aceita_notes_null` — clear funciona
- `update_rejeita_notes_acima_de_2000_chars` — validação `max:2000`
- `pagina_publica_mostra_notas` — `/f/{token}` retorna `notes` no payload
- `edit_payload_inclui_notes_atuais` — `/formations/{id}/edit` carrega `notes` pré-existentes
- `outro_user_nao_atualiza_notes` — ownership 403

**295 testes verdes** (+6 novos).

---

### ✅ Fase 33 — Filtros avançados em /formations

Antes a index apenas listava ordenada por `updated_at`. Com muitas formações fica difícil achar a certa. Agora há busca + filtros por atributos.

#### Decisão: filtragem client-side
Formações escalam pouco por user (típico: 10-50, raro >100). Trazer todas + filtrar em memória é mais responsivo (zero round-trip ao filtrar) e mais simples (estado local, sem URL state sync).

#### Backend (`FormationController@index`)
- Payload da formação agora inclui `beast_id`, `share_token`, `notes`, mas:
  - **`notes` não vai no JSON** — usa `makeHidden(['notes'])` e expõe um campo derivado `has_notes: bool` via `setAttribute`. Privacy + tamanho de payload.
  - `has_notes` é `true` quando `trim($notes) !== ''` — strings whitespace-only contam como vazias.
- Novo campo `availableChallengeTypes` — lista deduplicada dos challenge types que o user **realmente tem formações**. Evita poluir o filtro com 14 desafios quando o user só usa 2.

#### UI (`pages/formations/index.tsx`)
- **`FilterBar` inline** com 3 linhas:
  1. **Busca por nome** — input com ícone Search; case-insensitive `String.includes`.
  2. **Pills de challenge type** (só aparece se houver >1 disponível) — multi-select; OR dentro do grupo.
  3. **CycleButtons** — botões "ciclo de 3 estados" (Todos → Sim/Com → Não/Sem → Todos) para `mode`, `hasBeast`, `hasShare`, `hasNotes`. Compacto e mobile-friendly.
- **Empty states diferenciados**:
  - 0 formações totais → "Crie sua primeira" + botão criar
  - 0 formações pós-filtro → "Nenhuma casa" + botão "Limpar filtros"
- **Header dinâmico**: "X de Y formações" quando há filtros ativos, "N formações" caso contrário.
- **Badges nos cards**: Adicionados Beast (emerald), Pública (sky), Notas (amber) — feedback visual de quais campos cada formação tem.

#### Trade-offs
- **Por que client-side e não query params?** Volumetria baixa + UX instantânea (sem flash de loading). Trade-off: filtros não persistem entre páginas/refresh. Para volume maior valeria server-side com URL state.
- **Por que CycleButton de 3 estados em vez de checkbox/select?** Compacto (1 toque cicla), espaço-eficiente em mobile, e bool ternário (Sim/Não/Todos) cobre exatamente o caso. Checkbox seria menos preciso ("desmarcar" não significa "filtrar pelo oposto").
- **Por que `availableChallengeTypes` baseado no que o user tem?** Mostrar 14 challenge types ao filtrar quando o user só tem formações em 2 polui — escolha de UI que prioriza signal sobre exhaustividade.
- **Por que `makeHidden(['notes'])`?** O conteúdo da nota pode ser ~2KB e a index tem N formações. Para um user com 50 formações + nota cheia, isso seria 100KB extras desnecessários (filtragem só precisa saber se existe). E expor notas privadas no payload da index sem o user pedir é desnecessário.

#### Testes (`FormationIndexTest`)
5 cenários:
- `index_retorna_campos_necessarios_para_filtros` — `beast_id`, `share_token`, `has_notes` presentes; **`notes` ausente** do payload
- `has_notes_e_false_quando_notas_estao_vazias_ou_whitespace` — null, "   \n\t  ", e "algo" tratados corretamente
- `available_challenge_types_inclui_apenas_os_com_formacoes` — campaign + arena_of_heroes presentes; dream_realm ausente
- `available_challenge_types_deduplica` — 3 formations no mesmo type → 1 entry
- `outro_user_nao_ve_formacoes` — isolamento (também testa `availableChallengeTypes` vazio)

**300 testes verdes** 🎯 (+5 novos, marco redondo).

---

### ✅ Fase 34 — Diff visual em /formations/compare

A página `/formations/compare/{a}/{b}` (existente desde Fase 11) já mostrava scoring lado a lado com vencedor em verde por linha. Agora há **análise diff real**: delta numérico, barras de progresso, contadores de "wins por componente", e quais heróis são exclusivos vs comuns.

#### `ScoreRow` reformulado
- **Antes**: 1 linha com `a | label | b`, vencedor em verde.
- **Agora**: 2 linhas empilhadas:
  1. Valores numéricos com ícone `+` no vencedor.
  2. **Barra de progresso ratio** (proporção visual de A vs B) com **Δ delta** no centro, ou "=" em caso de empate.
- Suporta prop `bold` pra destacar Total.

#### Resumo de componentes vencedores (card global)
Cálculo agregado sobre todos os teams: para cada um dos 7 componentes, soma o valor across teams e compara A vs B. Mostra:
- "A venceu: N/7" (sky)
- "Empate: N/7" (muted)
- "B venceu: N/7" (amber)

Texto de leitura: *"{Nome} tem total +X.XX pontos a mais"* quando há winner total.

#### Composição: comum vs exclusivos
Card global com 3 grupos:
- **Só na A** (sky border) — heroes que só aparecem em formação A
- **Em ambas** (emerald border) — heroes presentes nas 2
- **Só na B** (amber border) — heroes que só aparecem em formação B

Cada grupo renderiza chips com `HeroChip` size sm. Empty state minimalista quando vazio.

#### Highlight nos teams
Heróis exclusivos ganham anel colorido (`ring-2 ring-sky-400` ou `ring-amber-400`) com `ring-offset` pra destacar do card. Tooltip `title` explica "Exclusivo de A/B".

#### Cabeçalho das 2 formações
- Cada card agora tem **badge A/B** (sky/amber) no topo pra identificação rápida.
- O card da **formação com maior total** ganha background tonal (`bg-sky-500/5` ou `bg-amber-500/5`) + borda + badge **"Total maior"** com ícone Trophy.

#### Trade-offs
- **Wins por componente: soma ou média?** Soma é proxy razoável quando teams têm tamanhos similares. Média ponderada por team-size daria igual peso a teams pequenos/grandes, o que é menos intuitivo. Soma reflete "presença efetiva" do componente.
- **Por que mostrar Δ pequenos como Δ0.00 e não "−"?** Diferenças <0.01 são raras mas existem em float arithmetic; mostrar "Δ0.00" preserva consistência visual em vez de criar regra especial.
- **Por que sky/amber pra A/B em vez de blue/red?** Vermelho seria interpretado como "ruim/perda" mesmo quando B venceu — confunde semântica. Sky/amber são neutros, distintos e legíveis em ambos temas.
- **Por que destacar heroes exclusivos com ring em vez de opacity nos comuns?** Ring é aditivo (chama atenção pro que importa); opacity dim seria subtrativo (apaga informação útil). Para diff, "o que é diferente" é o sinal valioso.

#### Sem testes novos
A Fase 11 já cobria o payload backend (`FormationCompareTest`: 3 cenários verificando scores por equipe + ownership). O diff é puramente visual — toda a lógica é `useMemo` sobre dados que já existem. **300 testes verdes mantidos**.

---

### ✅ Fase 35 — Tags em formações (M2M)

Tags livres por user pra categorizar formações ("counter-Twins", "speedrun", "safe", "PvP-rank-1"). Complementa os filtros booleanos da Fase 33 com taxonomia personalizada.

#### Schema
- **`formation_tags`** (escopo por user): `user_id` FK cascade, `name` (string 30), `color` (string 16, default `'amber'`). Unique `(user_id, name)` — mesmo nome OK entre users diferentes.
- **`formation_tag_assignments`** (pivot): `formation_id` + `formation_tag_id` ambas FK cascade. Unique `(formation_id, formation_tag_id)` + index em `formation_tag_id`. **Cascata bilateral**: deletar formação ou tag remove o assignment automaticamente.

#### Cores fixas (whitelist)
- 8 cores pré-definidas: `amber, sky, emerald, rose, violet, orange, fuchsia, slate`.
- Validação backend via `Rule::in(FormationTag::COLORS)`.
- Mapeamento centralizado em `components/formation-tags-menu.tsx#COLOR_CLASSES` com 4 variantes (`bg`, `text`, `border`, `dot`) por cor — reutilizável em qualquer lugar que renderize uma tag via helper exportado `tagColorClasses()`.

#### Controller `FormationTagController` (CRUD)
- `GET /formation-tags` — lista as do user ordenadas por nome.
- `POST /formation-tags` — cria. Validação: nome único por user, cor da whitelist (default amber).
- `PATCH /formation-tags/{tag}` — renomeia/troca cor. Ownership 403.
- `DELETE /formation-tags/{tag}` — apaga. Pivot cascata remove assignments automaticamente.

#### Sync via `FormationController.update`
- Aceita `tag_ids` array opcional. Validação:
  ```php
  'tag_ids.*' => ['integer', Rule::exists('formation_tags', 'id')->where('user_id', $request->user()->id)],
  ```
  — bloqueia attach de tag de outro user (defense in depth contra IDs adivinhados).
- Usa `$formation->tags()->sync($data['tag_ids'] ?? [])` — full-replace dentro da transação do update existente.
- `array_key_exists` checa se o frontend mandou o campo — `null` ou ausente preservam o estado atual; `[]` limpa.

#### Componente `FormationTagsMenu`
- DropdownMenu (não Popover — Popover não existia no projeto) com:
  - **`DropdownMenuCheckboxItem`** por tag, com `onSelect={(e) => e.preventDefault()}` pra manter o menu aberto após toggle (UX de "marcar várias" sem reabrir).
  - Cada item: dot colorido + nome + botão lixeira inline (com `confirm()` antes de deletar — operação destrutiva).
  - Item final "Criar nova tag" expande inline com input (max 30 chars), 8 swatches de cor (size-5, ring no ativo), Enter pra salvar, Escape pra cancelar.
- **Auto-add ao criar**: tag recém-criada já é adicionada à seleção ativa do user (UX intuitiva — quem criou queria usar).

#### Integração no editor
- `FormationTagsMenu` no header desktop com badge contador `{N}` quando há tags selecionadas.
- Tags selecionadas aparecem como badges coloridas na "Linha 2" do header (junto com challenge type, mode, etc).
- `tag_ids: selectedTagIds` enviado no payload do save.
- Marca `isDirty=true` ao alterar seleção.

#### Filtro na index
- Bloco "Tags:" no FilterBar (só aparece quando `availableTags.length > 0`).
- Multi-select com OR — formação casa se tem **pelo menos uma** das tags filtradas.
- Tags ativas no filtro ganham `ring-1 ring-current` pra distinção visual.
- Tags renderizadas também como badges no card da formação (entre badges de propriedades e data).

#### Trade-offs
- **Por que cores fixas e não color picker arbitrário?** Theme consistency com o design system (cada cor já mapeia pras 4 classes Tailwind necessárias). Color picker exigiria 4× mais classes geradas dinamicamente ou inline styles — bagunça.
- **Por que filtro OR e não AND entre tags?** OR cobre o caso comum ("mostre as que são X **ou** Y"). AND seria menos descobrível ("mostre as que são X **e** Y simultaneamente" — raro). Pode virar AND no futuro com toggle se demanda aparecer.
- **Por que `name` único por user e não global?** Mesma lógica dos presets da Fase 30 — cada user tem mental model próprio. "speedrun" pra mim pode significar Dream Realm, pra outro Cursed Realm.
- **Por que `confirm()` nativo pra deletar?** Operação destrutiva mas barata (recriar é 1 clique), e o fluxo já está dentro de um menu — empilhar AlertDialog em DropdownMenu fica frágil em mobile.

#### Testes (`FormationTagTest`)
15 cenários:
- `lista_apenas_tags_do_user` — isolamento
- `cria_tag` + `cor_default_quando_omitida`
- `rejeita_cor_invalida` + `rejeita_nome_duplicado_por_user`
- `users_diferentes_podem_ter_nome_igual` — scope correto do unique
- `atualiza_tag_propria` + `nao_atualiza_tag_de_outro_user` (403)
- `deleta_tag_propria` + `deleta_tag_remove_de_formacoes` (cascata pivot)
- `update_formation_sincroniza_tags` — happy path do sync
- `update_remove_tag_quando_tag_ids_vazio` — clear funciona
- `update_rejeita_tag_de_outro_user` — defense in depth
- `index_inclui_tags_e_available_tags`
- `edit_inclui_tags_da_formation_e_available_tags`

**315 testes verdes** (+15 novos).

---

### ✅ Fase 36 — Export PNG da formação

Botão "PNG" no editor que gera uma imagem 1080px-wide do estado atual da formação, ideal pra colar em Discord, Reddit ou screenshots de guild.

#### Stack
- **`html-to-image`** instalado (`^1.11.13`) — converte DOM em PNG via canvas. Mais leve que html2canvas, mantido, suporta `pixelRatio` para alta resolução.
- Chunk de `edit.tsx` cresceu de ~35KB → 65KB gzip — code-splitting do Vite garante que só carrega quando o user abre o editor.

#### Componente `FormationExportPreview`
- **Sempre renderizado off-screen** (`position:fixed; top:-99999px; pointer-events:none`) — `display:none` não funciona porque html-to-image precisa medir o nó.
- Largura fixa 1080px, altura dinâmica conforme número de teams.
- **CSS inline-only**: Tailwind `@apply` em gradients/borders custom às vezes não traduz pro CSS computado que o canvas vê. Estilos inline (`style={{ background: 'linear-gradient(...)', ... }}`) garantem fidelidade.
- Layout: header com nome + badges (challenge type, modo, PvP, tags) → uma linha por team com posição numérica + nome + score + 5 portraits 180px (com tier badge + class icon overlay) → footer com data + URL do projeto.
- `FACTION_COLOR_HEX` local — 8 cores explícitas em RGB pra evitar dependência das classes Tailwind do design system.
- `img crossOrigin="anonymous"` nos portraits — se o CDN da wiki Fandom (`static.wikia.nocookie.net`) não enviar CORS headers em alguns assets, a exportação **falha graciosamente** com mensagem amigável.

#### Hook `useFormationExport`
- Retorna `{ ref, download, isExporting, error, clearError }`.
- `download()`: chama `toPng(ref.current, { pixelRatio: 2, cacheBust: true, backgroundColor: '#0a0613' })` → cria `<a download>` invisível → click → download via blob:data URL.
- Pixel ratio 2 = retina-quality (2× a resolução dos pixels CSS, ~2160px wide no PNG final).
- Captura erros (CORS é o caso comum) e expõe `error` string pro UI.

#### Integração no editor
- **Desktop**: novo botão "PNG" no header com ícone Download, vira Loader2 durante geração.
- **Mobile**: item "Exportar PNG" no dropdown de "Mais opções".
- Filename derivado do nome da formação: `nome_da_formacao.png` (alphanumeric + `_`).
- Preview renderizado dentro do JSX após o NotesSheet (sempre presente, mesmo enquanto user edita).
- Toast inferior centralizado quando ocorre erro de export — dismissível.

#### Trade-offs
- **Por que `html-to-image` e não `html2canvas`?** Bundle menor (~60KB vs ~200KB gzip), API mais limpa (Promise<dataUrl> direto), mantido ativamente. Single source of truth.
- **Por que renderizar preview off-screen em vez de criar sob demanda?** Criar sob demanda exige aguardar render + img.onload em loop, mais frágil. Off-screen sempre, garante que está pronto quando o user clica.
- **Por que CSS inline-only no preview?** Tailwind compila classes em tempo de build; algumas (especialmente gradients custom) podem perder fidelidade quando o canvas mede o estilo computado. Inline elimina essa variável.
- **Por que pixel-ratio 2 e não 1 ou 3?** 1× fica embaçado em telas retina; 3× quadruplica o tamanho do arquivo sem ganho visual perceptível. 2× é o sweet spot pra Discord/Reddit.
- **Por que CORS em portraits pode falhar?** A wiki Fandom (CDN principal) historicamente envia headers inconsistentes. Não conseguimos forçar — solução real seria proxy backend pra reservir os portraits com headers corretos, mas é complexidade extra. Mensagem de erro orienta retry.

#### Sem testes novos
Feature 100% client-side, baseada em DOM Canvas API que não é testável em PHPUnit. Validação visual manual: testar com formação de 1 team, 7 teams, com/sem beast, com tags, com slots vazios. **315 testes verdes mantidos**.

#### Limitações conhecidas
- Portraits CDN Fandom podem falhar CORS — mensagem orienta retry; sem solução perfeita sem proxy backend.
- `text-overflow: ellipsis` em nomes muito longos truncados ainda; sem wrap multi-linha pra manter alinhamento visual.
- Em mobile o feed pode demorar ~2-3s (canvas pesado); botão mostra Loader durante.

---

### ✅ Fase 37 — Sistema de favoritos para heróis

Toggle "❤️ favorito" no modal de detalhes de cada herói, com filtro dedicado na `/heroes` e contador no dashboard. Feature simples e leve — útil pra marcar heróis que o user planeja investir ou que está pesquisando ativamente.

#### Schema
- **`user_hero_favorites`** — pivot puro: `user_id` + `hero_id` ambas FK com cascade delete bilateral. Unique `(user_id, hero_id)` + index em `hero_id` (pra queries reversas no futuro).
- **Sem model dedicado** — relacionamento M2M via `belongsToMany` no `User`. Pivot table não tem atributos próprios além de timestamps, então não vale criar uma classe Model só pra ela.

#### Controller `HeroFavoriteController`
- 2 endpoints **idempotentes** (segurança contra retries de rede / double-click):
  - `POST /heroes/{hero}/favorite` — `syncWithoutDetaching([$hero->id])` (não duplica)
  - `DELETE /heroes/{hero}/favorite` — `detach($hero->id)` (no-op se já não existe)
- Resposta sempre retorna `{ is_favorited: bool, favorites_count: int }` — frontend pode atualizar contagem global sem refetch.

#### Payloads atualizados
- **`HeroController@index`** ganhou `favoriteHeroIds: number[]` — Set de IDs marcados como favorito pra renderização eficiente do filtro.
- **`HeroController@details`** ganhou `isFavorited: boolean` — usado pelo modal pra estado inicial do botão.
- **`DashboardController@index`** ganhou `stats.favorites_count` — feed o novo StatCard.

#### UI no `HeroDetailModal`
- Botão coração no canto superior esquerdo do header (canto direito é o close do dialog) — `absolute top-3 left-3 size-9 rounded-full`.
- **Update otimista**: ao clicar, troca o estado local imediatamente; só faz rollback se a request falhar. Sensação instantânea mesmo com latência.
- Disparo de `router.reload({ only: ['favoriteHeroIds'] })` após sucesso pra atualizar o filtro da `/heroes` sem reload completo.
- Tonalidade rose quando favoritado (heart filled), muted quando não.
- **`onToggleFavorite` + `favoriting` passados como props** ao `HeroDetailContent` — função filha não pode acessar state do parent diretamente em React. Decisão deliberada pra manter o estado da chamada axios próximo ao state `data`.

#### Filtro na `/heroes`
- Novo botão pill "❤️ Favoritos (N)" ao lado do "Só meus" — variant `default` quando ativo, com tonalidade rose customizada (`bg-rose-500/15 text-rose-600`).
- State `onlyFavorites` integrado ao `useMemo` filter + `filtersActive` + `clearFilters`.
- Contagem `favoriteSet.size` no label do botão pra info passiva.

#### Dashboard
- Novo `StatCard` na segunda linha de stats: ícone Heart + valor `stats.favorites_count` + sub "Heróis marcados" + accent rose (`from-rose-500/20 to-rose-500/0`).

#### Trade-offs
- **Por que sem model dedicado?** Pivot puro (só FKs + timestamps) — criar `UserHeroFavorite extends Model` adiciona arquivo e indireção sem ganho. `belongsToMany` é a abstração certa.
- **Por que `POST` + `DELETE` em vez de um único `PUT`/`PATCH` de toggle?** Verbos HTTP fiéis à operação (REST). `POST favorite` = "marca como favorito" (idempotente via `syncWithoutDetaching`); `DELETE` = "desmarca" (idempotente via `detach`). Toggle puro seria mais conciso mas confunde o que o cliente está pedindo.
- **Por que update otimista no modal?** Latência percebida zero. Risco baixo: se falhar, rollback restaura. O backend é idempotente, então retry é seguro.
- **Por que prop drilling pra `onToggleFavorite` em vez de useContext?** Component pair muito acoplado (parent + child no mesmo arquivo). Context daria overhead sem benefício.

#### Testes (`HeroFavoriteTest`)
11 cenários:
- `marca_heroi_como_favorito` — happy path POST + payload
- `marcar_duas_vezes_e_idempotente` — `syncWithoutDetaching` não duplica
- `remove_dos_favoritos` — happy path DELETE
- `remover_quando_nao_e_favorito_e_idempotente` — `detach` no-op
- `favoritos_sao_isolados_por_user`
- `index_inclui_favorite_hero_ids` — payload de `/heroes`
- `details_inclui_is_favorited` + `details_is_favorited_false_quando_nao_favoritado`
- `dashboard_inclui_favorites_count`
- `guest_nao_pode_favoritar` — 401
- `deletar_heroi_cascateia_favoritos` — `onDelete cascade` na migration

**326 testes verdes** (+11 novos).

---

### ✅ Fase 38 — Notificações de pending_review (admins)

Badge dinâmico no item "Aprovar heróis" da sidebar mostrando a contagem de heróis em `pending_review`. Resolve a UX de "admin precisa lembrar de checar /admin/heroes/pending periodicamente" — agora o número fica visível em qualquer página.

#### Backend (`HandleInertiaRequests`)
- Shared data ganhou key `admin.pending_heroes_count`:
  - **Closure (Inertia lazy prop)**: `fn () => Hero::where('pending_review', true)->count()` — só é resolvida quando o frontend pede a prop. Para **first-render** sempre executa (toda página recebe shared data), mas para **partial reloads** (`router.reload({ only: ['x'] })`) NÃO executa se `admin` não está no set, evitando query desperdiçada.
  - Só atribui closure se `$user && $user->is_admin === true` — não-admins recebem `null`.
- Wrap com `'admin' => ['pending_heroes_count' => ...]` permite expandir esse namespace com outros contadores admin no futuro (ex: stuck sync jobs, failed jobs).

#### Frontend
- **`SharedData` type** ganhou `admin?: { pending_heroes_count: number | null }`.
- **`NavItem` type** ganhou `badgeCount?: number | null` + `badgeTone?: 'amber' | 'rose'` opcionais — abstração genérica que pode ser usada por outros itens no futuro.
- **`NavMain`** renderiza badge condicional (`typeof === 'number' && > 0`):
  - `min-w-[20px]` pra acomodar números de 1 a 3 dígitos
  - Tonalidade rose pra "atenção" (pending) vs amber default
  - `aria-label` com cardinalidade correta (`1 pendente` vs `N pendentes`)
- **`AppSidebar`** decora o item `/admin/heroes/pending` com `badgeCount: pendingCount` + `badgeTone: 'rose'` apenas — outros itens admin ficam sem decoração.

#### Trade-offs
- **Por que closure (lazy) em vez de int direto?** Em partial reloads (saves, etc), Inertia só resolve props pedidos. Sem closure, a query roda em todo request admin mesmo quando ninguém precisa. Com closure, só roda em first-render ou quando alguém pede `only: ['admin']`.
- **Por que key `admin` em vez de `pending_heroes_count` plano?** Namespace pra expansão. Hoje só pending_review; amanhã pode ter `stuck_jobs_count`, `failed_emails_count`. Agrupar evita poluição do shared data flat.
- **Por que `badgeCount` no `NavItem` em vez de prop dedicada no SidebarMenuButton?** Abstração genérica reutilizável. Se outro item admin precisar de badge depois, é só adicionar `badgeCount` ao definir o item — zero novo código no NavMain.
- **Por que rose e não amber?** Pending_review tem caráter de "ação requerida agora" (admin precisa decidir). Amber é warning passivo; rose é "atenção real". Decisão alinhada com o tom usado em "Investidos+esquecidos" da Fase 31.
- **Por que mostrar só quando > 0?** Badge "0" é ruído visual sem informação — admin já sabe que está zerado pela ausência. Hide quando não há nada urgente, show quando há.

#### Testes (`AdminPendingBadgeTest`)
5 cenários:
- `admin_recebe_pending_count_no_shared_data` — count correto pra 3 heróis pending
- `admin_sem_pendentes_recebe_zero` — zero quando não há pending
- `nao_admin_recebe_null` — sem vazamento pra usuário regular
- `aprovar_heroi_reduz_o_contador` — flow real: dashboard mostra 1 → aprovação → dashboard mostra 0
- `guest_nao_recebe_dados_admin` — testa via `/login` (única rota acessível a guest); admin sempre null

**331 testes verdes** (+5 novos).

---

### ✅ Fase 39 — Search global (Cmd+K)

Modal de busca acessível por atalho Cmd+K (Mac) / Ctrl+K (outros) em qualquer página do app, com input + lista agrupada por 4 categorias: heroes, formations, tier lists e guides.

#### Backend (`SearchController`)
- Endpoint `GET /search?q=X` retorna JSON com 4 listas.
- `MIN_QUERY_LENGTH = 2` — queries curtas retornam resultados vazios (evita full-table scan em "a", "e").
- `MAX_PER_CATEGORY = 5` — limite por categoria mantém payload pequeno e foca em "achar rápido" (não "explorar").
- **Escape de wildcards**: `str_replace(['%', '_'], ['\%', '\_'], $query)` antes do `LIKE` — usuário digitando "100%_carry" não vira pattern errado.
- **Heroes**: catálogo público filtrado por `is_active=true AND pending_review=false`. Busca em `name`, `title`, `slug`.
- **Formations / Tier Lists**: escopadas por user via relationship — não vaza dados entre users.
- **Guides**: públicos a todos os authenticated users. Busca em `title`, `title_pt`, `slug`.
- Resposta unificada: `{ id, label, sublabel?, url, image_url? }` — frontend não precisa de lógica por categoria.

#### Frontend (`GlobalSearchDialog`)
- Dialog Radix com `max-h-[80vh]` e `sm:max-w-2xl`. Input com ícone Search à esquerda + Loader2 ou clear button à direita.
- **Busca debounced 200ms** — evita request a cada tecla.
- **AbortController** cancela request anterior quando uma nova começa — previne race conditions ("usuário digita 'tho' depois 'thor' rápido; sem cancel, resposta de 'tho' chegaria depois e sobrescreveria").
- **Resultados flatten + activeIndex**: lista visual hierárquica (cabeçalho de categoria + items), mas internamente um array linear pra navegação por teclado uniforme.
- **Navegação por teclado**:
  - `↑/↓` cicla pelos itens (wrap-around)
  - `Enter` abre o item ativo via `router.visit()`
  - `Esc` fecha (Radix default)
- **Mouse hover sincroniza `activeIndex`** — UX consistente entre teclado e mouse.
- Empty state distinto: "Digite ao menos 2 caracteres" (query curta) vs "Nada encontrado para 'X'" (query válida sem matches).
- Footer com hint de teclado (`↑↓ Navegar`, `Enter Abrir`, `Esc Fechar`).

#### Integração no layout
- `AppSidebarLayout` ganhou state `searchOpen` + `useEffect` que registra listener global de `keydown` pra Cmd+K / Ctrl+K (detectado via `metaKey || ctrlKey`).
- `AppSidebarHeader` ganhou prop opcional `onOpenSearch` — quando passada, renderiza botão "Buscar… ⌘K" no canto direito do header.
- **Detecção de plataforma**: `isMac` checa `navigator.platform` em runtime pra mostrar `⌘` em Mac e `Ctrl` em outros — não polui shared data.

#### Trade-offs
- **Por que server-side em vez de pre-fetch + client filter?** Pre-fetch funcionaria pra heroes (catálogo ~250 itens, ~50KB), mas formações + tier lists crescem indefinidamente e mistura backend (formations escopadas por user) com cache complica invalidação. Server-side é mais simples e escalável.
- **Por que `LIKE %x%` em vez de fulltext search?** Volume modesto (centenas de heroes, dezenas de formações por user). `LIKE` com índice em colunas relevantes (já existe pra slug/name) é suficiente. Fulltext seria sobrengenharia.
- **Por que limite 5 por categoria (não scroll infinito)?** Search global é "encontrar rápido a coisa exata que tenho em mente", não "explorar". Top 5 é mais do que suficiente pra esse caso; se precisa explorar, vai pra página dedicada.
- **Por que debounce de 200ms (não 500ms)?** 200ms é o sweet spot entre cancelar typing e dar feedback responsivo. 500ms começa a parecer "travado".
- **Por que `AbortController` em vez de só ignorar respostas tardias?** Cancel real economiza tráfego e libera PHP-FPM mais cedo. No client, `axios.isCancel` distingue erro de cancelamento.
- **Por que botão de header + atalho (não só atalho)?** Discoverability — usuários novos não sabem do Cmd+K até descobrirem. Botão visível com o atalho impresso ensina e funciona como fallback.

#### Testes (`GlobalSearchTest`)
8 cenários:
- `guest_recebe_401` — auth obrigatório
- `query_curta_retorna_resultados_vazios` — min 2 chars
- `busca_heroi_por_name` — happy path + verifica URL `?open={slug}`
- `nao_retorna_heroi_pending_ou_inativo` — só catálogo público
- `busca_formacao_apenas_do_user` — isolamento (2 users com mesmo nome de formação)
- `busca_tier_list_apenas_do_user` — idem
- `busca_guide_publico` — disponível a qualquer authenticated
- `limite_de_5_resultados_por_categoria` — 7 heroes criados → 5 retornados

**339 testes verdes** (+8 novos).

---

### ✅ Fase 40 — Anotações por herói no roster (markdown)

Campo `notes` no `user_heroes` já existia desde a Fase 4 (schema + validação `max:1000` + persistência no controller), mas a UI era um textarea simples de 60px de altura sem preview, e o `HeroDetailModal` mostrava apenas um trecho `line-clamp-1`. Agora há **editor markdown completo** na edição e render formatado no modal de detalhes.

#### Novo componente `MarkdownEditor`
- Editor inline reusável com tabs `Editar`/`Preview` no topo (toggle via segmented control compacto).
- Props: `value`, `onChange`, `maxLength`, `placeholder`, `minHeight`, `label`, `textSize` (`xs` ou `sm`), `compact`, `fillHeight`.
- `fillHeight: true` faz textarea/preview usarem `flex-1 min-h-0` — permite empacotar em containers de altura total (Sheet, modal full-height). Sem `fillHeight`, usa `min-h-[120px]` ou o que `minHeight` passar.
- Preview reusa `MarkdownContent` (mesmo renderer dos guides, lore e habilidades) — consistência visual cross-feature.
- Contador `N / max` no rodapé com transição pra `text-destructive` quando ultrapassa o limite. Suprimido quando `compact: true`.

#### Refator do `FormationNotesSheet` (Fase 32)
- Antes tinha lógica própria de tabs + textarea + preview + contador (~140 linhas).
- Agora delega tudo pro `MarkdownEditor` com `fillHeight` + `textSize="sm"` (~70 linhas).
- Comportamento idêntico — DRY interno sem mudança visível pro user.

#### Integração no `RosterEditModal`
- Textarea raw substituído por `<MarkdownEditor>` com label custom (ícone `ScrollText` + "Notas (markdown)"), `minHeight="min-h-[100px]"` (cabe melhor no modal denso de roster).
- Placeholder exemplo: *"precisa de F9 antes de subir SI; plano: Ascended 3⭐ até fim do mês"* — orienta o user a usar de fato a feature.
- Mesma `state notes` + `setNotes` — zero mudança no fluxo de save.

#### Render no `HeroDetailModal`
- Novo bloco "Anotações" abaixo dos Chips de investimento, dentro da seção "Meu roster".
- Container `rounded-lg border bg-muted/30 px-3 py-2` com header `<ScrollText>` + label, e body `<MarkdownContent>{userHero.notes}</MarkdownContent>`.
- Só renderiza quando `userHero.notes && notes.trim().length > 0` — esconde quando vazio (sem ruído).
- O Chip "Notas" inline com `line-clamp-1` foi removido — agora as notas aparecem como bloco dedicado quando existem (a info redundante seria poluição).

#### Trade-offs
- **Por que extrair `MarkdownEditor` em vez de duplicar?** Mesma lógica estava nascendo em 3 lugares (FormationNotesSheet, RosterEditModal, e potencialmente HeroDetailModal). Extrair agora vs depois: agora é mais barato (menos call sites pra migrar).
- **Por que limite de 1000 chars (não 2000 como em formação)?** Notas por hero são lembretes táticos curtos ("plano:", "evitar PvP arena por causa de X"). Formação carrega mais contexto estratégico (5 heroes interagindo). Volume natural diferente.
- **Por que preview lado a lado (split) não funcionaria?** Em modal denso de roster, espaço horizontal é caro. Tabs ocupam metade da largura por raiz, split ocuparia ambos. Decisão alinhada com FormationNotesSheet.
- **Por que esconder bloco de notas quando vazio em vez de mostrar "sem notas"?** Hierarquia visual — admin/visitante do modal não precisa ver "sem notas" pra todos heroes. Bloco aparece como signal de "tem informação adicional aqui".

#### Testes (`UserHeroNotesTest`)
6 cenários:
- `update_persiste_notes_markdown` — round-trip via `PATCH /user/heroes/{id}` com headers + lista markdown
- `update_aceita_notes_null` — clear funciona (passa de string pra null)
- `update_rejeita_notes_acima_de_1000_chars` — validação `max:1000`
- `details_inclui_notes_atuais` — payload de `/heroes/{slug}/details` retorna `userHero.notes`
- `index_inclui_notes_no_roster` — payload de `/heroes` retorna `roster[hero_id].notes`
- `outro_user_nao_atualiza_notes` — ownership 403

**345 testes verdes** (+6 novos).

---

### ✅ Fase 41 — Limpar dívida técnica de tipos TS

Refator focado em **fix de tipos**, não em split estrutural. A tabela de tipos faltantes vinha acumulando há 5+ fases (visíveis em `tsc --noEmit` mas sem ação tomada porque CI lint só validava lint, não tipos). Limpeza zero-risco antes de mais features.

#### Escopo decidido (e o que ficou fora)
**Avaliado e descartado**: split do `HeroDetailModal` (1142 linhas) em sub-componentes (HeroHeader, HeroAbilities, HeroEquipment, HeroRosterSection, etc).

**Razão**: Sem testes E2E ou de componente React do modal, qualquer mudança estrutural é validada por "parece igual visualmente" — risco alto pra ganho marginal. Split vale quando alguém precisa mexer em uma seção específica (refactor sob demanda). Hoje não há trigger.

**Feito**: corrigir os erros TS pré-existentes que estavam todos relacionados a 2 issues de tipagem.

#### Fix 1: constraint da função `localized()`
**Sintoma**: `Hero & {faction; hero_class; tier}`, `HeroAbility`, `HeroFurnitureAbility`, `Guide` falhando com:
```
error TS2345: Argument of type 'X' is not assignable to parameter
of type 'Record<string, unknown> | null | undefined'.
```

**Causa**: TypeScript não considera interfaces tipadas como subtype de `Record<string, unknown>` automaticamente — interfaces têm campos definidos mas sem index signature.

**Fix em `resources/js/lib/afk.ts`**:
```ts
// Antes
export function localized<T extends Record<string, unknown>>(...)

// Depois
export function localized<T extends object>(...) {
    const record = item as Record<string, unknown>;
    // ...
}
```

Constraint `object` aceita qualquer interface. Cast interno pra ler propriedades dinâmicas com type safety mantida no call site.

**Resolveu 6 erros** em `hero-detail-modal.tsx` (linhas 369, 742, 743, 772, 773, 774) + 2 em `guides/show.tsx` + 2 em `guides/index.tsx`.

#### Fix 2: forms de auth não satisfazendo `FormDataType`
**Sintoma**:
```
error TS2344: Type 'LoginForm' does not satisfy the constraint 'FormDataType'.
```

**Causa**: `useForm<LoginForm>` exige que o tipo extenda `Record<string, FormDataConvertible>` (interface do Inertia 2.x). Interfaces sem index signature não satisfazem.

**Fix**: Adicionar `[key: string]: FormDataConvertible` aos 3 forms de auth (`LoginForm`, `RegisterForm`, `ResetPasswordForm`) + import `FormDataConvertible` de `@inertiajs/core`.

**Resolveu 3 erros** em `pages/auth/*.tsx`.

#### Fix 3: `mixBlendMode` em welcome.tsx
**Sintoma**:
```
error TS2322: Type '"plus-darker"' is not assignable to type 'MixBlendMode'.
```

**Causa**: `plus-darker` é um valor CSS válido mas as type definitions do React não incluem na union de `MixBlendMode`.

**Fix**: Cast pontual `as React.CSSProperties['mixBlendMode']` — força aceitar o valor sem suprimir a checagem do resto do style.

#### Resultado
| Antes | Depois |
|---|---|
| 12 erros TS acumulados | **0 erros TS** ✅ |
| `tsc --noEmit` poluído | Limpo |

#### Trade-offs
- **Por que não suprimir com `@ts-ignore`?** Suprimir esconde o problema mas não documenta o porquê — em 3 meses ninguém lembra. Fix real é tão barato quanto suprimir.
- **Por que não split do HeroDetailModal agora?** "Cleanup antes de feature" só vale quando há feature pendente naquela área. Hoje não há. Adiar até trigger natural reduz risco.
- **Por que cast em `localized` e não generic refinement?** Generic refinement (`T extends { [K in keyof T]: T[K] }`) é mais correto mas complexo de ler. Cast pontual com comment é mais explícito sobre a intenção ("eu sei que estou indexando dinamicamente, é o uso pretendido").

**345 testes verdes mantidos** (sem novos — comportamento idêntico).

---

### ✅ Fase 42 — Backup JSON de dados do user

Página `/settings/backup` com botão de download de JSON contendo todos os dados de jogo do user. Útil para portabilidade entre instâncias (dev → prod, instância de amigo), backup pessoal antes de mexer em dados, ou recuperação após algum acidente.

#### Estrutura do JSON
```jsonc
{
  "schema_version": 1,
  "exported_at": "2026-05-14T10:23:45+00:00",
  "user": { "id": 1, "name": "...", "email": "...", "locale": "pt" },
  "roster": [
    {
      "hero_slug": "thoran",
      "level": 240, "ascension_code": "ascended_5_stars", "ascension_value": 16,
      "signature_item": 40, "furniture": 9, "engraving": 80,
      "weapon_tier": "...", "weapon_level": 20, // ... 4 slots de equipamento
      "artifact_id": ..., "collection_id": ..., "artifact_star_level": ...,
      "notes": "...",
      "hp": ..., "attack": ..., "defense": ..., // ... 8 stats personalizadas
    }
  ],
  "formations": [
    {
      "name": "...", "challenge_type_code": "campaign", "mode": "target",
      "teams_count": 1, "notes": "...", "beast_slug": "...",
      "scoring_weights_override": null,
      "tag_names": ["speedrun"],
      "teams": [{ "position": 1, "name": null, "heroes": [{"slot": 1, "hero_slug": "...", "row": null}] }]
    }
  ],
  "tier_lists": [{ "name": "...", "rows": [{"label": "S", "color": "amber", "items": [{"hero_slug": "...", "position": 1}]}] }],
  "formation_tags": [{ "name": "speedrun", "color": "emerald", "challenge_type_id": null }],
  "favorite_hero_slugs": ["skreg", "thoran"],
  "scoring_weight_presets": [{ "name": "PvP agressivo", "challenge_type_id": null, "weights": {...} }]
}
```

#### Backend (`Settings/BackupController`)
- **`show(Request)` → Inertia render** de `settings/backup`:
  - Conta cada tipo de item (`roster_count`, `formations_count`, etc) pra mostrar summary
  - Expõe `schema_version` constante pra o frontend exibir
- **`download(Request)` → `StreamedResponse`**:
  - Reúne payload completo via 6 builders privados (`buildRoster`, `buildFormations`, `buildTierLists`, `buildFormationTags`, `buildFavoriteSlugs`, `buildScoringWeightPresets`)
  - `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` — legível humanamente, sem `ç` ou `\/`
  - Filename: `afk-builder-backup-{user_id}-{YYYY-MM-DD-HHMMSS}.json`
  - Stream em vez de in-memory pra suportar rosters grandes (200+ heroes × 25 fields cada) sem consumir muita RAM

#### Identificadores externos (não IDs internos)
- **Heroes** referenciados por `slug` (não `hero_id`) — slug é estável entre instâncias
- **Challenge types** por `code` (`campaign`, `arena_of_heroes`) — idem
- **Beasts** por `slug`
- **Tags** por `name` (escopadas ao user, único pra ele)
- **Artifacts/collections** ainda por ID (sem slug no schema) — limitação documentada

Decisão proposital: facilita import futuro entre instâncias mesmo que IDs internos diferem.

#### UI (`settings/backup.tsx`)
- Layout consistente com profile/password (SettingsLayout + HeadingSmall)
- Card "O que está incluído" com 6 SummaryRows (ícone + label + count tabular)
- Texto pequeno explica `schema_version` + uso de identificadores externos
- Card amber de heads-up reforçando que o export **não inclui senha ou dados sensíveis**
- Botão `<a href="...backup.download" download>` — browser handle do download via header `Content-Disposition: attachment`

#### Trade-offs
- **Por que JSON e não SQL dump?** JSON é universal, legível humanamente, e mais fácil de versionar (diff) e processar (import). SQL exigiria importar exatamente o mesmo schema.
- **Por que stream em vez de in-memory + return Response::json()?** Em rosters grandes, JSON pode passar 100KB+. `StreamedResponse` mantém o encode JSON enquanto envia bytes — não dobra a memória.
- **Por que slugs externos em vez de IDs internos?** Portabilidade. Um backup criado na instância dev deve funcionar quando importado na instância prod, onde os IDs de Hero serão diferentes.
- **Por que sem opção de "export parcial" (só formações, só roster)?** YAGNI. User pode editar o JSON gerado manualmente se quiser filtrar. Adicionar UI de seleção seria 3-4× mais código pra um caso de uso raro.
- **Por que `schema_version: 1` (não 0 ou v1.0.0)?** Inteiro simples bumpa com clareza quando schema muda (1 → 2 → 3). SemVer seria sobrenotação pra esse caso.
- **Por que não incluir import nessa fase?** Import é significativamente mais complexo (validação de schema, merge vs replace, conflitos de slug que não existem, etc). Backup primeiro garante portabilidade unidirecional (saída). Import como fase separada quando demanda real surgir.

#### Testes (`BackupTest`)
10 cenários:
- `guest_recebe_login_redirect` — ambos endpoints (show e download) protegidos
- `page_renderiza_com_summary` — counts corretos no Inertia payload
- `download_retorna_json_com_schema_correto` — Content-Type, Content-Disposition, schema_version, todas as 6 chaves principais
- `download_inclui_roster_completo` — fields completos com SI 40, F9, E80, notes
- `download_inclui_formacao_com_teams_e_tags` — teams aninhados + tag_names array
- `download_inclui_tier_list_com_rows_e_items` — rows com items por hero
- `download_inclui_favoritos_como_slugs` — array flat de slugs
- `download_inclui_presets_de_peso` — name, challenge_type_id, weights array
- `dados_sao_isolados_por_user` — userA cria roster, userB exporta vazio
- `filename_inclui_user_id_e_timestamp` — regex match no Content-Disposition

**355 testes verdes** (+10 novos).

---

### ✅ Fase 43 — Import JSON (complementa backup)

Fecha o ciclo de portabilidade aberto pela Fase 42. Upload de arquivo JSON na mesma página `/settings/backup` com escolha entre **merge** (preservar existente) e **replace** (limpar antes).

#### Backend (`BackupController::import`)
- Endpoint `POST /settings/backup/import` aceita:
  - `file`: upload (`mimetypes:application/json,text/plain`, max 5MB)
  - `strategy`: `merge` ou `replace`
- Validação em 3 camadas:
  1. Laravel `validate()` valida o request (presença, tipo, tamanho do file)
  2. `json_decode` + check `is_array` (rejeita JSON corrompido)
  3. Estrutura: `schema_version` obrigatório int + seções nullable arrays
  4. `schema_version === SCHEMA_VERSION` (1) — rejeita versões incompatíveis com mensagem clara
- **Toda operação em `DB::transaction`** — rollback completo se qualquer seção falhar. Atomicidade garantida.
- Retorna `back()->with('status', $summary)` — flash message com counts importados + skipped.

#### Estratégias (`merge` vs `replace`)
**Merge** (default, recomendado):
- Preserva dados existentes
- Adiciona apenas o que ainda não existe (skip via unique constraint check em código)
- Heroes já no roster → skip
- Tags / Presets com mesmo nome → skip
- Favoritos: `syncWithoutDetaching` (não remove o que já está, só adiciona)

**Replace** (destrutivo):
- `wipeUserData($user)` chama `delete()` em userHeroes, formations, tier_lists, formation_tags, scoring_weight_presets + `detach()` em favorite_heroes
- Cascade do DB cuida de teams/team_heroes, rows/items, pivots — sem código manual
- Recria tudo do JSON
- UI tem warning rose com counts atuais que serão perdidos

#### Resolução de slugs externos
- Hero referenciado por `hero_slug` — busca `Hero::whereIn('slug', ...)` em batch
- Beast por `beast_slug` — idem
- Challenge type por `challenge_type_code` — idem
- **Slugs não encontrados são skipados silenciosamente** e contados em `skipped`
- Mensagem final inclui contagem total skipada: *"Backup importado (merge): 50 heroes, 3 formações, ... (5 itens skipados por slug/code não encontrado.)"*

#### Sanitização de payloads
- **Presets de pesos**: filtra `weights` pra incluir só chaves de `ScoringWeightPreset::WEIGHT_KEYS` válidas + clamp `0.0 ≤ value ≤ 3.0`. Mesmo se o JSON tivesse pesos malicious `power_weight: 999`, o import normaliza.
- **Tag color**: se cor não está em `FormationTag::COLORS`, usa `DEFAULT_COLOR` (`amber`).
- **Defaults pra campos faltantes**: `level ?? 1`, `mode ?? 'target'`, etc — JSON malformado parcial não trava o import.

#### UI no `settings/backup.tsx`
- Seção "Importar backup" abaixo do bloco de export
- **StrategyOption cards** lado a lado (mobile: empilhados):
  - Merge (emerald) — preserva o existente
  - Replace (rose) — destrutivo
- Toggle por click; tone change visual marca ativo via `aria-pressed`
- Input `<input type="file" accept="application/json,.json">` estilizado via `file:` pseudo-element selector
- **Warning rose contextual** aparece só quando `strategy === 'replace' && file selecionado` — mostra counts atuais que serão perdidos
- Botão "Importar JSON" com `Loader2` durante `processing`
- Flash message emerald no topo após sucesso, error rose para falhas
- Após sucesso: reset form + `router.reload({ only: ['summary'] })` pra refrescar os counts no card de export

#### Trade-offs
- **Por que `merge` como default e não `replace`?** Padrão menos destrutivo. User que sabe o que quer marca replace explicitamente. Acidente de double-click ou ENTER por engano fica seguro.
- **Por que slugs skipados não geram error?** Heroes podem não estar seedados ainda (catálogo sendo construído) ou foram removidos da wiki. Hard fail no primeiro slug ausente quebra import inteiro. Skip + contagem dá feedback útil.
- **Por que sanitizar weights mesmo com whitelist no controller original?** Defense in depth — JSON pode vir de export feito em versão antiga ou de outra instância com schema diferente. Validar nas duas pontas.
- **Por que cascade do DB pra wipe e não delete manual em loop?** Foreign keys com `cascadeOnDelete` foram definidas exatamente pra isso. Delete manual reimplementaria a mesma lógica e poderia esquecer alguma tabela pivot.
- **Por que não dry-run mode (mostrar preview antes de aplicar)?** YAGNI. User pode abrir o JSON num editor pra ver o conteúdo. Adicionar dry-run + diff visual dobra a complexidade.
- **Por que merge não sobrescreve dados de hero existentes (level, SI)?** Decisão semântica: "merge" = "fundir, sem perder informação minha". Sobrescrever seria "atualizar com o backup", que é o que `replace` por hero específico faria — mas se o user quer isso, melhor remover do roster manualmente antes do import.

#### Testes (`BackupImportTest`)
13 cenários:
- `guest_recebe_redirect`
- `rejeita_arquivo_sem_schema_version` (validação Laravel)
- `rejeita_schema_version_incompativel` (999 vs 1)
- `merge_importa_roster_completo` — round-trip com todos os fields (SI 40, F9, E80, notes)
- `merge_skipa_hero_ja_no_roster` — preservação de dados existentes
- `replace_apaga_e_recria_roster` — sobrescreve SI 0 → SI 40
- `slug_inexistente_e_skipado` — validação de status message com count
- `importa_formacao_com_tags_e_teams` — happy path completo (teams + heroes via slug + tag_names lookup)
- `challenge_type_code_inexistente_skipa_formacao`
- `importa_favoritos` — slugs válidos + slug ausente skipado
- `importa_presets_skipa_nome_duplicado_em_merge` — preset 'pvp' original preservado
- `isola_por_user`
- `replace_remove_tags_existentes_antes_de_importar` — wipe correto

**368 testes verdes** (+13 novos).

---

### ✅ Fase 44 — Atalhos de teclado globais

Atalhos cross-page estilo Linear/GitHub: **leader key `G`** seguida de letra navega pra área. `?` abre modal listando todos. Cmd+K (search da Fase 39) também documentado no help.

#### Sistema de leader key
- **`G`** ativa o leader (state interno + timeout 1500ms)
- Próxima tecla dentro do timeout: combo `g <letra>` é resolvido contra `SHORTCUTS`
- Sem timeout, **outras teclas** durante o estado leader também resetam (não fica "preso")
- Implementação compacta com `useRef` (não causa re-render por keystroke)

#### Bindings declarativos (`SHORTCUTS` em `use-global-shortcuts.ts`)
| Combo | Descrição |
|-------|-----------|
| `g d` | → /dashboard |
| `g h` | → /heroes |
| `g u` | → /heroes/usage |
| `g f` | → /formations |
| `g c` | → /formations/recommended (comps prontos) |
| `g t` | → /tier-lists |
| `g g` | → /guias |
| `g s` | → /settings/profile |
| `?` | abrir modal de ajuda |
| Cmd/Ctrl+K | abrir busca global (Fase 39) |

Bindings são **declarativos**: a mesma constante alimenta o hook e o modal de help. Mudar uma entrada propaga para os dois.

#### Hook `useGlobalShortcuts`
- Registra um único `keydown` listener em `window`
- **Ignora eventos quando user está digitando** (`input`, `textarea`, `select`, `contenteditable`). Tecla `g` enquanto edita formação não navega.
- **Ignora atalhos com modifier** (`Cmd`/`Ctrl`/`Alt`) exceto Cmd+K que tem tratamento próprio — evita conflito com atalhos do browser (Ctrl+P, Cmd+R, etc).
- Action pode ser string (`router.visit`) ou função arbitrária — flexível pra atalhos não-navegacionais futuros.
- Aceita prop `disabled` — desabilitado enquanto modal de search/help está aberto (evita roubar `g` lá dentro).

#### Modal `KeyboardShortcutsHelp`
- Lista os bindings agrupados em "Navegação" + "Geral"
- Inclui também atalhos não-gerenciados pelo hook (Cmd+K, ?, Esc) hardcoded pra o user ver tudo num lugar só
- Cada combo renderiza como `<kbd>` separado por "depois": `g h` → `[G] depois [H]`
- Disparado por `?`, botão Keyboard no header (desktop), ou Esc fecha
- Footer com lembrete: "atalhos desativados em campos de texto"

#### Trade-offs
- **Por que leader key e não combinações com modifier?** Cmd/Ctrl + letra esbarra constantemente em atalhos do browser (Cmd+H minimiza Mac, Cmd+T abre tab, etc). Leader key é convenção de apps "power user" (Linear, Notion, GitHub) — descobrível via `?` e familiar pra quem usa esses.
- **Por que `g` e não outra letra?** Convenção GitHub/Linear. Curto, fácil de teclar com indicador esquerdo, raramente conflita com início de palavras importantes.
- **Por que 1500ms de timeout?** Suficiente pra typing relaxado (testei manualmente), curto pra não deixar o estado "preso" indefinidamente se user abandonar.
- **Por que `useRef` em vez de `useState` pro leader?** State causaria re-render a cada keystroke do `g` — desperdício. Ref muta sem re-render.
- **Por que `disabled` quando modal aberto e não dentro do hook que checa modal aberto?** Inversão de controle: o layout sabe quais modais estão abertos, hook não deveria conhecer essa estrutura. Prop fica honesta e desacoplada.
- **Por que sem testes PHP?** Feature 100% client-side, sem backend novo. Lógica do hook é simples o suficiente que TS type check + uso real cobrem; testes JS exigiriam Vitest setup novo (~30min de configuração) pra ganho marginal.

#### Sem novos testes
**368 testes verdes mantidos**.

---

### ✅ Fase 45 — Bulk edit no roster (multi-select)

Modo seleção em `/heroes` com checkbox em cada card e barra flutuante de ações em lote — UX similar ao Gmail. Reduz fricção de operações repetitivas (marcar 10 heroes como favoritos, adicionar 20 ao roster antes de tunar individualmente, etc).

#### Backend — `HeroBulkActionController`
2 endpoints com payload uniforme `{hero_ids: number[], action: 'add'|'remove'}`:

- **`POST /heroes/bulk/favorites`** — `syncWithoutDetaching` em add (idempotente) ou `detach` em remove
- **`POST /heroes/bulk/roster`**:
  - `add`: cria `UserHero` com defaults (level 1, asc 0, SI 0, F 0, E 0) pra cada `hero_id` que ainda não está no roster
  - `remove`: `delete()` de cada `UserHero` que está no roster

**Validações**:
- `hero_ids: required|array|min:1|max:200` — limite previne abuso e queries gigantes
- `hero_ids.*: integer|min:1`
- `action: required|in:add,remove`
- `array_unique` interno dedupa IDs no payload antes de processar
- `Hero::whereIn('id', $ids)->pluck('id')` filtra IDs realmente existentes — evita attach com FK inválida ou crashes
- Resposta inclui `affected` (quantos realmente mudaram) + count atualizado da seção relevante

#### `HeroCard` com selection mode
Props opcionais que mantêm o card 100% retrocompatível:
- `selectionMode: boolean` — quando true, click vira toggle de seleção (não abre modal)
- `isSelected: boolean` + `onToggleSelect: () => void`

**Visual em selection mode**:
- Checkbox overlay no canto superior direito (substitui posição do tier badge no detalhe), preenchido em primary quando selecionado
- Card selecionado: `ring-4 ring-primary` + `scale-[1.02]`
- Card não-selecionado: `opacity-70` — destaca os escolhidos
- `aria-pressed={isSelected}` em selection mode (substitui o aria-pressed default)

#### `/heroes` — toggle modo + barra de ações
- **Botão "Selecionar" no header** — quando ativo vira "Cancelar (N)" mostrando contagem
- **Barra flutuante fixed bottom** com 5 ações (Favoritar / Desfavoritar / Add no roster / Remover roster / Cancelar)
  - `bottom-4 left-1/2 -translate-x-1/2 max-w-3xl` — centralizada, max 768px
  - Cancelar com ícone X só (texto `sr-only` em mobile)
  - Remover do roster com tonalidade rose pra signalar destrutivo
  - Hint "Esc para cancelar" no rodapé
- **`Esc` cancela seleção** via listener `keydown` registrado quando `selectionMode` é true (specific desta página, não vai pro `useGlobalShortcuts` da Fase 44 pois é contextual)
- **Reload partial** pós-ação: `router.reload({ only: ['favoriteHeroIds', 'roster'] })` — atualiza state sem reload completo

#### Trade-offs
- **Por que `affected` no response e não fail-on-conflict?** Add em hero já no roster ou favoritar duplicado não é erro — é no-op. Idempotência simplifica retry e double-click. Resposta com `affected` informa o que realmente mudou.
- **Por que filtrar `Hero::whereIn` em vez de `Rule::exists` no validator?** Validator rejeitaria o request inteiro se 1 ID for inválido — o frontend teria que descobrir qual era e retentar. Filtrar internamente + retornar `affected` é mais friendly: "20 IDs enviados, 18 afetados, 2 ignorados (IDs inválidos)".
- **Por que limite de 200 por request?** Lookup table típica do user fica em centenas; 200 cobre uso real com folga. Acima disso virariam queries pesadas no DB. UI não permite passar isso (catálogo tem ~250 heroes).
- **Por que add no roster usa defaults e não dados específicos?** Bulk é pra criar shells rápidos; usuário depois ajusta cada um no modal de detalhes. Sobrecarregar bulk com 25 campos opcionais é confuso.
- **Por que opacity 70% nos não-selecionados em vez de hide?** Hide quebra mental model — usuário não vê o catálogo. Atenuar prioriza visualmente os escolhidos sem ocultar contexto.
- **Por que listener Esc específico da página em vez de global?** Esc tem semântica contextual: aqui cancela seleção; em outras páginas pode fechar modal. Global ficaria ambíguo. Hook da Fase 44 trata navegação (`G letra`), não cancelamento de estado local.

#### Testes (`HeroBulkActionTest`)
13 cenários:
- `guest_recebe_401`
- `bulk_favorita_em_lote` — 3 heroes → 3 favoritos, count correto
- `bulk_favorita_e_idempotente` — re-favoritar não duplica
- `bulk_desfavorita` — remove em lote
- `bulk_filtra_ids_inexistentes` — IDs 99999 ignorados, `affected: 1` quando só 1 era real
- `bulk_add_no_roster` — cria UserHero com defaults (level 1, SI 0)
- `bulk_add_no_roster_skipa_ja_existentes` — hero com SI 40 preservado, novo add não sobrescreve
- `bulk_remove_do_roster` — delete em lote
- `rejeita_action_invalida` — `action: invalid` → 422
- `rejeita_hero_ids_vazio` → 422
- `rejeita_acima_de_200_ids` → 422
- `isola_por_user`
- `dedupe_ids_no_payload` — IDs duplicados no array → `affected: 1`

**381 testes verdes** (+13 novos).

---

### ✅ Fase 46 — Histórico de power do roster (snapshots + chart)

Snapshot diário automatizado do power total do roster por user + chart Recharts na dashboard mostrando evolução nos últimos 30 dias. Útil pra ver progresso de investimento ao longo do tempo (asc up, novos heroes, F9, etc).

#### Schema (`roster_power_snapshots`)
- `id`, `user_id` FK cascade, `snapshot_date` (date), `total_power` (unsigned bigint), `hero_count` (unsigned int), `top_hero_id` (FK nullable nullOnDelete), `top_hero_power` (unsigned bigint nullable), `created_at`
- Unique `(user_id, snapshot_date)` — **1 snapshot por user por dia**
- Index `(user_id, snapshot_date)` — queries do tipo "últimos 30 do user X"
- Sem `updated_at` — registro é write-once por dia; re-rodar o comando faz upsert

#### Model `RosterPowerSnapshot`
- Cast `snapshot_date => 'date:Y-m-d'` — formato explícito pra `updateOrCreate` comparar igual ao `toDateString()`. Cast genérico `'date'` converte pra datetime em SQLite (text), quebra unique check.
- Relations `user()` + `topHero()`
- `public $timestamps = false` — só `created_at`, sem `updated_at`

#### Comando artisan `roster:snapshot`
```bash
php artisan roster:snapshot                       # hoje, todos os users com roster
php artisan roster:snapshot --user-id=42          # só user 42
php artisan roster:snapshot --date=2026-01-15     # backfill data específica
```

- Itera users (todos ou `--user-id`), carrega roster com eager-load completo (faction, class, stats, artifact, collection)
- Para cada user: soma `PowerCalculator::forUserHero()` de todos os UserHero
- Track do top hero por power (slug + power individual)
- `updateOrCreate` por `(user_id, snapshot_date)` — re-rodar atualiza
- Skipa users com roster vazio
- Imprime summary: "Snapshot 2026-05-14: 5 processados, 4 criados/atualizados, 1 skipado (roster vazio)"

#### Schedule (`routes/console.php`)
```php
Schedule::command('roster:snapshot')->dailyAt('04:00')->withoutOverlapping()->onOneServer();
```
- Roda às 04:00 (após `heroes:sync` 03:30 e `heroes:resolve-images` 03:50)
- `withoutOverlapping` + `onOneServer` evitam dupla execução

#### Payload da dashboard
`DashboardController` agora inclui `powerHistory`:
- Últimos 30 snapshots do user (`orderByDesc + limit(30)`)
- Re-ordenado cronologicamente com `sortBy('snapshot_date')` antes do render (chart visualiza esquerda → direita = mais antigo → mais novo)
- Cada ponto: `{ date, total_power, hero_count }`

#### Chart `RosterPowerChart` (Recharts)
- `npm i recharts` (~50KB gzip no chunk de `dashboard`)
- `LineChart` com:
  - `XAxis` formatando data como `dd/mm` (com `minTickGap=20` pra evitar overlap)
  - `YAxis` com tick formatter compacto (`12000 → 12k`, `2500000 → 2.5M`)
  - `Tooltip` custom: data full (`dd/mm/yyyy`) + `Power total: 12.345.678` + `Heroes: 87`
  - `Line` monotona amber, sem dots fixos, `activeDot` no hover
- Header com **delta + variação %**: `+12.5k (+8.3%)` em emerald (alta) ou rose (baixa)
- Empty state quando não há snapshots: instrução pra rodar o comando ou aguardar 24h

#### Trade-offs
- **Por que tabela dedicada e não calcular sob demanda?** Power exige join completo de roster + relations + computação por hero — caro pra fazer em request. Snapshot diário é assíncrono, dashboard usa lookup direto.
- **Por que `top_hero_id` + `top_hero_power` no snapshot?** Pré-computar evita join com roster pra mostrar "MVP do dia". Trade-off: 2 colunas extras × N snapshots; aceitável dado o volume baixo (~30 por user).
- **Por que cap em 30 snapshots na dashboard?** Sweet spot entre "mostrar evolução visível" e "chart legível". Mais que 30 vira ruído visual em telas mobile.
- **Por que `cascade` no `top_hero_id` (nullOnDelete) em vez de `cascadeOnDelete`?** Se um hero é deletado (cleanup admin), o snapshot ainda é útil — só perde o "qual hero específico era top". Hard cascade jogaria histórico fora.
- **Por que 04:00 e não 00:00?** Dá 1h de buffer pra `heroes:sync` (03:30) + `heroes:resolve-images` (03:50) terminarem antes — snapshots podem precisar de dados frescos do catálogo.
- **Por que sem `created_at` cast genérico `datetime`?** Já é o default Eloquent. Tirar `updated_at` foi proposital — registros são write-once por dia.
- **Recharts cresce ~50KB gzip no chunk de dashboard.** Aceitável: chart é a feature mais "visual" da dashboard e o chunk só carrega quando user entra lá (code-split do Vite).

#### Testes (`RosterPowerSnapshotTest`)
10 cenários:
- `comando_cria_snapshot_para_user_com_roster` — happy path, valida total_power > 0 + top_hero_id
- `comando_skipa_user_sem_roster` — user vazio não gera snapshot
- `comando_e_idempotente_por_dia` — 3 runs → 1 snapshot
- `comando_atualiza_snapshot_quando_roster_muda` — reduz SI → power do snapshot cai
- `comando_aceita_user_id_option` — restringe a 1 user
- `comando_aceita_date_option` — backfill funciona
- `dashboard_inclui_power_history` — payload com ordem cronológica crescente
- `dashboard_limite_30_snapshots` — 40 snapshots → 30 retornados
- `dashboard_isola_history_por_user`
- `cascade_delete_quando_user_deletado` — FK cascade funciona

**391 testes verdes** (+10 novos).

### ✅ Fase 47 — Tier list templates (admin marca, user clona)

Admins agora podem **marcar uma das próprias tier lists como template público**. Qualquer user autenticado acessa `/tier-lists/templates`, vê todos os templates publicados e clica em **Clonar** pra ganhar uma cópia editável da lista (com todos os tiers e heróis pré-posicionados) sob sua propriedade.

#### Schema
Migration `2026_05_16_010000_add_template_fields_to_user_tier_lists.php`:
- `is_template` boolean default false (com index — query `where is_template = true` no /templates)
- `cloned_from_id` nullable FK para `user_tier_lists` com `nullOnDelete` — preserva a cópia se o template original for deletado pelo admin

#### Endpoints
- `GET /tier-lists/templates` — público (auth); lista todos `is_template=true` com `withCount(rows)` + `items_count` via subquery join
- `POST /tier-lists/templates/{tierList}/clone` — público (auth); cria nova lista do user atual num único `DB::transaction` copiando rows (label/color/position) e items (hero_id/position); redireciona pro editor
- `PATCH /tier-lists/{tierList}/template` — admin-only (`abort_unless($user->is_admin)`) **e** ownership-check (admin só marca **as próprias** listas; admin não toca em lista de outro user)

Rotas declaradas **antes** das rotas com `{tierList}` no `routes/web.php` pra não bater no route model binding (a palavra "templates" seria interpretada como ID).

#### UI
- **`tier-lists/templates.tsx`** — grid de cards com nome, autor, contagem de tiers/heróis, botão "Clonar" via `router.post` (segue redirect Inertia pro editor)
- **`tier-lists/index.tsx`** — botão "Templates" no header + badge ⭐ amber "Template" nas listas próprias com `is_template=true`
- **`tier-lists/edit.tsx`** — botão "Marcar template" (ou "Template ativo" se já marcado) **apenas para admins** (`usePage<SharedData>().props.auth.user?.is_admin`); estado local toggle via `axios.patch`

#### Trade-offs
- **Por que o admin permanece dono do template (e não vira "sistema")?** Mantém a auditoria simples — o user que publicou aparece como "por X" na lista de templates. Se admin sair, decisão de deletar/transferir é manual; não precisamos de "usuário-sistema" sintético.
- **Por que `nullOnDelete` no `cloned_from_id` em vez de cascade?** A cópia do user é dele — não deve sumir só porque admin removeu o template público. Perde só a referência da origem.
- **Por que clone não copia `share_token`?** Tokens são únicos e o user pode (re)gerar o próprio depois. Copiar geraria collision na constraint `unique` ou exigiria regeneração — mais simples começar `null`.
- **Por que botão de template fica no `edit` em vez do `index`?** Marcar template é uma decisão sobre **conteúdo curado**, e quem cura está no editor olhando os tiers. No index seria click acidental. Bonus: usuário não admin nem vê o botão (UI já filtra).
- **Por que admin não pode marcar lista de outro user como template?** Mantém ownership clara — se o admin quer publicar a curadoria de outro, clona/copia o conteúdo pra sua própria conta primeiro. Evita "sequestro" de listas.
- **Por que `withCount(rows as items_count)` com join manual em vez de `withCount('rows.items')`?** Eloquent não suporta nested `withCount` direto — o join na subquery é mais explícito e gera uma única query agregada.

#### Testes (`TierListTemplateTest`)
12 cenários:
- `admin_pode_marcar_lista_como_template`
- `user_normal_nao_pode_marcar_template` (403)
- `admin_nao_pode_marcar_lista_de_outro_user_como_template` (403)
- `pagina_de_templates_lista_apenas_templates` — filtro `is_template=true` funciona
- `clone_copia_rows_e_items_para_o_user_atual` — verifica ownership, labels/cores/posições e hero_ids
- `clone_falha_se_lista_nao_for_template` (404, defesa em profundidade)
- `clone_exige_autenticacao` (redirect /login)
- `pagina_de_templates_exige_autenticacao` (redirect /login)
- `clone_nao_propaga_flag_de_template` — cópia sempre `is_template=false`
- `dois_clones_geram_listas_independentes`
- `delete_template_seta_cloned_from_id_null_no_copy` — FK `nullOnDelete` funciona
- `index_inclui_flag_is_template` — payload da listagem traz a flag

**403 testes verdes** (+12 novos).

### ✅ Fase 48 — Beasts por team com DnD e auto-pick

A besta da formation deixa de ser global e passa a ser **por team individual**. O editor ganha um slot DnD por team, lista de bestas no pool lateral (tabs Heróis | Bestas), e um botão "Auto" por team que escolhe a melhor besta baseado nos tipos dos heróis posicionados.

#### Schema
Migration `2026_05_16_020000_move_beast_to_formation_teams.php`:
- Adiciona `beast_id` em `formation_teams` (FK `nullOnDelete` + index)
- Backfill via subquery agnóstica (MySQL/SQLite): cada team da formation herda o `beast_id` da formation
- Dropa `formations.beast_id`

#### Backend
- `FormationTeam`: relação `beast()` + `beast_id` em fillable
- `Formation`: relação `beast()` removida + `beast_id` saiu do fillable
- `FormationController::update`: valida `teams.*.beast_id` (`Rule::exists` em beasts ativos), persiste por team
- `FormationController::edit`/`suggestTeam`: eager-load `teams.beast`; suggest usa beast do team específico
- `FormationController::suggestAll`: passa `null` como beast (decisão pragmática — beasts são definidas separadamente via endpoint dedicado por team)
- `FormationController::index`: pré-calcula `has_beast` via subquery agregada (`whereHas('teams', whereNotNull beast_id)`) — evita N+1 sem carregar relação completa
- `FormationCompareController`: scoring usa `$team->beast`; payload inclui beast por team
- `FormationShareController::publicShow`: eager-load `teams.beast` pra renderização pública
- `Settings/BackupController`: export move `beast_slug` pra dentro de cada team; import faz backward-compat com `beast_slug` top-level de backups antigos

#### Endpoint `POST /formations/{formation}/teams/{team}/best-beast`
Calcula a melhor besta pro team baseado em **maior buff agregado**:
```
score = Σ (beast.buff_type × heroes_no_team_daquele_type)
```
- 422 `team_empty` se team sem heroes
- 422 `no_typed_heroes` se nenhum hero tem `type` definido (Strength/Intelligence/Agility)
- 422 `no_beasts` se sem bestas ativas cadastradas
- 422 `no_buff_match` se nenhuma besta beneficia os tipos presentes
- Persiste `beast_id` no team em caso de sucesso

#### Frontend
- **`BeastSlot`** (`components/formation-editor/beast-slot.tsx`): drop-zone `useDroppable` por team com `kind: 'beast-slot'`. Mostra portrait + buffs + ✕ remover + 🔮 Auto
- **`BeastPool`** (`components/formation-editor/beast-pool.tsx`): lista de bestas draggables (`useDraggable` com `kind: 'beast'`); search por nome
- **`EditorPool`** (`components/formation-editor/editor-pool.tsx`): wrapper com tabs Heróis | Bestas; substitui o uso direto de `HeroPool`
- **`TeamRow`**: ganha props `beastById`, `onClearBeast`, `onAutoBestBeast`, `isPickingBeast`; renderiza `<BeastSlot>` ao lado dos slots de heroes
- **`TeamSlotsState`**: ganha `beastId: number | null`
- **`formations/edit.tsx`**:
  - Remove o `<Select>` global do header (besta agora é por team)
  - `handleDragEnd` reconhece `kind: 'beast'` → `kind: 'beast-slot'` antes do path de hero
  - `clearBeastForTeam` / `setBeastForTeam` / `autoPickBestBeast` (chama endpoint best-beast e atualiza state)
  - Payload do save inclui `team.beast_id`
  - Tap-to-add mobile: tap em besta seta a besta no `mobileTargetTeamPos` (mesma team alvo dos heroes)
- **`formations/public.tsx`**: linha com portrait + nome + buffs da besta acima dos slots de heroes (quando team tem beast)
- **`formations/index.tsx`**: usa `has_beast` (computed do controller) em vez do antigo `beast_id !== null`

#### Trade-offs
- **Por que beast por team e não por formation?** No AFK Arena cada team de uma formation multi-team enfrenta inimigos distintos; idealmente cada team escolhe a besta que maximiza seu power. Beast única por formation era restritivo demais.
- **Por que `suggestAll` ignora beasts?** O `MultiTeamPartitioner` faz swaps cross-team — combinar isso com escolha ótima de beast por team explodiria o espaço de busca. Solução pragmática: distribuição de heroes via suggestAll, escolha de beast via endpoint dedicado por team. UI invita explicitamente clicar em "Auto" depois de organizar os heroes.
- **Por que `has_beast` computed em vez de carregar `teams.beast` no index?** Listagem só precisa do flag pra filtros — carregar relação completa é caro pra listagem (pode ter dezenas de formations × N teams). Subquery agregada via `whereHas` resolve em uma query extra.
- **Por que SQLite-friendly UPDATE com subquery em vez de `UPDATE...JOIN`?** Testes rodam em SQLite e SQLite não suporta a sintaxe `UPDATE...INNER JOIN`. Subquery via `DB::table()->whereExists()` funciona em ambos.
- **Por que backup mantém retrocompatibilidade com `beast_slug` top-level?** Backups exportados pré-Fase 48 não têm `beast_slug` por team. Import detecta o legacy top-level e aplica a todos os teams (mesma lógica da migration).
- **Por que `nullOnDelete` no `beast_id` do team?** Se admin deleta uma besta do catálogo, teams perdem só a referência — formation continua válida.
- **Por que tabs Heróis|Bestas em vez de strip horizontal de bestas?** Manter o layout de uma coluna lateral (consistente com o pool de heroes) é mais limpo. Strip horizontal consumiria altura preciosa em telas pequenas.

#### Testes (`FormationBeastTest`)
12 cenários (refactor + 6 novos):
- `edit_carrega_lista_de_beasts_disponiveis` — pool inclui só `is_active=true`
- `user_pode_atribuir_beast_a_team` — payload `teams.*.beast_id` persiste
- `user_pode_remover_beast_setando_null`
- `validacao_rejeita_beast_inexistente` — erro em `teams.0.beast_id`
- `validacao_rejeita_beast_inativo`
- `beasts_diferentes_por_team` — multi-team com beasts independentes
- `best_beast_endpoint_seleciona_maior_buff_agregado` — 3×strength_buff:10=30 vence 1×int_buff:20=20
- `best_beast_rejeita_team_vazio` (422 `team_empty`)
- `best_beast_rejeita_heroes_sem_type` (422 `no_typed_heroes`)
- `best_beast_403_se_team_de_outro_user`
- `best_beast_404_se_team_nao_pertence_a_formation`

**409 testes verdes** (+6 novos best-beast).

### ✅ Fase 49 — Filtros avançados em /heroes (investimento + equipamento)

Catálogo de heróis ganha **painel colapsável** com 8 filtros que se aplicam contra o roster do user: SI mín, Móveis mín, Inscrição mín, Nível mín, Tier do catálogo, Has artefato (sim/não/qualquer), Has coleção (sim/não/qualquer), e Equipment tier mínimo em **todas as 4 peças** (weapon/helmet/chest/boots).

#### Backend
`HeroController::index` agora carrega no payload do roster os 11 campos adicionais necessários pra alimentar os filtros:
- `artifact_id`, `collection_id`
- `weapon_tier`, `weapon_level`, `helmet_tier`, `helmet_level`, `chest_tier`, `chest_level`, `boots_tier`, `boots_level`

Custo desprezível (UserHero já existe e é indexado por hero_id; 11 cols leves × N user_heroes).

#### Frontend
- **`components/heroes-advanced-filters.tsx`** — painel colapsável (collapsed by default) com:
  - **Segments numéricos** com presets: SI [0/10/20/30/40], F [0/3/6/9], Eng [0/30/60/80], Level [0/160/200/220/240]
  - **Tristate** "Qualquer / Com / Sem" pra artefato e coleção
  - **Equipment tier mínimo** com botões T1+...T6+ (aplica a todas as 4 peças simultaneamente — "AND" entre slots)
  - **Tier do catálogo** (multi-select chips usando os tiers do payload)
  - Badge "ativos" no header quando algum filtro está ligado
  - Reset rápido via link "Limpar avançados" no header expandido
- **Lógica client-side** (`heroes/index.tsx`):
  - `advancedScopesRoster` flag: true se qualquer filtro de investimento/equipamento ativo — implica `hero ∉ roster ⇒ excluído`
  - `tierCodeMeetsMin(uh.weapon_tier, 'T4')` compara via mapa `T1=1...T6=6`
  - Filtro de tier do catálogo é por `hero.tier_id` (independente de roster — funciona pra heróis fora do roster também)
- **`clearFilters`** + **`filtersActive`** estendidos pra cobrir o estado avançado

#### Trade-offs
- **Por que tudo client-side?** A página `/heroes` já carregava o catálogo inteiro + roster. Filtrar via backend exigiria query param + roundtrip a cada toggle. Client-side é instantâneo e o payload já está em memória.
- **Por que `equipTierMin` aplica em todas as 4 peças (AND) e não OR?** "Quero heróis com pelo menos T4 em tudo" é a query útil pra organizar farming de gear. "Tem qualquer peça T4+" tem baixo valor de filtro (quase todo hero ascended tem ao menos uma peça avançada). Trade-off: perde flexibilidade de "ao menos uma peça" mas ganha precisão.
- **Por que SI/F/Eng com presets fixos em vez de slider?** Os valores significativos são milestones (SI 10/20/30/40, F 3/6/9, Eng 60/80). Slider livre seria ruído — o user quer pensar em milestones do jogo, não em "37".
- **Por que `siMin > 0 && uh.signature_item < siMin` exclui (e não inclui) heróis com SI=0 quando filtro é `siMin=0`?** Lógica: `siMin=0` significa "qualquer" — não restringe. Só quando `siMin > 0` o filtro é aplicado.
- **Por que filtros avançados ignoram heróis fora do roster automaticamente?** "Heróis com SI≥30" implicitamente requer existir no roster (sem roster não há SI). Aplicar a filtros sem roster geraria 0 resultados confuso — exclusão silenciosa é mais previsível. Aviso textual no painel deixa isso explícito.
- **Por que filtro de Tier do catálogo está dentro do painel avançado?** Tier (Comum, Raro, Élite, Lendário, Mítico, Ascended, Celestial) é menos usado que faction/class no filtering — empurrar pro painel avançado libera o header pra os filtros frequentes.

#### Testes (`HeroIndexTest`)
4 cenários (2 novos):
- `payload_inclui_features_carregadas`
- `hero_sem_features_retorna_array_vazio`
- `roster_payload_inclui_campos_de_equipamento` — verifica que SI/F/Eng/Level + equipment tiers/levels + artifact_id + collection_id aparecem no payload
- `roster_isolado_por_user` — payload do user B não contém heroes do roster do user A

**411 testes verdes** (+2 novos).

### ✅ Fase 50 — Quantidade de equipes ajustável (criação + edição)

A opção "Quantidade de equipes" agora é sempre visível na criação (mesmo quando o tipo de desafio fixa em 1) e pode ser **alterada depois de criada** via dialog dedicado no editor — aumentar cria teams vazios nas novas posições, diminuir apaga os excedentes (com versionamento pra rollback).

#### Backend
- **Novo endpoint `PATCH /formations/{formation}/teams-count`** (`FormationController::updateTeamsCount`):
  - Valida `teams_count` contra `min_teams`/`max_teams` do `challenge_type` (não acopla com o `update` geral)
  - **Aumentar**: cria teams vazios nas posições `current+1..next`
  - **Diminuir**: deleta teams nas posições `> next` (cascade FK limpa `team_heroes`)
  - Snapshot via `FormationVersioning` antes de mexer (diminuir é destrutivo — user restaura via histórico se errar)
  - No-op silencioso quando `next === current`
- Rota nomeada `formations.teams-count.update` ordenada antes das rotas com `{team}` (route binding)

#### Frontend
- **`formations/create.tsx`**: input "Quantidade de equipes" **sempre visível** quando challenge type está selecionado. Quando `min === max`, vira readonly + disabled com mensagem "Este desafio requer exatamente N equipe(s)". Quando há range, mensagem "Entre X e Y equipes. Você pode alterar depois." reforça que é editável depois.
- **`components/teams-count-dialog.tsx`** (novo): dialog com input numérico clamped contra min/max, badge de aviso destacado quando user reduz ("vai apagar equipes X–N, heróis posicionados e beasts"), botão Aplicar desabilitado quando valor não mudou ou o desafio é fixed.
- **`formations/edit.tsx`**: na linha 2 do header, substitui badge estática por **botão clicável** "N equipe(s)" com ícone `Users` — abre o dialog. Quando `min === max`, fica visualmente estático e título `title` explica.

#### Trade-offs
- **Por que endpoint separado em vez de adicionar `teams_count` no `update` geral?** O `update` roda a cada save (heroes, beast, tags) e processa um array `teams` por posição. Embutir mudança de count exigiria validar/reordenar a cada save. Endpoint dedicado mantém o save quente sem regressão, e a mudança rara fica explicitamente isolada — mais fácil de adicionar UX como confirmação destrutiva, snapshot, etc.
- **Por que diminuir apaga sem perguntar de novo, dado que o dialog já avisa?** O dialog **é** a confirmação — mostra o aviso amarelo + lista das posições que somem. Confirm dialog adicional seria fricção redundante. Versão preservada no histórico (`FormationVersioning::snapshot`) cobre o "ops".
- **Por que snapshot de versão também ao aumentar (não só ao diminuir)?** Consistência operacional — qualquer mudança estrutural gera snapshot, padrão idêntico ao `update`. Custo é desprezível (1 row em `formation_versions`) e o user pode querer voltar ao estado anterior se experimentou e não gostou.
- **Por que `whereColumn` em vez de `DB::raw('max_teams')` no teste?** Diferença é semântica: `where('a', '!=', DB::raw('b'))` compara `a` com a **string literal** `"b"` no SQLite (e dá warning em alguns drivers). `whereColumn('a', '!=', 'b')` é a forma idiomática Laravel pra comparar duas colunas.
- **Por que arcane_labyrinth especificamente nos testes?** Range 1–5 é o mais amplo entre os com range no seeder; permite cobrir aumentar (2→4) e diminuir (4→2) num mesmo CT sem batê na borda. `heroes_of_esperia` (5–7) também tem range mas força usar valores ≥5 — menos flexível.
- **Por que sempre mostrar o input na criação (mesmo readonly)?** "Por que não vejo a opção?" foi a pergunta original. Esconder por completo deixa a feature invisível; mostrar readonly + texto explicativo educa o user sobre as regras do desafio e fica consistente com challenge types que aceitam range.
- **Por que o dialog faz `router.patch` (Inertia) e não `axios`?** O backend retorna `back()` (302); Inertia segue o redirect e recarrega os props (`teams`, `formation`). Com axios precisaria de reload manual. Inertia native é mais simples e mantém o histórico de navegação correto.

#### Testes (`FormationTeamsCountTest`)
8 cenários:
- `create_aceita_teams_count_dentro_do_range` — payload do form de criação respeita o valor escolhido
- `update_teams_count_aumenta_cria_teams_vazios` — 2 → 4 cria positions 3 e 4 vazios
- `update_teams_count_diminui_apaga_teams_excedentes` — 4 → 2 apaga positions 3, 4 + team_heroes via cascade
- `update_teams_count_no_op_quando_igual` — 3 → 3 sem alteração
- `update_teams_count_rejeita_acima_do_max` (422)
- `update_teams_count_rejeita_abaixo_do_min` (422)
- `update_teams_count_403_de_outro_user`
- `update_teams_count_gera_snapshot_de_versao` — `formation_versions` ganha 1 registro

**419 testes verdes** (+8 novos).

### ✅ Fase 51 — Refatoração do modelo de bestas (stats granulares + nível 1-36)

Schema antigo (`strength_buff`/`intelligence_buff`/`agility_buff` como inteiros) trocado por **JSON columns por scope** e **level 1-36 por team**. Reflete fielmente o jogo: cada besta dá stats granulares (PV, ATQ, DEF, Esquiva, Crit, Crit Amp, Percepção, Competência, Resist. Mágica, Bloq. Crítico, Vitalidade) divididos em 4 escopos.

#### Modelo do jogo (referência)
Exemplo fornecido pelo user — **Thistlekin (Flordecardo) nível 36**:
- Para heróis de **Força**: PV 17.59% · DEF 47.55% · Esquiva 24.23 · Vit 8.84 · TBC 10.61 · SM 66.09
- Para a **equipe** toda: PV 14.03% · ATQ 10.24% · DEF 37.93%

Bestas em outros tiers seguem proporção parecida ajustada por tier.

#### Schema
Migration `2026_05_16_030000_refactor_beasts_to_granular_buffs.php`:
- **Drop**: `strength_buff`, `intelligence_buff`, `agility_buff` (inteiros antigos)
- **Add em `beasts`**: `buffs_strength`, `buffs_intelligence`, `buffs_agility`, `buffs_team` (JSON nullable)
  - Cada um é um `{ stat_key: valor_% }` no nível máximo (36)
  - Ex: `{"hp": 17.59, "defense": 47.55, "magic_resistance": 66.09}`
- **Add em `formation_teams`**: `beast_level` (unsignedTinyInteger, default 36)

JSON aceita qualquer chave — admin pode adicionar stats não documentados sem schema change.

#### PowerCalculator (reformulado)
`PowerCalculator::beastBuffForType(?Beast $beast, ?string $heroType, int $beastLevel = 36)`:
1. Soma todos os valores % do scope correspondente ao type do hero (se houver)
2. Soma todos os valores % do scope team (aplica a todos)
3. Multiplica pelo factor de level: `level / MAX_LEVEL`
4. Caller (`forUserHero`/`forHero`) aplica o `BEAST_TIER_FACTOR`

Diferenças vs antigo:
- Heroes **sem type** ainda recebem buffs do scope team (antes recebiam 0)
- Level entra no cálculo (antes era assumido máximo implicitamente)
- Soma de stats granulares em vez de 1 valor por type

#### Endpoint `bestBeast`
Nova heurística:
```
score = Σ_typed_heroes (buffs_<type>_total)
      + buffs_team_total × total_hero_count
```
Mudanças vs Fase 48:
- Removido o rejeito `no_typed_heroes` — team com heroes sem type ainda recebe buff de scope team
- Detecta `beasts_without_buffs` somando os 4 scopes (não só os 3 antigos)

#### UI

**Admin form** (`/admin/beasts`):
- `components/beast-buffs-editor.tsx`: lista de 11 stats canônicos com inputs decimais %
- `components/beast-form-modal.tsx` reescrito com **tabs por scope** (Força/Inteligência/Agilidade/Equipe). Badge "ativos" no header de cada tab indica quantos stats estão preenchidos
- Stats: `hp`, `attack`, `defense`, `crit_rate`, `crit_amp`, `dodge`, `vitality`, `perception`, `proficiency`, `magic_resistance`, `crit_block_rate`

**Pool de bestas + slot por team**:
- `BeastSlot` ganha **input numérico de nível 1–36** ao lado do nome (default 36, modificável por team)
- Display resume buffs principais via `summarizeScope()` (top 2 do scope mais ativo)
- `BeastPool`: mesma summarização no item

**Página pública**:
- Mostra nível ao lado do nome da besta (`Nv 36`) e resumo dos buffs principais

**Helper** (`resources/js/lib/beast.ts`):
- `beastStatCounts(beast)` — contagem por scope (`{strength, intelligence, agility, team, total}`)
- `summarizeScope(buffs, limit)` — texto "ATQ +20% · Crit +15%"
- `buffsAtLevel(buffs, level)` — multiplica pelos `level/36`

#### Seed reformulado
Comando `php artisan beasts:seed-buffs` populado com valores realistas baseados em role+tier:
- **Thistlekin** com os valores exatos fornecidos pelo user (referência de ouro)
- Outras 27 bestas com valores heurísticos respeitando tier (S+ alto, F baixo) e role (Talismane→strength; Fox Fatale→intelligence; Slumber Seal→agility; Pandy Dumpty→team)
- `--force` sobrescreve buffs manuais
- Joyous Reamer não mapeado (sem tier conhecido) — fica vazio

#### Trade-offs
- **Por que JSON column e não tabela `beast_stats`?** Modelo do jogo tem muitos stats (~11 base + variantes); cada besta usa um subset diferente. JSON evita criar 30+ colunas ou uma tabela com 4 scopes × N stats × M bestas. Trade-off: queries por valor específico (ex: "qual besta dá mais Crit?") exigem JSON functions. Aceitável já que o consumo é via PowerCalculator (PHP soma em memória).
- **Por que escalar linearmente pelo level (factor `level/36`)?** O jogo provavelmente tem curva não-linear, mas sem documentação confiável. Linear é simples, transparente, e o erro relativo no scoring é pequeno comparado a outros fatores (SI, equipment, faction bonus).
- **Por que default `beast_level=36` (max)?** Pra não punir formations criadas antes desta fase — todas mantêm efeito de besta no max. Quando o user quiser refletir besta de fato no level menor, edita.
- **Por que `beastBuffForType` retorna 0 quando `Beast` é null mas considera team scope mesmo sem hero type?** Hero sem type ainda é parte da equipe — team scope aplica. Só ausência de besta resulta em 0.
- **Por que valores percentuais e não absolutos?** No jogo são %, e percentuais aplicados sobre os stats base do hero. O PowerCalculator soma como contribuição direta no power (heurístico) — o número final é uma comparação relativa, não exata.
- **Por que `buffs_team` no mesmo modelo de cada scope individual em vez de tabela separada?** Mesmo formato de dados (`{stat: %}`), uso simétrico no PowerCalculator. Tabela separada não traria benefício, só duplicação.
- **Por que UI de tabs no admin form em vez de 4 colunas?** 4 scopes × 11 stats = 44 inputs simultâneos = caos visual. Tabs concentram atenção em um scope por vez. Counter "ativos" ajuda a ver o que falta sem trocar de tab.
- **Por que input de level no `BeastSlot` e não no admin?** Level é per-team (cada user evolui suas bestas diferentemente); admin define apenas os valores no nível máximo (referência). Single source of truth.
- **Por que helper `summarizeScope` mostra só top N stats?** Cards/slots têm espaço limitado. Top stats por % dão sinal forte da identidade da besta; full list fica no admin form/popover.
- **Por que mantemos `BEAST_TIER_FACTOR`?** Já calibrado pra balancear bestas legacy vs nova meta (S+ vale 2× C). Refletir o tier oficial é independente do scope dos buffs.

#### Testes
- `FormationBeastTest` (12 cenários) reescrito pra usar JSON columns; novo `team_scope_aplica_a_todos` e `aplica_team_scope_mesmo_sem_heroes_tipados` (substitui `rejeita_heroes_sem_type` da Fase 48)
- `PowerCalculatorTest` (Unit) atualizado com 3 novos: `beast_buff_inclui_team_scope`, `beast_buff_escala_pelo_level`
- `BeastAdminTest` atualizado pra validar payload com `buffs_strength`/`buffs_team` em vez dos inteiros antigos

**423 testes verdes** (+4 novos no PowerCalculator + reformulação).

### ✅ Fase 52 — Engravings (Inscrições) integradas no scoring

Sistema de **Inscrições** (E30/E60/E80) já existia em `user_heroes.engraving` (0-100) e na catalog `hero_engravings`, mas o algoritmo só usava com peso linear desprezível (`engraving * 0.5`). Agora milestones disparam bônus discretos no **power** E o `TeamScorer` ganha um **eixo novo** ponderado por `engraving_weight` no profile.

#### Modelagem do milestone
No jogo, cada faixa libera unlocks reais:
- **E30** — primeiro tier ativo (~50 power)
- **E60** — combat tier (+100 power, cumulativo)
- **E80** — exclusivo Celestials/Hypogeans (+50 power, cumulativo)

Exemplo: hero E80 = 80×0.5 (linear) + 50 + 100 + 50 = **240 power** (antes 40).

#### Backend

`PowerCalculator`:
- `engravingMilestoneBonus(int $engraving): float` — retorna 0/50/150/200 conforme thresholds
- `engravingMilestoneLabel(int $engraving): ?string` — `'E30'/'E60'/'E80'` ou null (< 30)
- `forUserHero()` soma o bônus discreto no `$base`

`TeamScorer`:
- Novo método privado `engravingScore(heroes, userHeroesById)` retorna `{score, milestones: {e30, e60, e80}}`
  - Cada hero contribui: `+0.10 (E30) + 0.15 (E60) + 0.10 (E80)` (cumulativo)
  - Time totalmente E80 = `5 × 0.35 = 1.75` (max)
- `total` agora soma `engravingScore * profile->engravingWeight`
- `ScoreBreakdown` ganha `$engravingBonus` + entry `engraving_milestones` em details

`ScoringProfile`:
- Novo `$engravingWeight = 0.3` no construtor
- `fromArray()` + `withOverrides()` aceitam `engraving_weight`

`FormationController::extractScoringWeights()` retorna 8 chaves (era 7).

`ScoringWeightPreset::WEIGHT_KEYS` ganha `engraving_weight` (preset salvo no DB cobre o slider novo).

#### Frontend

**Badge reutilizável** (`components/engraving-badge.tsx`):
- Renderiza `E30/E60/E80` com cores graduais: âmbar (E30) → rose (E60) → fuchsia gradient (E80)
- 3 tamanhos: `xs` (9px), `sm` (10px, default), `md` (11px)
- Title HTML mostra o nível raw ("Inscrições 65")

**Helpers em `lib/afk.ts`**:
- `engravingMilestone(level)` — espelha a função PHP
- `ENGRAVING_MILESTONE_LABEL` + `ENGRAVING_MILESTONE_BADGE` (classes Tailwind)

**Aplicações:**
- `HeroCard` — badge ao lado de `Nv X · AS X` (rows wrap)
- `TeamSlot` — badge `xs` ancorada no canto inferior central (overlay sob portrait)
- `ScoreBreakdownCard` — nova linha "Inscrições" no detalhamento (entre Móveis F3/F9 e Total)
- `WeightTunerSheet` — novo slider "Inscrições" (8º), default 0.3
- `formations/compare.tsx` — novo eixo no diff lado-a-lado

**Backend passa map** `engravingByHeroId: Record<heroId, level>` pra `formations/edit.tsx` (consumido só pelo `TeamSlot`, sem aumentar payload em outras páginas).

#### Trade-offs

- **Por que milestones discretos em vez de só aumentar `engraving * 0.5`?** No jogo, E30/E60/E80 são unlocks **categóricos** (atributos novos ativam). Linear não modela "hero está perto do próximo unlock vs. já passou". Discreto reflete o salto real.
- **Por que `50/100/50` e não `50/50/50`?** E60 é onde a maior parte do ganho de combate real entra (segundo atributo majoritário). E80 é exclusivo de poucas factions, mas o investimento já dobrou em recursos — daí o bônus extra. Calibração heurística sujeita a refinamento futuro.
- **Por que o eixo novo no `TeamScorer` em vez de só somar via power?** O power já inclui o bônus (efeito direto no number). O eixo no scorer dá pra **ponderar separadamente por challenge_type** (PvE pesa menos, PvP pesa mais) e expor no breakdown como linha distinta — usuário entende "minhas inscrições subiram 0.4 do score do time" sem ter que decompor power.
- **Por que `engravingWeight = 0.3` (default)?** Time full E80 contribui 1.75 × 0.3 = 0.525 — comparável ao `feature_bonus` (0.5) e ao `furniture_effect_weight` (0.4). Pesa menos que faction/synergy mas é relevante. Ajustável por challenge_type no scoring_profile JSON.
- **Por que cumulativo em vez de "maior milestone só"?** Cumulativo = invariante natural: hero E60 dominado por hero E30 sempre. Não-cumulativo criaria casos esquisitos (hero E59 = 50, hero E60 = só 100 sem o 50 do E30).
- **Por que `engraving_milestones` em details retorna contagens (não bonus por hero)?** Display friendly: "3 heroes E60+" é mais útil que "0.45 score". Bonus pode ser inferido via `score`. Mantém payload pequeno.
- **Por que `BadgeEngraving` aceita `null/undefined` e retorna null?** API friendly — caller passa `userHero?.engraving` sem checar antes.
- **Por que badge no `TeamSlot` é overlay e não inline?** Espaço escasso (slots 96×96 em desktop). Overlay no canto inferior preserva o portrait + permite escalar badge pra `xs` sem quebrar layout.
- **Por que mostro o nível raw no `title` (tooltip) e não na label?** Label fixa por milestone (E30/E60/E80) é mais escaneável. Quem quer o nível exato passa o mouse.

#### Testes

`PowerCalculatorTest` (Unit) — 3 novos:
- `engraving_milestone_bonus_aplica_em_thresholds` — testa 0/29/30/59/60/79/80/100
- `engraving_milestone_label` — equivalente para labels
- `for_user_hero_inclui_engraving_milestone_bonus` — diff eng29→eng30 = +50.5 (50 milestone + 0.5 linear)

3 testes ajustados:
- `test_formula_basica_sem_furniture` — eng 60 agora soma +150 (E30+E60)
- `test_furniture_9_adiciona_bonus_f3_e_f9_cumulativo` — eng 100 agora soma +200
- `test_for_user_hero_sem_hero_mantem_calculo_antigo` — idem

`TeamScorerTest` (Feature) — 3 novos:
- `engraving_bonus_acumula_milestones_por_hero` — heroes com eng 29/30/80 → 0.45 bonus + counts `{e30: 2, e60: 1, e80: 1}`
- `engraving_bonus_zero_sem_roster` — target mode (sem userHeroesById) → 0.0
- `engraving_weight_aplica_no_total` — peso 2.0 → total = engravingBonus × 2

`FormationEditScoreBreakdownTest` atualizado pra esperar a chave `engraving_bonus` no payload.

**429 testes verdes** (+6 novos: 3 unit PowerCalculator + 3 feature TeamScorer).

### ✅ Fase 53 — Página de análise do roster (`/roster/analytics`)

Dashboard dedicada com visão profunda do roster: distribuição de power por facção/classe/tier, médias de investimento (SI/F/Eng/Level/Ascensão), top 10 heroes por power, KPIs de "investidos" e lacunas detectadas (facções sub-investidas, classes faltando). Complementa o `/dashboard` (overview rápida) com análise dirigida a decisões de farming.

#### Backend
`RosterAnalyticsController::index(Request, PowerCalculator)`:
- Carrega `UserHero` com eager-load completo (hero + faction/class/tier/stats + artifact + collection)
- Calcula `powerByHeroId` em 1 passada via `PowerCalculator::forUserHero`
- Agrega em 6 outputs:
  - **`summary`**: total_power, avg/max_power, avg_level/SI/F/Eng/ascension, invested_count
  - **`byFaction`**: bucket por facção (8) com count, total_power, avg_power, invested_count
  - **`byClass`**: bucket por classe (5) com mesma estrutura
  - **`byTier`**: bucket por tier (3) só com count/power (sem invested_count — tier não muda com investimento)
  - **`top10`**: array sorted DESC de heroes com slug/name/portrait/stats + power
  - **`gaps`**: array de objetos `{type, severity, label, hint}` reportando lacunas

**Heurística de "investido"**: hero atinge UM dos thresholds → `ascension_value >= 10` (Ascended/Mythic+) OU `signature_item >= 30` OU `furniture >= 9`. Engraving 80 sozinho **não conta** porque é late-game e raro — não penaliza users mid-game.

**Detecção de gaps**:
- `faction_underinvested`: facção com < 3 heroes investidos (severity `high` se 0, senão `medium`)
- `class_missing`: classe sem nenhum hero (severity `high`)
- `class_underinvested`: classe com heroes mas nenhum investido (severity `low`)

#### Frontend
- **`pages/roster/analytics.tsx`** — página com 5 seções:
  1. **KPIs** (4 cards): roster size, total power, max power + nome do top hero, investimento médio (`AS X · FY · EZ`)
  2. **Lacunas detectadas** (se houver) — grid 2 cols com cards coloridos por severity (rose=high, amber=medium, zinc=low)
  3. **Power por facção** — `BarChart` Recharts com cores das facções + grid de legendas abaixo
  4. **Power por classe + Power por tier** (lado a lado em desktop) — barras horizontais % do total
  5. **Top 10 por power** — lista numerada com portrait, badges de investimento, `EngravingBadge`, valor de power
- **Empty state** quando roster vazio: ícone + CTA "Ir para o catálogo"

#### Navegação
- Item "Análise do roster" no sidebar entre "Uso de heróis" e "Formações" (ícone `Gauge`)
- Rota `GET /roster/analytics` nomeada `roster.analytics` no grupo auth

#### Trade-offs
- **Por que página separada e não tab no Dashboard?** Dashboard é entry point (overview rápida + ações comuns). Analytics é deep-dive — usuário entra explicitamente quando quer analisar gaps/distribuição. Mistura faria Dashboard pesar mais.
- **Por que `byFaction` retorna 8 buckets mesmo com count=0?** Render do chart fica consistente (mesma ordem, mesmas cores). Gaps inferem o "faltando" via `invested_count`, não via ausência. Tradeoff: payload sempre 8 itens vs zerados — irrelevante (poucos KB).
- **Por que invested_count com 3 critérios OR (ASC/SI/F) e não AND?** O jogo permite caminhos diferentes (algumas factions ganham mais com SI, outras com F). Critério OR captura "user fez investimento real", critério AND seria estrito demais e penalizaria caminhos legítimos.
- **Por que MIN_INVESTED_PER_FACTION = 3?** No PvE multi-team (Heroes of Esperia, 5 equipes), cada facção típica precisa ao menos 3 heroes pra rotacionar. Menor que isso = lacuna real.
- **Por que `class_missing` é severity `high` e `class_underinvested` é `low`?** Faltar Tank/Support quebra scoring no `TeamScorer::classBalance` (−0.3 cada). Ter classe sem investimento ainda permite usar pra completar 5-stacks, só não como carry/core.
- **Por que mostrar `avg_*` em vez de medianas?** Média é mais legível pro user médio (não precisa explicar mediana). Para roster pequeno (< 10), distorção é aceitável; pra roster grande, ambas convergem.
- **Por que power calculado em PHP em vez de SQL?** `PowerCalculator` tem 30+ regras (stats, equipment, artifact, collection, beast) — replicar em SQL viraria query monstro. O eager-load + loop PHP é O(N) sobre roster e respeita single source of truth.
- **Por que cores das facções hardcoded no front (não vêm do backend)?** Já temos `FACTION_THEME` em `lib/afk.ts` com gradientes para cards; cores sólidas pra Recharts são uma necessidade nova e específica do componente — ficam locais à página.
- **Por que `top10` e não `top20`?** Visual fit: 10 entries cabem confortavelmente sem scroll na maioria das telas. User com roster grande já tem `/heroes` com sort por power pra ver lista completa.
- **Por que Engraving 80 não conta como "investido"?** É um critério a mais que segrega heroes que já passaram outros gates. Sair de 3 critérios pra 4 com OR diluiria o sinal — qualquer hero E80 já é Ascended (não há rota pra E80 sem first ser Mythic+).

#### Testes (`RosterAnalyticsTest`)
5 cenários:
- `guest_redireciona_para_login`
- `roster_vazio_renderiza_componente_com_size_zero` — payload tem buckets zerados mas chart-friendly
- `payload_calcula_summary_e_distribuicoes` — 3 heroes diferentes → counts corretos por facção/classe/tier
- `top10_ordenado_por_power_desc` — hero forte aparece antes do fraco
- `gaps_reporta_faccoes_sub_investidas` — 1 hero solo → 8 gaps de facção + 4 de classe missing

**434 testes verdes** (+5 novos).

### ✅ Fase 54 — Planner de investimento (`/roster/planner`)

Sugere onde investir o próximo recurso para maximizar power: simula upgrades candidatos (próximo milestone de SI, F3/F9, E30/E60/E80, milestones de Ascensão) em cada hero do roster, calcula o **ROI** (`power_gain / cost`), aplica multiplicadores contextuais (facção sub-investida, tier Ascended, Awakened, presença na formação selecionada) e retorna a lista ordenada por score decrescente.

Complementa a Fase 53 (`/roster/analytics`) que mostra **o estado** — o planner é **o que fazer a seguir**.

#### Service `InvestmentPlanner`

Em `app/Services/Optimization/InvestmentPlanner.php`:
- `plan(User $user, ?Formation $formation = null, int $topN = 10): array`
- Carrega `UserHero` com eager-load (mesma assinatura que o PowerCalculator espera)
- Para cada hero, gera `candidateUpgrades()`:
  - **SI**: próximo milestone (30 ou cap da facção); respeita `SignatureItemCost::capForFaction()` (40 só pra Celestial/Hypogean)
  - **Furniture**: 0→3 ou 3→9 (não cada peça)
  - **Engraving**: 30/60/80 (próximo só)
  - **Ascension**: próximo de 4/10/12/16 (milestones que mudam tier ou destravam ★)
- Para cada upgrade, simula via `UserHero::replicate()` + `setRelation`, chama `PowerCalculator::forUserHero` no estado novo, calcula `power_gain`
- Filtra upgrades com `power_gain ≤ 0` (não vale sugerir)
- Cost em "unidade abstrata":
  - SI: usa `SignatureItemCost::cumulativeCostUpToLevel()` real (emblemas)
  - Furniture: `250` por peça abaixo de F3, `300` de F3→F9
  - Engraving: `30/70/150` por nível em cada faixa (E0-30, 30-60, 60-80)
  - Ascension: `400` por step

Score base = `power_gain / max(1, cost)`. Multiplicadores aplicados:
- `FACTION_GAP_MULTIPLIER (1.20)` — facção com < 3 heros investidos no roster
- `ASCENDED_TIER_MULTIPLIER (1.15)` — tier Ascended (catálogo)
- `AWAKENED_MULTIPLIER (1.25)` — slug != `Str::slug(name)` (desperto)
- `IN_FORMATION_MULTIPLIER (1.50)` — hero está numa formation selecionada

Reasons retornados como strings (`faction_gap`, `tier_ascended`, `awakened`, `in_formation`) → frontend mapeia pra badges coloridos.

#### Backend
`RosterPlannerController::index`:
- Aceita `?formation_id=N&top=12` (validados; formation_id verificado pertencer ao user)
- Retorna `suggestions`, `formations` (dropdown), `selectedFormationId`, `rosterSize`, `topN`
- Rota `GET /roster/planner` nomeada `roster.planner`

#### Frontend
- **`pages/roster/planner.tsx`**:
  - Header com explicação do ROI + dropdown "Escopo: Roster inteiro / Formação X"
  - Lista numerada (1-12) de sugestões. Cada card mostra:
    - portrait + nome + Nv X · Classe (· badge Ascended)
    - badge do tipo do upgrade (Assinatura/Móveis/Inscrições/Ascensão) com cor diferenciada (âmbar/azul/rose/verde)
    - badge "+X power" verde
    - label de custo (Baixo/Médio/Alto/Muito alto) com cor (emerald→rose)
    - chips de reasons (Facção sub-investida, Tier Ascended, Desperto, Na formação)
    - botão "Editar" (link pra `/heroes?edit=slug`)
  - Empty state quando roster vazio ou tudo no max
  - Filtro de formation faz `router.get(preserveState: true)` — recarrega só os props
- **Sidebar**: item "Planner de investimento" (ícone `Compass`) abaixo de "Análise do roster"

#### Trade-offs
- **Por que simular o PowerCalculator com `replicate()` em vez de calcular delta analítico?** Power tem 30+ regras (stats, equipment, artifact, F3/F9 bonus de unlock, beast, etc) — duplicar a fórmula daria 2 places of truth. Simular custa O(N × upgrades) chamadas in-memory, sem queries adicionais (relations já carregadas).
- **Por que cost em "unidade abstrata" e não emblemas reais?** Os tipos de recurso são diferentes (emblemas SI, fragmentos de móveis, scrolls de engraving, fragmentos do hero) — não há conversão direta. A unidade abstrata serve como **divisor** pra comparar ROI entre tipos. User vê apenas o label (Baixo/Médio/Alto), não o valor cru.
- **Por que apenas o **próximo** milestone e não todos?** Sugerir "SI 10→40 (+260 power)" é menos acionável que "SI 10→30 (+140 power)". O user vai um milestone por vez. Ranking destaca **próxima ação concreta**.
- **Por que Ascensão usa milestones (4/10/12/16) em vez de cada step?** Steps intermediários (Mythic→Mythic+) dão pouco power individualmente; o salto que importa é o que destrava tier (Legendary, Mythic+, Ascended, Ascended 5★).
- **Por que `IN_FORMATION_MULTIPLIER (1.50)` é o mais alto?** Quando user diz "fortalece esta formação", priorizar heros dessa formation é o objetivo explícito. Multipliers menores podem afundar heros da formation pelos contextuais.
- **Por que `FACTION_GAP_MULTIPLIER` ativa com `< 3 investidos`?** Mesma heurística da Fase 53 (`MIN_INVESTED_PER_FACTION = 3`). Coerência conceitual entre as duas páginas — "facção sub-investida" tem o mesmo significado.
- **Por que não persistir o plano?** Plano é função pura do roster + formation → sempre regenerável. Persistir adicionaria invalidation/staleness sem benefício (não há histórico útil de "qual plano eu tinha ontem").
- **Por que `topN = 12` default (não 10)?** Lista de 10 fica próxima da fold visual em mobile; 12 dá espaço pra explorar mais opções sem precisar paginar.
- **Por que `power_gain ≤ 0` filtra a sugestão (em vez de mostrar com aviso)?** Upgrade que não dá power é não-sugestão. Não há contexto onde "ir pra E30 sem power gain" seria útil.

#### Testes (`InvestmentPlannerTest`)
8 cenários:
- `roster_vazio_retorna_array_vazio` — short-circuit sem queries
- `sugere_upgrade_para_hero_com_si_baixo` — SI 10 → próximo step é 30
- `hero_no_max_de_si_nao_recebe_sugestao_de_si` — Lightbearer SI 30 → sem SI sug (outros tipos sim)
- `celestial_pode_subir_para_si_40` — cap 40 respeitado
- `formation_scope_aplica_multiplier_in_formation` — heros idênticos, só o da formation tem score maior + reason `in_formation`
- `awakened_hero_recebe_multiplier` — slug `lucius-awakened` → reason `awakened` no topo
- `pagina_planner_retorna_payload_correto` — Inertia render com props esperadas
- `pagina_planner_guest_redireciona` — auth middleware

**442 testes verdes** (+8 novos).

### ✅ Fase 55 — Resource tracker integrado ao Planner

O user agora informa quantos recursos tem em estoque; o Planner usa essa info pra marcar quais sugestões **cabem no orçamento atual** e oferecer um filtro "Só sugestões que cabem". Completa a Fase 54 — antes o planner só falava "isso é mais ROI", agora também avalia "isso você consegue pagar agora".

#### Tipos de recursos rastreados (5)

Alinhados com `SignatureItemCost::EMBLEM_*` + pools simplificados:

- **`primordial_emblems`** — SI +1 a +20
- **`amplifying_emblems`** — SI +21 a +30
- **`faction_emblems`** — SI +31 a +40 (Celestial/Hypogean only)
- **`mythic_furniture`** — pool genérico (agrega variantes)
- **`engraving_scrolls`** — pool genérico (agrega variantes)

Ascensão **não** tem pool global rastreável (frags são por hero específico) → `resource_cost = []` → sempre affordable (não bloqueia).

#### Schema
Migration `2026_05_17_010000_create_user_resources_table`:
- 1:1 com `users` via FK unique cascade
- 5 colunas `unsignedInteger` default 0
- timestamps

Model `UserResource` com `FIELDS` const exposto pra reusar em controller (fillable + validation rules).

`User::resources()` retorna `HasOne`.

#### Backend
- **`UserResourceController::update`** (PATCH `/roster/resources`):
  - Aceita JSON com **subset** de FIELDS (campos ausentes preservam valor atual via `updateOrCreate`)
  - Validação: `integer min:0 max:99999999` por campo
  - Retorna `{resources: {...}}` (200)
- **`RosterPlannerController`** carrega/cria `UserResource` ao acessar `/roster/planner`, passa pro `InvestmentPlanner::plan()` e injeta no payload Inertia
- **`InvestmentPlanner::plan()`** ganha 4º param `?UserResource $resources`. Cada sugestão recebe:
  - `resource_cost`: map por recurso (null se chamada sem recursos)
  - `affordable`: bool (null se sem recursos)
- **`InvestmentPlanner::resourceCostFor()`** public — computa custo concreto por recurso:
  - SI: query em `signature_item_costs` no range `[from+1, to]` agrupando por `emblem_type`
  - Furniture/Engraving: 1 unidade por step (delta = to - from)
  - Ascension: array vazio
- `isAffordable()` retorna true se `array_empty(resource_cost)` OU **todos** os requisitos `<= resources[field]`

#### Frontend
- **`components/resource-editor-dialog.tsx`** — dialog com 5 inputs numéricos sanitizados (regex `[^\d]`), labels + hints PT-BR, salva via axios PATCH e atualiza state via callback
- **`pages/roster/planner.tsx`** — novo card abaixo do header:
  - Linha de chips com cada recurso atual (`Primordiais: 1.234 · Amplificantes: 567 · ...`)
  - Checkbox "Só sugestões que cabem no orçamento" (visível só se algum recurso > 0)
  - Botão "Editar recursos" abre o dialog
  - Texto explicativo quando recursos zerados ("Informe seus recursos pra destacar...")
- Cada sugestão ganha badge:
  - **Cabe agora** (verde, ícone CheckCircle2) se `affordable === true`
  - **Falta recursos** (rose, ícone XCircle, com `title` listando o que falta) se `affordable === false`
- Após salvar recursos, `router.reload({only: ['suggestions', 'resources']})` recalcula só esses dois props sem perder scroll/state

#### Trade-offs
- **Por que 1 pool genérico pra furniture e engraving (não múltiplos tipos)?** O jogo distingue tipos (regular/elite/mythic furniture; common/rare/mythic scrolls). Rastrear todos exigiria 6+ inputs adicionais e o user provavelmente não conta separadamente. Pool agregado dá 80% do valor com 20% da fricção.
- **Por que ascension sempre affordable em vez de excluí-la das sugestões?** Excluir penaliza users que ainda querem ver "subir asc é boa ideia" sem precisar rastrear frags. Marca como affordable (=neutro) preserva info sem bloquear.
- **Por que `updateOrCreate` em vez de `firstOrCreate` + `update`?** Operação atômica + idempotente. O subset patch é semântica natural ("atualizo só o que mandei").
- **Por que validar `max:99999999`?** unsignedInteger MySQL max é ~4B; 99.9M é folga confortável que ainda cabe em UI/display sem quebrar layout. Investimento real raramente passa de dezenas de milhões.
- **Por que dialog em vez de página dedicada?** Recursos não são "conteúdo" pra navegar — é input rápido contextualizado pelo planner. Dialog mantém foco e evita context-switch desnecessário.
- **Por que `router.reload({only: [...]})` em vez de re-fetch axios?** O Inertia mantém props sincronizadas e roteamento consistente. Axios duplicaria a lógica de carregar suggestions com formation_id atual.
- **Por que recurso `null` no `resource_cost` em chamadas sem recursos (Fase 54)?** Backward compat: o teste antigo `pagina_planner_retorna_payload_correto` ainda passa porque ascende a um `UserResource` automático (`firstOrCreate`). Mas o service em si mantém comportamento antigo se `?UserResource = null`.
- **Por que faction_emblems é coluna separada (não derivada via emblem_type)?** Pra simplificar UI: user vê 3 tipos distintos pra contabilizar. Reconciliação no backend evita lookup de seeder na hora da subtração.

#### Testes (`InvestmentPlannerTest` + `UserResourceTest`)
6 novos cenários no `InvestmentPlannerTest`:
- `resource_cost_emblemas_si_diferentes_buckets` — SI 0→30 retorna primordial+amplifying; SI 30→40 retorna só faction
- `resource_cost_furniture_e_engraving_pool` — delta unitário (F0→F3 = 3; E30→E60 = 30)
- `affordable_flag_quando_recursos_passados` — recursos altos pra SI → true; recursos zero pra furniture/engraving → false
- `affordable_null_quando_recursos_ausentes` — call sem `$resources` mantém comportamento antigo
- `ascension_sem_pool_e_sempre_affordable` — recursos zerados não bloqueiam asc

6 cenários em `UserResourceTest` (novo):
- `guest_rejeitado` (401)
- `update_cria_registro_quando_nao_existe` — `updateOrCreate` insert path
- `update_atualiza_subset_preservando_outros_campos` — patch parcial
- `validacao_rejeita_valores_negativos` (422)
- `validacao_rejeita_overflow` (422 quando > 99.999.999)
- `user_so_atualiza_proprios_recursos` — isolamento por user_id

**453 testes verdes** (+11 novos).

### ✅ Fase 56 — Cristal Ressonante (guia + tracker + integração)

Sistema completo pra rastrear o **Cristal Ressonante** do AFK Arena: guia textual rico, página dedicada `/cristal` com pentagrama auto-detectado e fortalecimento custom, integração no Analytics e Planner. Entregue em 3 sub-fases pra commits limpos.

#### 56a — Guia textual

Renomeou slug typo `critico-ressonante` → `cristal-ressonante` e substituiu content fetched-from-wiki por **markdown PT-BR rico** em `database/seeders/guides/cristal-ressonante.md`:
- Visão geral + lore breve
- Modo pentagrama (pré-240): regras de slots, cooldown 24h, exceções
- Modo upgrade (pós-240): barra de progresso, fortalecer ×10 por nível
- Limite de nível: 240 + 5 × ascendidos não-dimensionais (cap 475)
- Tabela de custos (selecionados: 240/250/.../475)
- Recursos: Essências Revigorantes / Ouro / EXP / Essência de Herói
- Estratégia recomendada

`GuideSeeder` ganhou suporte a `static_content` (markdown do disco) com **fallback** pro fetch da Fandom Wiki. Slug antigo é deletado no início do `run()`.

Commit: `ed1d25c`.

#### 56b — Tracker completo

**Schema:**
- Migration extende `user_resources` com 4 colunas: `revigorating_essences`, `gold` (bigInt), `hero_exp` (bigInt), `hero_essence` — recursos que cabem tanto no Planner quanto no Cristal
- Nova tabela `resonating_crystal_costs` (PK = level 240-501, com `gold_cost`, `hero_exp_cost`, `dust_cost`) populada via `ResonatingCrystalCostsSeeder` (262 níveis)
- Nova tabela `user_resonating_crystals` (1:1 user): `level` (default 240), `strengthen_progress` (0-9), `slots_unlocked` (default 5)

`UserResonatingCrystal` constants:
- `MIN_LEVEL=240`, `MAX_LEVEL=501`, `STRENGTHENS_PER_LEVEL=10`, `LEVEL_CAP_PER_ASCENDED=5`
- `levelCapForUser(User)`: 240 + 5 × `count(ascendidos não-dimensional)`, max 501

**Backend** (`ResonatingCrystalController`):
- `show` — calcula `effective_level` (5º maior pré-upgrade ou `crystal.level` pós-upgrade), `level_cap`, `can_upgrade`, custo do próximo fortalecimento (rateio do ciclo + dust no 10º)
- `update` — sync manual de level/progress/slots (PATCH)
- `strengthen` — debita recursos atomicamente via `DB::transaction`, incrementa `strengthen_progress`; ao chegar em 10, sobe `level` e zera `progress`

**Rotas:**
- GET `/cristal` (`cristal.show`)
- PATCH `/cristal` (`cristal.update`)
- POST `/cristal/strengthen` (`cristal.strengthen`)

**UI** (`pages/cristal/index.tsx`):
- 3 KPIs: Nível efetivo · Teto destravado · Slots desbloqueados
- Pentagrama: top 5 do roster ordenado DESC, 5º hero destacado (define nível compartilhado)
- Card "Fortalecer cristal" (só pós-upgrade) com:
  - Barra de progresso 0-10 colorida (amber → rose)
  - Grid de custos por fortalecimento (ouro/EXP/essência) com cores baseadas em recursos atuais
  - Custo total do ciclo abaixo
  - Botão "Fortalecer ×1" que chama o endpoint
  - Estado de erro inline (insufficient/cap reached)
- Card de recursos com 4 chips + botões "Sincronizar com o jogo" e "Editar recursos"
- Dialog de sync (`level` + `strengthen_progress` + `slots_unlocked`)
- `ResourceEditorDialog` estendido com 4 novos campos
- Sidebar item "Cristal Ressonante" (ícone `Gem`)

**Defaults via `$attributes` no model**: SQLite (testes) ignora defaults do schema; setar no atributo do model garante consistência.

Commit: `cb074e4`.

#### 56c — Integração no Analytics e Planner

**`RosterAnalyticsController`** ganha `pentagramSummary()`:
- Top 5 do roster ordenado por nível (mini-portraits + slug + faction theme)
- `shared_level` (5º maior) e `is_at_240` (flag de "pronto pra upgrade")
- `level_cap` via `UserResonatingCrystal::levelCapForUser`

Página `/roster/analytics` ganha card "Pentagrama (Cristal Ressonante)" entre KPIs e Lacunas com:
- Badge do nível compartilhado e "Pronto pra upgrade" quando aplicável
- Link "Detalhes →" pra `/cristal`
- 5 mini-cards (3 cols mobile, 5 desktop) com 5º hero highlighted
- Texto explicativo dinâmico ("Teto: X" pós-240, ou "Subir o 5º eleva o cristal inteiro" pré-240)

**`InvestmentPlanner`** ganha:
- Const `IN_PENTAGRAM_MULTIPLIER = 1.30`
- `buildContext` calcula `pentagram_hero_ids` (top 5 do roster por level)
- Sugestão pra hero no pentagrama recebe `multiplier × 1.30` + reason `in_pentagram`

Página `/roster/planner` ganha entry em `REASON_LABEL` com badge âmbar "No pentagrama".

#### Trade-offs

- **Por que 1:1 `user_resonating_crystals` (não embedar em users)?** Mantém users table enxuta. A relação 1:1 com lazy create via `firstOrCreate` no controller é trivial.
- **Por que `slots_unlocked` é manual (não derivado de essências consumidas)?** O usuário pode ter destravado slots no jogo antes de começar a usar o app. Sync manual é mais honesto que tentar reconstruir histórico.
- **Por que `strengthen()` faz transação em vez de incrementos diretos?** Decremento de 3 recursos + update de 2 campos precisa atomicidade. Sem transação, falha parcial deixaria estado inválido.
- **Por que custos no DB e não em PHP const?** 262 níveis = 262 linhas de array. Tabela permite query/seeder limpo, indexação por level, e atualização incremental quando o jogo subir o cap.
- **Por que rateio `ceil()` no custo por fortalecimento?** Se o total não divide por 10 perfeitamente, `floor()` resultaria em "sobra" não-cobrada no último. `ceil()` arredonda pra cima, garantindo que 10 fortalecimentos × cost-por-fortalecimento ≥ cost total.
- **Por que o 10º cobra `dust_cost` total (não rateado)?** Espelha o jogo: essência só entra no último step. Modelo "complete o ciclo" — quem chega no 10 paga.
- **Por que `level_cap` desconta Dimensionais?** Regra documentada do jogo. Dimensionais (slots auto-rotacionados) não contam pro teto.
- **Por que `IN_PENTAGRAM_MULTIPLIER = 1.30`?** Menor que `IN_FORMATION (1.50)` porque o usuário não escolheu explicitamente o pentagrama (é auto-detectado). Maior que `TIER_ASCENDED (1.15)` porque pentagrama é o gatilho real pra subir cristal inteiro — alto valor estratégico.
- **Por que `effective_level` é computed (não persistido)?** Pré-upgrade depende do top 5 do roster (varia constantemente). Persistir geraria staleness. Read derived é simples e correto.
- **Por que setar `$attributes` defaults no model em vez de só na migration?** SQLite (testes) ignora defaults do schema em alguns casos. `$attributes` garante consistência sem precisar mexer no driver.
- **Por que tabela de costs é PK = level (não auto-increment)?** `level` é único, ordenado, e usado como lookup principal (`forLevel($n)`). PK natural elimina coluna `id` redundante.
- **Por que UI deixa o user fortalecer mesmo sem ter realmente upgrado o cristal no jogo?** App é tracker, não fonte da verdade. User pode usar como "simulador" pra planejar quanto tempo até X nível.

#### Testes

**`ResonatingCrystalTest` (10 cenários):**
- `guest_redireciona`
- `show_inicializa_crystal_e_resources` — firstOrCreate cria registros corretos
- `pentagram_lista_top_5_por_level`
- `is_upgraded_quando_5_heros_no_240` — flag flip + cap = 265
- `dimensionais_nao_contam_no_level_cap` — 3 reg + 3 dim → cap 255
- `update_aceita_sync_de_level` (PATCH)
- `update_rejeita_level_abaixo_de_240` (422)
- `strengthen_falha_sem_recursos` (422 `insufficient_resources`)
- `strengthen_incrementa_progress_e_debita_recursos`
- `strengthen_10x_sobe_level_e_zera_progress`

**`RosterAnalyticsTest`** +1: `payload_inclui_pentagrama_do_cristal`

**`InvestmentPlannerTest`** +1: `pentagram_hero_recebe_multiplier_in_pentagram`

**465 testes verdes** (+12 nesta fase: 10 + 1 + 1).

---

### ✅ Fase 57 — Comparação de heróis lado a lado

Página `/roster/compare` que permite comparar 2-4 heróis do roster do user em formato grid, com destaque automático do "vencedor" por métrica e próximos upgrades por hero via `InvestmentPlanner::planForHero()`.

**Arquitetura:**
- Rota GET `/roster/compare` aceita query string `heroes[]=<slug>` (2-4 slugs, validados com regex `^[a-z0-9-]+$`).
- `HeroComparisonController::index()` valida, carrega só `UserHero` do user logado (filtro `whereHas('hero')` na slug list, segurança built-in: outsider slugs viram payload vazio), e monta payload por hero com power breakdown (stat/equipment/artifact/collection) + top 3 upgrades.
- Ordem dos slugs na URL é preservada (não usa `keyBy` no return — itera pela ordem original).
- Slugs duplicados na query são deduplicados via `unique()`.

**Service estendido — `InvestmentPlanner::planForHero()`:**

```php
public function planForHero(User $user, UserHero $userHero, ?Formation $formation = null, ?UserResource $resources = null): array
```

Reusa o `buildContext()` completo do roster (faction_gap, pentagram_hero_ids) e gera só sugestões do hero alvo, sem cap de `topN`. Crítico: carrega o user inteiro pra contexto correto — investir num pentagram hero deve mostrar o multiplier de 1.30× nas razões.

**Frontend — `pages/roster/compare.tsx`:**

Layout grid responsivo (`grid-cols-2/3/4` conforme `heroes.length`):
- Header com chips dos heros selecionados + picker autocompletar (busca por nome no roster, max 30 sugestões).
- Card por hero com:
  - Portrait + nome + tier badge + faction + class
  - **Power total** com badge "Maior" + ring se vencedor único da métrica
  - Métricas comparáveis (Nível, Ascensão, AS, F, E) — cada uma destaca o card vencedor com bg-amber + Trophy icon
  - Equipamento por slot (4 slots) com `EQUIPMENT_TIER_BADGE` colorido
  - Artefato/Coleção quando presentes
  - Stats base (contribuição do catálogo)
  - Próximos 3 upgrades (kind/from→to/power_gain/affordable)

**Algoritmo de "vencedor":**
```ts
function winnerIndex(values: number[]): number | null {
    if (values.length === 0) return null;
    const max = Math.max(...values);
    if (max <= 0) return null;
    const winners = values.map((v, i) => (v === max ? i : -1)).filter((i) => i !== -1);
    return winners.length === 1 ? winners[0] : null;
}
```

Empate → ninguém destacado. Tudo zero → ninguém destacado. Só um índice é vencedor (não retorna lista) — UI mais limpa.

**Trade-offs:**
- **Slug como param em vez de ID**: URL fica legível (`/roster/compare?heroes[]=alyssa&heroes[]=lucretia`) e shareable. Custo: validação regex no controller.
- **Top 3 upgrades por card (não 10)**: cabe no card sem overflow vertical. Power user pode ir ao Planner pra ver lista completa.
- **`planForHero` reutiliza `plan()` internamente**: usa o mesmo `buildContext` (que carrega o roster inteiro), evita drift entre as duas APIs. Custo: 1 query extra de `userHeroes` por hero comparado — aceitável pra max 4 heros.
- **Sem persistência da seleção**: comparação é stateless — slug list vive só na URL. Usuário pode bookmark uma comparação específica.
- **Sem export PNG**: ficou pra Fase 58 (votada como próxima opção potencial).

**Navegação:**
- Sidebar: novo item "Comparar heróis" (ícone `Scale`) entre Planner e Cristal.
- Analytics top 10: botão "Comparar top N" no header da seção (N=min(top10.length, 4)) que pré-popula a URL.

**Tests — `HeroComparisonTest` (9 cenários):**
- `guest_redireciona_para_login`
- `renderiza_pagina_vazia_quando_sem_heroes_param`
- `lista_roster_options_para_picker`
- `compara_dois_heros_com_payload_completo` — power diff + equipment + upgrades shape
- `preserva_ordem_dos_slugs_na_query` — `?heroes[]=gamma&heroes[]=alpha&heroes[]=beta` retorna `[gamma, alpha, beta]`
- `hero_que_nao_pertence_ao_user_e_ignorado` — segurança: slug do roster de outro user vira no-op
- `max_heroes_e_respeitado_via_validacao` — 5 slugs → 302 redirect (validação fail em GET Inertia)
- `slugs_duplicados_sao_deduplicados`
- `power_breakdown_inclui_componentes` — T6 Lv20 × 4 = 400 confirmado

**474 testes verdes** (+9 nesta fase). TypeScript ✅ ESLint ✅.

---

### Calibração — Stats reais no PowerCalculator (2026-05-20)

Quando o player preenche stats reais do jogo no `UserHero` (HP/ATK/DEF/CRIT/etc), o `PowerCalculator` agora **prefere essas leituras empíricas** sobre o baseline do catálogo (`HeroStat`, que vale stats de Lv 1). Calibração ancorada em snapshots reais do PT-BR (versão 2026):

| Hero    | Asc       | HP      | ATK    | DEF    | Stat contrib. |
|---------|-----------|---------|--------|--------|---------------|
| Shemira | Lendário  | 642,254 | 41,552 |  9,239 | ~592          |
| Thoran  | Lendário  | 642,794 | 41,471 |  9,258 | ~592          |
| Baden   | Lendário  | 890,481 | 75,446 | 13,374 | ~915          |
| Thane   | Lendário  | 890,628 | 75,438 | 13,372 | ~913          |
| Ivan    | Elite     | 995,903 | 69,194 | 14,051 | ~927          |
| Ivan    | Elite+    | 997,282 | 69,292 | 14,076 | ~928          |

**Insights empíricos (não-óbvios):**
1. Stats reais são **virtualmente idênticos** entre heros de mesmo arquétipo (mage/warrior/tank) e mesma ascensão. Variações <0.1% (Shemira vs Thoran, Baden vs Thane).
2. Incremento Elite → Elite+ é só **+0.14%** em stats — milestone visualmente menor que ascended_X_stars.
3. Mage (Shemira) < Warrior (Baden) por ~55% em stat contribution.
4. Tank Elite (Ivan) ≈ Warrior Lendário (Baden) — HP maior compensa ATK menor.

**Multipliers (constants em PowerCalculator):**

```php
private const REAL_STAT_HP_FACTOR = 0.0005;     // HP de ~1M → ~500 power
private const REAL_STAT_ATK_FACTOR = 0.005;     // ATK de ~75k → ~375
private const REAL_STAT_DEF_FACTOR = 0.005;     // DEF de ~13k → ~65
private const REAL_STAT_CRIT_FACTOR = 0.3;
private const REAL_STAT_ACC_FACTOR = 0.2;
private const REAL_STAT_DODGE_FACTOR = 0.2;
private const REAL_STAT_HASTE_FACTOR = 0.2;
private const REAL_STAT_HP_THRESHOLD = 1000;    // > 1000 = stats reais
```

São 10x menores que os do `statContribution(HeroStat)` porque operam em valores de jogo (~10^5 HP) em vez de stats do catálogo (~10^3 HP).

**Comportamento:**
- `hasRealStats($uh)`: HP > 1000.
- `forUserHero`: usa `realStatContribution($uh)` quando true, senão `statContribution($hero->stats)`.
- Backward-compat: nenhum hero existente sem stats preenchidos é afetado — continua usando o baseline do catálogo.

**6 testes novos no PowerCalculatorTest:**
- `has_real_stats_detecta_hp_acima_do_limiar`
- `real_stat_contribution_calibrado_para_snapshots` — Shemira/Baden/Ivan com tolerância 0.5
- `for_user_hero_prefere_stats_reais_quando_preenchidos` — ignora HeroStat com 99k quando UserHero tem 642k
- `for_user_hero_cai_para_heroStat_quando_sem_real_stats` — fallback funcional
- `baden_vs_thane_stats_iguais_geram_power_igual` — diff < 5 pontos
- `ivan_elite_para_elite_plus_diff_minima` — diff ~101 (asc +1 + 1 ponto de stat)

**480 testes verdes** (+6 desta calibração).

---

### ✅ Fase 58 — Equipamentos Míticos (catálogo + integração + UI)

Sistema completo de equipamentos NOMEADOS do AFK Arena (Gaius/Ancelot/Místico), distintos do tier genérico T1-T6 que já existia. Entregue em 3 sub-fases.

#### 58a — Catálogo (`7774a63`)

**Schema novo (`equipments` table):**
- slug único, set_code (gaius/ancelot/mystic), slot (boots/chest/helmet/weapon), material (plate/leather/padded/heavy_weapon/light_weapon/magic_staff), hero_type (strength/agility/intelligence), faction_id, quality_tier (mythic/mythic_t1/...t4)
- `power_rating`, `stats` (JSON), `set_bonuses` (JSON nullable pra T3+)
- `max_upgrade_level` (5 pra Mítico-T2, 0 pra T3-T4), `upgrade_percent_per_level` (10%)
- Convenção: stats com sufixo `_pct` são percentuais; sem sufixo é flat.

**Model `Equipment`:**
- `powerAtLevel($n)`: aplica +10%/nível ao power_rating com clamp no max_upgrade_level
- `statsAtLevel($n)`: escala todos os stats no mesmo multiplier
- `applyFactionBonus($stats, $match)`: +30% nos valores quando hero pertence à mesma faction
- Override `$table = 'equipments'` pra evitar pluralização Laravel (incontável)

**Seeder com 18 items reais:**
- Gaius: Grevas (Lightbearer + Celestial), Couraça (Wilder), Elmo (Graveborn), Mangual (Wilder) = 5
- Ancelot: Perseverança (Graveborn + Celestial), Honra (Lightbearer), Refúgio (Graveborn), Lâmina (Hypogean) = 5
- Místico: Botas/Casaco/Chapéu (Celestial) + Bastão × 5 tiers (Hypogean) = 8

#### 58b — Equipar + PowerCalculator (`8accb3b`)

**UserHero:** 8 colunas novas (FK + level por slot) — `weapon_equipment_id/level`, `helmet_equipment_id/level`, etc. Foreign keys com `nullOnDelete`. Relations `weaponEquipment()/helmetEquipment()/chestEquipment()/bootsEquipment()` + helper `equippedMythicItems()` que itera os 4 slots.

**PowerCalculator:**
```php
public function mythicEquipmentContribution(UserHero $userHero, ?Hero $hero = null): float
```
- Agrega stats dos itens equipados com `statsAtLevel` aplicado
- Aplica `applyFactionBonus` quando `hero.faction_id == equipment.faction_id`
- Converte agregação em power via `convertAggregateStatsToPower` (privado), reusando `REAL_STAT_*_FACTOR` pra coerência com `realStatContribution`
- Stats `_pct` entram com peso modesto (0.3-0.5 por ponto)
- Integrado no `forUserHero` após equipamento padrão

**Comportamento:** coexiste com tier genérico T1-T6 — slots míticos somam ao power, não substituem.

#### 58c — UI de listagem + sidebar

**Página `/equipamentos`:** read-only com cards filtráveis. Filtros (faction/slot/hero_type/quality_tier/set_code) atualizam URL via `router.get` preservando state. Cards agrupados por set_code com:
- Quality badge (gradient diferenciado por tier — fuchsia pra T3, fuchsia+amber gradient pra T4)
- Hero type badge (strength=rose, agility=emerald, intelligence=sky)
- Stats list com labels PT-BR (`STAT_LABEL` map)
- Set bonuses em `<details>` colapsável (T3+ only)
- Footer com faction (com cardGradient theme) + info de upgrades

**Sidebar:** novo item "Equipamentos Míticos" (ícone `Hammer`) entre Cristal e Formações.

**Trade-offs documentados:**
- **Stats `_pct` vs flat**: convenção de sufixo evita schema separado, simplifica seeders e leitura.
- **Set bonuses só pra T3+**: dados crus do user mostram bonus só nesses tiers; modelo permite expandir sem migration.
- **Coexistência tier genérico + mítico**: bonus mítico é ADITIVO, não substituto. User pode equipar Mítico no slot e ainda usar `weapon_tier=T6` paralelo.
- **Admin CRUD adiado**: dados são estáveis (game items), CRUD pode entrar depois quando precisar de mais items.
- **Equip flow no roster modal**: ainda não implementado — backend está pronto, faltou só UI. Próxima fase.

**Tests:**
- `EquipmentSeederTest` (9 cenários): count, faction duplications, JSON shape, T3/T4 sem upgrades, scaling, faction bonus
- `EquipmentControllerTest` (8 cenários): guest redirect, lista completa, filtros isolados e combinados, payload shape
- `PowerCalculatorTest` (+7): sem itens, soma single, faction match/no-match, escala upgrade, soma 4 slots, integração forUserHero
- **504 testes verdes** (+24 desta fase: 9+8+7). TypeScript ✅.

#### 58d — Equip flow no roster modal

**Backend:**
- `UserHeroController` valida 8 campos novos (4 IDs + 4 níveis) com `Rule::exists('equipments', 'id')` e clamp do level (0-5).
- `mythicEquipmentSlotFields()` valida que o equipment.slot bate com o nome do campo (descarta silenciosamente se user tenta equipar arma no slot de boots) e clampa level pelo `max_upgrade_level` do equipment carregado.
- `HeroController::details` retorna `availableEquipments` filtrado por `hero.type` (quando hero tem type definido), com flag `faction_matches` pré-computada pra UI destacar o +30%.

**Frontend:**
- `roster-edit-modal.tsx`: nova seção colapsável `<details>` "Equipamentos Míticos" com counter "X/4 equipado(s)".
- Componente `MythicSlotEdit` por slot — Select com `name_pt (T#, faction)` + Input numérico com clamp dinâmico no `max_upgrade_level`.
- Badge "+30%" amber aparece quando faction do hero == faction do equipment (instant feedback).
- `<details>` colapsado por default, oculto totalmente quando `availableEquipments` está vazio (hero sem type ou sem items).

**Types:** `AvailableEquipment` + `EquipmentSlot` + `EquipmentQuality` em `types/afk.ts`. `UserHero` ganhou 8 fields novos.

**Tests:** `UserHeroMythicEquipmentTest` (8 cenários):
- Equipar single + clamping level
- T3 com max_upgrade_level=0 (rejeita level>0 via clamp silent)
- Validation rejeita ID inexistente
- Equipment slot errado é descartado silenciosamente
- 4 slots simultâneos
- Update remove equipment quando id=null
- Payload do `/heroes/{slug}/details` inclui availableEquipments filtrado por hero_type + flag faction_matches

**512 testes verdes** (+8 nesta sub-fase). TypeScript ✅.

#### 58e — Remoção do sistema legado de equipamento T1-T6

Sistema antigo de equipamento genérico (T1-T6 + level 1-20 em 4 slots) nunca refletiu mecânica real do jogo — era placeholder que coexistia com os míticos. Removido completamente agora que o sistema mítico está estável.

**Migration:** drop das 8 colunas (`weapon_tier`/`weapon_level`/`helmet_*`/`chest_*`/`boots_*`).

**Backend:**
- `UserHero`: removidas constants `EQUIPMENT_TIERS`/`EQUIPMENT_TIER_LABELS`/`EQUIPMENT_LEVEL_MAX`/`EQUIPMENT_SLOTS` e fields do `$fillable`/`$casts`.
- `PowerCalculator`: removidos `totalEquipmentContribution()`, `equipmentContribution()`, `EQUIPMENT_TIER_VALUE`, `EQUIPMENT_SLOTS`. Chamada no `forUserHero` apaga (só `mythicEquipmentContribution` resta).
- `UserHeroController`: removido `equipmentSlotFields()` e validation rules dos campos antigos.
- `HeroController::details` + `HeroController::index`: removidos campos do select; `index` agora pega só campos míticos no roster.
- `HeroComparisonController`: payload `equipment` agora retorna 4 slots com `{ name_pt, quality_tier, level, max_upgrade_level }` em vez de tier/level genérico. `equipment_contribution` vem do `mythicEquipmentContribution`.
- `Settings/BackupController`: export/import agora usa campos `*_equipment_id`/`*_equipment_level`.

**Frontend:**
- `types/afk.ts`: removido `EquipmentTier` type e 8 fields do `UserHero`.
- `lib/afk.ts`: removidos `EQUIPMENT_TIERS`/`EQUIPMENT_TIER_LABEL`/`EQUIPMENT_TIER_BADGE`/`EQUIPMENT_LEVEL_MAX`.
- `roster-edit-modal`: removidos os 4 `SlotEdit` (Arma/Capacete/Peitoral/Bota) com `EquipmentInputs`. Seção "Equipamentos Míticos" agora abre por default (`<details open>`). Layout 3-col simplificado: esquerda só Assinatura, direita só Artefato/Coleção.
- `hero-detail-modal`: removidos os 4 `EquipmentSlot` da grid superior. Component `EquipmentSlot` deletado.
- `pages/heroes/index.tsx`: removido filtro `equipTierMin` e referências em `hasActiveAdvancedFilters`.
- `heroes-advanced-filters.tsx`: removido `equipTierMin` do state, `EQUIP_TIERS`, `EQUIP_TIER_RANK`, `tierCodeMeetsMin` e UI do filtro.
- `pages/roster/compare.tsx`: card de equipment agora mostra equipamento mítico por slot (`name_pt` + level).

**Tests:**
- `PowerCalculatorTest`: removidos 4 tests do sistema antigo (`equipment_contribution_*`, `total_equipment_soma_quatro_slots`, `for_user_hero_inclui_equipamento`).
- `UserHeroArtifactTest`: removidos 5 tests do sistema antigo (`user_pode_equipar_4_pecas`, `validacao_rejeita_tier_invalido`, `details_retorna_equipamento`, `validacao_rejeita_level_acima_de_20`, `tier_sem_level_e_normalizado`).
- `HeroIndexTest::test_roster_payload_inclui_campos_de_equipamento`: removido check dos tier/level antigos (mantida estrutura básica).
- `HeroComparisonTest`: atualizado pra novo shape de `equipment` (null pra slots vazios).
- **502 testes verdes** (-10 removidos). TypeScript ✅.

#### 58f — Layout 3-col com 4 tiers em todos os equipamentos

Duas correções pós-58e:
1. **Layout restaurado** — slots Arma/Capacete/Peitoral/Bota voltaram para as laterais do modal (eram convenientes). Mas agora usam `MythicSlotEdit` em vez do antigo `EquipmentInputs` removido. Camada `<details>` redundante de Equipamentos Míticos foi removida.
2. **Todos os equipamentos têm 5 tiers** — antes só o Bastão tinha Mítico/T1/T2/T3/T4. Agora os 14 itens base geram cada um 5 versões via expansão programática.

**Escalas de stats** (extraídas do Bastão de Magia, única peça com dados completos no jogo):

| Stat type    | Mítico | T1     | T2     | T3      | T4      |
|--------------|--------|--------|--------|---------|---------|
| Flat (HP/ATK/DEF/etc) | 1.00x  | 2.33x  | 5.00x  | 9.00x   | 24.67x  |
| crit_rate_pct | 1.00x  | 1.00x  | 1.00x  | 1.80x   | 1.80x   |
| resistance_pct (RF/RM) | 1.00x | 1.08x  | 1.17x  | 2.10x   | 2.10x   |
| power_rating | 1.00x  | 1.97x  | 3.88x  | 393x    | 535x    |

T3 e T4 ganham `set_bonuses` adicionais. Para arma usa os valores do Bastão T3/T4 verbatim; para slots não-arma (boots/chest/helmet) aplicamos 50% — premissa: arma é a peça mais forte do set.

**Constants no seeder:**

```php
private const FLAT_MULTIPLIERS = [
    'mythic' => 1.0, 'mythic_t1' => 2.33, 'mythic_t2' => 5.0,
    'mythic_t3' => 9.0, 'mythic_t4' => 24.67,
];
private const CRIT_MULTIPLIERS = [...];
private const RESISTANCE_MULTIPLIERS = [...];
private const POWER_MULTIPLIERS = [...];
private const NON_WEAPON_BONUS_WEIGHT = 0.5;
```

`scaleStats()` privado aplica o multiplier apropriado por chave. `buildSetBonuses()` aplica `NON_WEAPON_BONUS_WEIGHT` quando slot ≠ weapon.

**Resultado:**
- 14 itens base × 5 tiers = **70 equipamentos seeded**
- 14 por tier (Mítico/T1/T2/T3/T4)
- T3/T4 sem upgrades (`max_upgrade_level = 0`), mas com set_bonuses
- T0/T1/T2 com upgrade scaling 10%/nível, max 5 levels

**Frontend:**
- Modal: layout 3-col com slots em ambas as laterais funcional novamente
- `MythicSlotEdit` adaptado pro visual compacto (`bg-card`, padding menor)
- Badge "+30%" aparece quando faction match

**Tests:**
- `EquipmentSeederTest`: 12 cenários (9 atualizados + 3 novos: `todos_os_equipamentos_tem_5_tiers`, `t3_e_t4_dos_outros_slots_tem_set_bonuses_menores`, `stats_escalam_consistentemente_entre_tiers`)
- `EquipmentControllerTest`: counts atualizados (18→70 em filtros)
- **505 testes verdes** (+3 nesta sub-fase)

#### 58g — Equipamentos por facção + filtro no roster

Três correções pós-58f:

1. **Equipamentos pra todas as 8 facções** — antes cada item base tinha 1-2 facções específicas (ex: Grevas só em Lightbearer + Celestial). Agora cada um dos 12 templates é gerado pra todas as 8 facções, totalizando **480 items** (12 × 8 × 5 tiers).

2. **Filtro AND por faction_id + hero_type** — `HeroController::details` agora filtra `availableEquipments` por DOIS critérios: a `faction_id` do hero E o `hero_type` apropriado. Hero Lightbearer-strength só vê Gaius-Lightbearer (não Gaius-Mauler ou Místico-Lightbearer).

3. **Visual melhorado do MythicSlotEdit** — quando não há items compatíveis o slot mostra "Sem items compatíveis" (em vez de só "— vazio —"). Trigger compactado mostra o tier (M/T1/T2/T3/T4) em badge + nome truncado. Items no dropdown também usam badge de tier compacto.

**Mudanças no seeder:**

Refatorado pra abordagem template × faction. `itemTemplates()` retorna 12 templates (sem faction); `run()` itera por todas as 8 factions em `ALL_FACTIONS` e gera 1 versão por faction × 5 tiers de cada template. Slug pattern: `{template_slug_prefix}-{faction_code}{-tier?}`.

```php
private const ALL_FACTIONS = [
    'lightbearer', 'mauler', 'wilder', 'graveborn',
    'celestial', 'hypogean', 'dimensional', 'draconis',
];
```

**Distribuição final:**
- 12 templates × 8 factions × 5 tiers = 480 equipamentos
- 60 items por facção (12 × 5 tiers)
- 96 items por tier (12 × 8)
- 160 items por hero_type (4 templates × 8 × 5)

**Tests atualizados:**
- `EquipmentSeederTest`: count 70→480, novo test `cada_template_existe_em_todas_8_factions`
- `EquipmentControllerTest`: counts em todos os filtros (faction 6→60, weapon 7→120, intelligence 8→160, T3 14→96)
- `UserHeroMythicEquipmentTest`: novo test `hero_de_faction_diferente_nao_ve_items_de_outras_factions` confirma o filtro AND
- **507 testes verdes** (+2 nesta sub-fase)


---

### ✅ Fase 59 — Importação de roster via OCR (`/roster/import`)

Upload de screenshots do jogo → OCR no navegador (tesseract.js, idioma `eng`) → fuzzy match dos nomes contra o catálogo (Fuse.js, threshold 0.4) → revisão manual → gravação.

- **Rotas:** `GET /roster/import` (roster.import) e `POST /roster/import` (roster.import.store).
- **Frontend** (`pages/roster/import.tsx`): um único worker processa as imagens em sequência; linhas extraídas via `recognize(file, {}, { blocks: true })` (desde o tesseract.js v6 a hierarquia blocks → paragraphs → lines só vem quando pedida). Linhas com nível mas sem nome reconhecível viram "Herói não identificado", resolvidas com um picker. O mesmo herói detectado em várias imagens fica com o maior nível.
- **Backend** (`RosterImportController::store`): valida `heroes` (1–300 itens), `hero_id` distinto e ativo, `level` 1–240; limita o nível ao `max_level` do tier; grava em transação na **conta de jogo ativa**. Heróis já no roster só têm o nível atualizado — ascensão, SI, móveis etc. ficam intactos.
- **Testes:** `RosterImportTest` (7 cenários — guest, só ativos no index, criação, update preservando campos, clamp por tier, validações, conta ativa).

### ✅ Fase 60 — Múltiplas contas de jogo (Etapas A–F + D.4)

Um usuário pode ter várias contas do jogo (main/alts/servidores). Todo dado de domínio passou a pertencer a uma `GameAccount`, não mais ao `User`.

- **A — Schema aditivo:** tabela `game_accounts` (user_id, name, server, avatar_hero_id, is_default) + coluna `game_account_id` nas 9 tabelas de domínio, com backfill.
- **B — Model** `GameAccount` + relations; `GameAccountObserver` garante sempre exatamente uma default por user.
- **C — `ActiveGameAccount`** (serviço): resolve a conta ativa via sessão (`active_game_account_id`) com fallback na default; middleware `EnsureGameAccount`; conta ativa compartilhada no Inertia. Troca via `POST /game-accounts/{id}/switch`.
- **D.1/D.2 — Controllers:** 19 controllers refatorados para escopar por conta ativa; `UserObserver` cria a "Conta Principal" no registro.
- **D.3 — Drop de `user_id`:** `game_account_id` vira NOT NULL; `User` passa a expor as relations via `hasManyThrough`.
- **D.4 — Limpeza (2026-10-16):** removida a trait de compatibilidade `BelongsToGameAccount` (os testes já usavam `game_account_id` direto) e o `user_id` "fantasma" do `$fillable` dos 8 models de domínio.
- **E — UI:** `account-switcher.tsx` no sidebar + CRUD em `/settings/accounts`.
- **F — Backup v2:** export/import de todas as contas (`schema_version: 2`), com retrocompatibilidade para arquivos v1.

### ✅ Fase 61 — Deploy na Hostinger + CI (2026-10-17)

Produção em **https://nexa.app.br** — Hostinger Cloud Economy (hospedagem compartilhada, LiteSpeed, PHP 8.4, MySQL local). Detalhes operacionais em `deploy/hostinger/README.md`.

- **Layout:** app em `public_html/afk-builder`; `public_html/.htaccess` reescreve tudo para `afk-builder/public/` (`.env` e código nunca são servidos) e libera `/.well-known/` para a validação do SSL.
- **DNS:** domínio no Registro.br (modo avançado), registro A para o servidor. SSL emitido pela Hostinger.
- **Deploy contínuo:** push no `main` → workflow `tests` → `.github/workflows/deploy.yml` monta o release (`vendor` sem dev + `public/build` + `public/release.txt` com o SHA) e envia pela API da Hostinger (secret `HOSTINGER_API_TOKEN`). No servidor, `deploy/hostinger/watcher.sh` (cron a cada minuto) troca os diretórios por rename preservando `.env` e `storage/`, roda `migrate`/`optimize`/`queue:restart` e guarda a versão anterior em `afk-builder.prev`. O workflow só fica verde quando `/release.txt` devolve o SHA novo. Não depende de SSH nem de o repositório ser público.
- **Scheduler e fila:** `deploy/hostinger/cron.sh schedule|queue`. O agendador da Hostinger não executa linhas `cd … && php artisan …` — no primeiro dia scheduler e fila ficaram parados por isso. A fila roda um worker por vez (`flock`) com `DB_QUEUE_RETRY_AFTER=1900`, acima do timeout de 1800s do `RunArtisanCommandJob` (com o padrão de 90s, syncs longos do `/admin/sync` seriam reprocessados e marcados como falhos).
- **Setup inicial:** `setup.sh` (migrate + seeds estáticos) e `catalog.sh` (sync da Fandom + seeders que dependem dos heróis), rodados por cron único — sem SSH pelo MCP.
- **Bug achado no deploy:** `BeastTiersSeeder` ainda usava as colunas `*_buff` removidas na Fase 51 — corrigido, com `CatalogSeedersTest` cobrindo os seeders de catálogo.
- **Removidos:** os alvos Oracle Cloud (`deploy/oracle/`) e Vercel (`vercel.json`, `api/index.php`), que não chegaram a ser usados.

### ✅ Fase 62 — Perfil de capacidades dos heróis (2026-10-18)

O otimizador enxergava os heróis por rótulos (facção, classe, tier rating, sinergias cadastradas) e ignorava o que cada um FAZ: as 906 habilidades importadas e o `role_label` da wiki não entravam em nada.

- **`HeroCapabilityClassifier`:** lê as descrições das habilidades (EN, Fandom) frase a frase e o papel da wiki, e gera o perfil do herói — 18 capacidades em 5 grupos: dano (área, alvo único, contínuo), sustentação (cura do time, escudo, redução de dano, autossustentação, sobrevive/revive), controle (forte, leve, imunidade), suporte (dissipar, energia para aliados, roubo de energia, buff, debuff) e posição (linha de frente, alcance da linha de trás). Força 1 ou 2 por capacidade. Calibrado contra as 906 habilidades; o contexto da frase decide o sentido ("heals all allies" é cura do time, "heals himself" é autossustentação; "immune to control" não é controle sobre inimigos).
- **Dados:** `heroes.capabilities` (classificador, regenerado) + `heroes.capabilities_manual` (correções do admin, `{add, remove}`, nunca tocadas pela reclassificação); `hero_abilities.effects` recebe as capacidades de cada habilidade.
- **`heroes:classify-capabilities`:** agendado diariamente às 04:10 (depois do `heroes:sync`), disponível no `/admin/sync` e no `catalog.sh`.
- **Admin `/admin/heroes/capabilities`:** revisão por herói (força 0/1/2 por capacidade); o servidor grava só a diferença em relação ao classificador.
- **UI:** perfil na ficha do herói (bloco "Capacidades" + selos em cada habilidade) e painel "Capacidades do time" no breakdown das formações — o que o time cobre, o que falta no contexto (PvE, PvP, Chefe), "sem sustentação" e "sem efeito contra chefe" (controle).

**Por que é informativo e não entra no score:** antes de ligar no otimizador, cinco critérios de pontuação por capacidades (cobertura com essenciais, cobertura pura, intensidade, riqueza do kit, nº de capacidades) foram validados contra os comps recomendados da comunidade, comparando com 3.000 times aleatórios. Nenhum separou os comps fortes do acaso — percentil médio entre 20 e 41 (acaso = 50). Comps fortes vencem por mecânicas específicas (reflexo de dano, burst que dispensa tanque, empilhar charm) e pela força dos heróis, que o texto das habilidades não revela.

**Pesos aprendidos dos tier ratings (2026-10-18), também negativo:** com os 3.202 ratings afk.guide de fim de jogo, a capacidade que mais acompanha rating alto é energia para aliados (+1,0 a +1,2 tier), seguida de dissipar, cura e buff — e dano em alvo único, linha de frente e alcance da linha de trás aparecem em heróis com rating MENOR. Usando essas diferenças como pesos, os comps recomendados ficaram no percentil 25,5; o **tier rating médio sozinho (já usado pelo otimizador) fica no 76,5**, e somar capacidades a ele derruba para 54,4. Conclusão: o perfil de capacidades segue informativo; o sinal forte de qualidade de time é o tier rating.

### ✅ Fase 63 — Campanha com vários grupos, Cristal de fim de jogo e Forja Dracônica (2026-10-18)

- **Campanha:** a cada 4 fases, a 4ª é uma batalha de vários grupos (até 5 equipes, heróis exclusivos). O tipo `campaign` passou de 1 para 1-5 equipes (`teams_must_be_unique`); a criação começa com 1 equipe e o jogador escolhe até 5 na fase de grupos.
- **Cristal Ressonante:** o app cortava em nível 501 e 60 posições — contas reais estão em 675/1040 com 112 posições. Agora: `level_cap` (o teto que o jogo mostra; vazio = estimar por 240 + 5 × Ascended do roster), `slots_occupied`, nível até 2000 e posições até 500. Um nível informado acima de 240 já conta como cristal evoluído, mesmo com o roster incompleto no app. A tabela pública de custos (Fandom) só vai até 501: acima disso a tela avisa que o custo é desconhecido em vez de estimar.
- **Provas da Forja Dracônica (Dragonforge Trials):** o tipo `dragon_trial` era um palpite da Fase 9 (1 equipe contra alvo dracônico). Corrigido pelo guia do afk.guide: 2 equipes nas fases 3-6 e 3 nas fases 7-12, heróis exclusivos; Dracônicos lutam no nível 600 sem supressão, os demais são suprimidos pela Fúria do Dragão (o Chifre de Dragão reduz); só Dracônicos, Dimensionais e heróis do Cristal; coleções, artefatos e pets não valem.
  - `DragonforgeTrialsSeeder`: as 81 formações do guia (fases 3-12, 1★ a 3★) como comps recomendados de várias equipes (`recommended_formations.teams`), com o investimento mínimo por fase. Rodado por migration de dados (o deploy só executa `migrate`).
  - Ratings do modo (`mode=dragonforge`): frequência de cada herói nas formações, ponderada pelas estrelas → S+/S/A/B; fora do guia, A para Dracônicos e C para os demais. São derivados das escolhas do guia — refletem a comunidade, não uma avaliação independente. O perfil do tipo prioriza tier rating (peso 1,6).
  - Comps de várias equipes: "duplicar" cria a formação com todas as equipes; o matcher compara o time do editor com a equipe mais parecida de cada comp; a página de comps ganhou filtro por modo.

### ✅ Fase 64 — Correções de dados, tradução e benchmark do otimizador (2026-10-18)

- **Tradução:** o `stichoza/google-translate-php` passou a receber HTTP 429 e o `content:translate` "concluía" sem traduzir nada (as falhas só iam para o log). Agora o `GoogleFreeTranslator` (HTTP/2 + POST, com novas tentativas) e o comando falham de forma visível. Na Hostinger nem isso basta: o Google recusa o PHP do servidor pela assinatura TLS. Daí o `content:translations export|translate|import`, que traduz por arquivo fora do servidor (ver `deploy/hostinger/README.md`).
- **Besta duplicada:** "Joyous Reamer" é um erro de digitação da página Beasts da wiki; a besta é Joyous Roamer (patch 1.158). O sync corrige o nome (`SyncBeastsCommand::NAME_FIXES`) e uma migration removeu a duplicata. Thistlekin segue sem tier: não há fonte pública.
- **Heróis fora do sync:** `FandomSyncService::INFOBOX_FIXES` completa a classe ou o tier que a wiki deixa em branco para 8 heróis (Angelo, Arden, Golus, Bronn, Draconis Archer/Warrior, Dreaf, Eletha), com base na descrição da própria página. Ankhira e Gourgue ficam de fora: não há pista da classe deles. Daemina não existe na wiki.
- **Desperto:** `Hero::isAwakenedSlug` exige slug = nome + título ("shemira-the-soulreaver"). A regra antiga (slug ≠ nome) marcava o Ukyo ("Ukyo Tachibana"/`ukyo`).
- **Capacidades:** novas regras ("fatal blow", "impervious to mind control", roubo de vida, "grants the allied target…") corrigiram Brutus e Mortas. Gavus segue sem perfil (sem habilidades na wiki).
- **`optimizer:benchmark`:** mede em que percentil os comps recomendados completos ficam contra times aleatórios, por componente. No catálogo de produção: **total 88,0** (acaso = 50), facção 78, tier rating 78,5, sinergias 70, classes 59, destaques 59. Variações de peso ficaram entre 86,8 e 89,8, ruído para 9 comps, então **os pesos foram mantidos**. Rodar antes de mudar o `TeamScorer`.

### ✅ Fase 65 — Equipes variadas na campanha com vários grupos (2026-10-18)

- **Sintoma:** ao gerar 5 equipes alvo na campanha, as três primeiras saíam inteiras de Portadores da Luz (ou de outra facção, conforme o catálogo), e o mesmo personagem aparecia duas vezes no time (Shemira + Shemira Desperta, Lucius + Lucius Desperto).
- **Sinergia sem teto:** cada par cadastrado valia ~1 ponto e a soma não tinha limite (até 6,3 num time), contra 0..1 do tier rating. Como os pares do seeder são quase todos da mesma facção, o otimizador fechava times monofacção. Agora `1 − e^(−soma)`, na faixa de 0 a 1: o 1º par vale mais e os seguintes cada vez menos.
- **Bônus de facção:** 5 da mesma facção 1,0 → 0,5; 3+2 0,6 → 0,3. Saíram o +0,1 por Hypogean e o +0,3 por 2+ Draconis, heurísticas da Fase 5.1 sem regra do jogo por trás (a força desses heróis já entra pelo tier rating).
- **Mesmo personagem:** `Hero::characterKey()` (slug do nome) identifica base e Desperto; o `SingleTeamOptimizer` não coloca as duas versões no mesmo time, e uma versão usada em outra equipe bloqueia a outra.
- **`optimizer:benchmark`:** total 88,0 → **88,2**; o pior comp recomendado subiu do percentil 43 para o 54. Nas 5 equipes de teste, duas ficaram sem bônus de facção e três com 3+2, sem repetição de personagem.

### ✅ Fase 66 — Modo alvo sem roster e atributos de tela fora do catálogo (2026-10-18)

- **Sintoma:** mesmo após a Fase 65, as 5 equipes alvo da campanha em produção vinham quase todas de Portadores da Luz do roster, e o Hybris (S+) ficava de fora.
- **Causa 1 — atributos de tela no catálogo:** 14 heróis tinham em `hero_stats` (atributos base de nível 1) os valores da tela do jogo de uma conta de fim de jogo: HP de 0,5 a 1,6 bilhão. Com os fatores de nível 1, cada um valia ~10^5 de poder (contra 0,72 de um herói sem atributos) e decidia sozinho. `PowerCalculator::statContribution` agora ignora atributos de catálogo com HP acima de 100 mil (`CATALOG_STAT_HP_MAX`).
- **Causa 2 — modo alvo usava o roster:** formações alvo planejam com qualquer herói do jogo no investimento-alvo, mas o poder dos heróis do roster entrava no score. `FormationController::loadUserHeroes` e a comparação de formações não usam mais o roster no modo alvo.
- **Pendente:** os atributos reais de fim de jogo (10^9 de HP) também estouram a escala de `realStatContribution` (calibrada para ~6×10^5) no modo real; precisa de recalibração antes de usar atributos do roster nessa faixa.

### ✅ Correções pós-auditoria (2026-10-16)

- **OCR quebrado:** o `post()` do `useForm` ignorava a opção `data` (o backend nunca recebia `heroes`) e `lines` vinha `undefined` no tesseract.js v7. Corrigido conforme a Fase 59, com testes.
- **Set bonuses dos Equipamentos Míticos (revertido em 2026-10-17):** chegaram a ser somados ao power, mas os valores do seeder são absolutos de fim de jogo (+1,39M HP, +582k ATK) e um set T3 completo passou a valer ~11k de power — quase 4 heróis Ascended no máximo (~2,65k), decidindo sozinho o otimizador. Ficam fora do cálculo até haver snapshot real de herói com T3+; `MythicEquipmentPowerScaleTest` protege a escala com os dados do seeder.
- **Contagem dobrada com stats reais:** com atributos reais cadastrados (lidos da tela do jogo, que já incluem o equipamento), o equipamento mítico deixou de ser somado de novo — em `PowerCalculator::forUserHero` e no breakdown da comparação de heróis.
- **Lint/TS:** `tsc --noEmit` e `eslint` voltaram a passar (erros em `roster/import.tsx`, import morto em `equipments/index.tsx`, `exhaustive-deps` intencionais documentados nos modais de beast/coleção).
- **530 testes verdes.**
