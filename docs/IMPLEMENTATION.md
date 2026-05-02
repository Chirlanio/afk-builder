# AFK Builder — Status de Implementação

Documento vivo do estado da aplicação. Atualizar conforme novas fases concluem.

> **Última atualização:** 2026-05-01 (Fase 4 concluída)

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

### ⏭️ Fase 5 — Algoritmo de otimização (pendente)

Escopo planejado:
- `TeamOptimizer` service: scoring de 1 equipe (bônus de facção + balanço de classes + sinergias curadas + poder bruto), pesos vindos de `challenge_types.scoring_profile`
- `MultiTeamPartitioner` service: distribui até 7 equipes sem sobreposição (heurística greedy + swaps locais; PLI se não convergir)
- Endpoint "Sugerir formação" integrado ao editor (botão "auto-preencher esta equipe" + "auto-distribuir todas as equipes")

### ⏭️ Fase 6 — Polimento (pendente)

- Histórico/versionamento de formações
- Comparação lado a lado de duas formações
- Share via link público read-only
- Refinamento visual com base em uso real
- Importação manual de heróis (UI, complementar ao sync)
- Tela admin pra aprovar `pending_review` heróis novos (hoje só via SQL/tinker)
- Refinar tradução: investigar 10 heróis ignorados na wiki (Bronn, Daimon, Angelo, etc.)

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
| 2 | baixa | ~10 heróis ignorados pelo sync por `tier=''` ou `class=''` na wiki (Bronn, Daimon, Angelo, Ankhira, Dreaf, Eletha, Golus, Gourgue, Draconis Archer/Warrior) — investigar caso a caso |
| 3 | informativa | Adrian & Elyse usa só a imagem do Adrian (escolha da wiki como "page representative image", não bug nosso) |
| 4 | baixa | `pending_review=true` para heróis novos não tem UI de aprovação ainda — workaround temporário: `UPDATE heroes SET is_active=1, pending_review=0 WHERE pending_review=1;` |
| 5 | melhoria | Mobile: drag-and-drop funciona via TouchSensor mas UX não foi otimizada para touch (pool fica oculto em telas < lg) |
