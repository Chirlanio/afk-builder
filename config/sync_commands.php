<?php

/**
 * Whitelist de comandos artisan disparáveis pela UI admin (/admin/sync).
 *
 * Cada entrada define o `signature` (nome real do comando), `label` PT-BR,
 * `description` e o array `options` com os argumentos opcionais expostos.
 *
 * Tipos suportados em `options[*].type`:
 *   - `bool`   → flag (--initial)
 *   - `int`    → --limit=N (validado como integer >=1)
 *   - `string` → --slug=X (validado por regex se informada)
 *   - `enum`   → --type=lore|abilities|... (validado contra `options`)
 *
 * Apenas comandos listados aqui podem ser disparados — qualquer outro
 * é rejeitado em SyncCommandController::store.
 */
return [
    'heroes:sync' => [
        'label' => 'Sincronizar catálogo de heróis',
        'description' => 'Detecta heróis novos da Fandom Wiki e atualiza existentes. Use "inicial" só na primeira importação ou após reset do BD (auto-aprova tudo).',
        'icon' => 'Users',
        'options' => [
            'initial' => ['type' => 'bool', 'label' => 'Importação inicial (auto-aprovar)'],
            'dry-run' => ['type' => 'bool', 'label' => 'Dry run (não persiste)'],
            'limit' => ['type' => 'int', 'label' => 'Limite de páginas', 'placeholder' => 'Ex: 50', 'min' => 1, 'max' => 500],
            'page' => ['type' => 'string', 'label' => 'Página específica', 'placeholder' => 'Ex: Shemira', 'pattern' => '/^[A-Za-z0-9 _\-:]{1,80}$/'],
        ],
    ],

    'heroes:resolve-images' => [
        'label' => 'Resolver imagens de heróis',
        'description' => 'Atualiza portrait_url para a CDN canônica do Fandom (static.wikia.nocookie.net) usando a API pageimages.',
        'icon' => 'Image',
        'options' => [
            'force' => ['type' => 'bool', 'label' => 'Forçar re-resolver (mesmo já em CDN)'],
            'dry-run' => ['type' => 'bool', 'label' => 'Dry run (não persiste)'],
            'size' => ['type' => 'int', 'label' => 'Tamanho em pixels (largura)', 'placeholder' => 'Padrão: 400', 'min' => 100, 'max' => 1200],
        ],
    ],

    'heroes:enrich' => [
        'label' => 'Enriquecer heróis (lore + abilities)',
        'description' => 'Popula campos lore (Background) e abilities a partir da wiki para heróis já importados. Sem flags, processa só os que estão faltando.',
        'icon' => 'BookOpen',
        'options' => [
            'force' => ['type' => 'bool', 'label' => 'Re-extrair (sobrescrever existente)'],
            'limit' => ['type' => 'int', 'label' => 'Limite de heróis', 'placeholder' => 'Ex: 20', 'min' => 1, 'max' => 500],
            'slug' => ['type' => 'string', 'label' => 'Slug do herói', 'placeholder' => 'Ex: lucius', 'pattern' => '/^[a-z0-9\-]{1,80}$/'],
        ],
    ],

    'heroes:sync-furniture' => [
        'label' => 'Sincronizar móveis (F3/F9)',
        'description' => 'Sincroniza habilidades F3/F9 (Hotel Furniture) per-hero da página master da Fandom.',
        'icon' => 'Sofa',
        'options' => [
            'dry-run' => ['type' => 'bool', 'label' => 'Dry run (não persiste)'],
        ],
    ],

    'heroes:sync-types' => [
        'label' => 'Sincronizar tipos (Força/Int/Agi)',
        'description' => 'Popula heroes.type (Strength/Intelligence/Agility) a partir do infobox da Fandom.',
        'icon' => 'Dumbbell',
        'options' => [
            'force' => ['type' => 'bool', 'label' => 'Re-extrair tipos (mesmo já preenchidos)'],
            'limit' => ['type' => 'int', 'label' => 'Limite de heróis', 'placeholder' => 'Ex: 50', 'min' => 1, 'max' => 500],
            'slug' => ['type' => 'string', 'label' => 'Slug do herói', 'placeholder' => 'Ex: athalia', 'pattern' => '/^[a-z0-9\-]{1,80}$/'],
        ],
    ],

    'artifacts:sync' => [
        'label' => 'Sincronizar artefatos',
        'description' => 'Sincroniza catálogo de artefatos (Dura\'s + Class) da página Artifacts da Fandom.',
        'icon' => 'Sparkles',
        'options' => [
            'dry-run' => ['type' => 'bool', 'label' => 'Dry run (não persiste)'],
        ],
    ],

    'beasts:sync' => [
        'label' => 'Sincronizar beasts (pets)',
        'description' => 'Sincroniza nomes de beasts (pets) da página master Beasts. Buffs S/I/A continuam manuais via /admin/beasts.',
        'icon' => 'PawPrint',
        'options' => [
            'dry-run' => ['type' => 'bool', 'label' => 'Dry run (não persiste)'],
        ],
    ],

    'heroes:sync-ratings' => [
        'label' => 'Sincronizar tier ratings (afk.guide)',
        'description' => 'Importa tiers (S+/S/A/B/C/D/F) por modo (Overall/PvP/PvE/Labyrinth/Twisted Realm) e estágio (early/mid/end) do afk.guide. Idempotente — sobrescreve ratings existentes da mesma fonte.',
        'icon' => 'Trophy',
        'options' => [
            'dry-run' => ['type' => 'bool', 'label' => 'Dry run (não persiste)'],
        ],
    ],

    'content:translate' => [
        'label' => 'Traduzir conteúdo (EN→PT-BR)',
        'description' => 'Traduz lore, abilities, furniture e guides via Google Translate (sem API key). Sem flags processa tudo o que ainda não tem tradução.',
        'icon' => 'Languages',
        'options' => [
            'type' => [
                'type' => 'enum',
                'label' => 'O que traduzir',
                'options' => ['all' => 'Tudo', 'lore' => 'Lore', 'abilities' => 'Abilities', 'furniture' => 'Furniture', 'guides' => 'Guides'],
                'default' => 'all',
            ],
            'limit' => ['type' => 'int', 'label' => 'Limite por tipo', 'placeholder' => 'Ex: 10', 'min' => 1, 'max' => 1000],
            'slug' => ['type' => 'string', 'label' => 'Slug do herói', 'placeholder' => 'Ex: lucius', 'pattern' => '/^[a-z0-9\-]{1,80}$/'],
            'force' => ['type' => 'bool', 'label' => 'Re-traduzir (mesmo se _pt já existe)'],
        ],
    ],
];
