<?php

namespace App\Services\HeroCatalogSync;

/**
 * Parses an AFK Arena Fandom hero page wikitext, extracting the {{Hero|...}} infobox.
 *
 * Returns null when no Hero template is found (the page is not actually a hero).
 *
 * Note: this parser does NOT produce a slug. The orchestrator is responsible for
 * generating the slug from the page title — multiple wiki pages (e.g. a hero and
 * its Awakened form) often share the same internal `name=` value.
 */
class WikitextHeroParser
{
    private const FACTION_MAP = [
        'lightbearer' => 'lightbearer',
        'lightbearers' => 'lightbearer',
        'mauler' => 'mauler',
        'maulers' => 'mauler',
        'wilder' => 'wilder',
        'wilders' => 'wilder',
        'graveborn' => 'graveborn',
        'graveborns' => 'graveborn',
        'celestial' => 'celestial',
        'celestials' => 'celestial',
        'hypogean' => 'hypogean',
        'hypogeans' => 'hypogean',
        'dimensional' => 'dimensional',
        'dimensionals' => 'dimensional',
        'draconis' => 'draconis',
    ];

    private const CLASS_MAP = [
        'tank' => 'tank',
        'warrior' => 'warrior',
        'ranger' => 'ranger',
        'mage' => 'mage',
        'support' => 'support',
    ];

    private const TIER_MAP = [
        'common' => 'common',
        'rare' => 'common',
        'elite' => 'legendary',
        'elite+' => 'legendary',
        'legendary' => 'legendary',
        'legendary+' => 'legendary',
        'mythic' => 'legendary',
        'mythic+' => 'legendary',
        'ascended' => 'ascended',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function parse(string $wikitext): ?array
    {
        $template = $this->extractHeroTemplate($wikitext);

        if ($template === null) {
            return null;
        }

        $params = $this->parseTemplateParams($template);

        // Fandom às vezes usa `nickname=` no lugar de `name=` (ex: Daimon, Arden).
        $name = $this->cleanValue($params['name'] ?? '');
        if ($name === '') {
            $name = $this->cleanValue($params['nickname'] ?? '');
        }

        if ($name === '') {
            return null;
        }

        $factionRaw = strtolower($this->cleanValue($params['faction'] ?? ''));
        $classRaw = strtolower($this->cleanValue($params['class'] ?? ''));
        $rarityRaw = strtolower($this->cleanValue($params['rarity'] ?? ''));
        $genderRaw = strtolower($this->cleanValue($params['gender'] ?? ''));

        // role pode aparecer como `role=` ou `role1=` (varia entre páginas).
        $roleLabel = $this->cleanValue($params['role'] ?? '') ?: $this->cleanValue($params['role1'] ?? '') ?: null;

        return [
            'name' => $name,
            'title' => $this->cleanValue($params['title'] ?? '') ?: null,
            'faction_code' => self::FACTION_MAP[$factionRaw] ?? null,
            'class_code' => self::CLASS_MAP[$classRaw] ?? null,
            'tier_code' => self::TIER_MAP[$rarityRaw] ?? null,
            'gender' => match ($genderRaw) {
                'female', 'f' => 'female',
                'male', 'm' => 'male',
                '' => null,
                default => 'other',
            },
            'role_label' => $roleLabel,
            'image_filename' => $this->extractImageFilename($params['image'] ?? ''),
        ];
    }

    /**
     * Walks wikitext char-by-char tracking {{}} and [[]] depth, returning the
     * inner content of the first {{Hero ...}} template (without the enclosing braces).
     */
    private function extractHeroTemplate(string $wikitext): ?string
    {
        if (! preg_match('/\{\{\s*Hero\b/i', $wikitext, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $match[0][1] + 2;
        $length = strlen($wikitext);
        $braceDepth = 1;
        $linkDepth = 0;
        $i = $start;

        while ($i < $length && $braceDepth > 0) {
            $two = substr($wikitext, $i, 2);

            if ($two === '{{') {
                $braceDepth++;
                $i += 2;
                continue;
            }

            if ($two === '}}') {
                $braceDepth--;
                if ($braceDepth === 0) {
                    return substr($wikitext, $start, $i - $start);
                }
                $i += 2;
                continue;
            }

            if ($two === '[[') {
                $linkDepth++;
                $i += 2;
                continue;
            }

            if ($two === ']]' && $linkDepth > 0) {
                $linkDepth--;
                $i += 2;
                continue;
            }

            $i++;
        }

        return null;
    }

    /**
     * Splits the template body on top-level pipes and parses key=value pairs.
     * The first segment (template name) is dropped.
     *
     * @return array<string, string>
     */
    private function parseTemplateParams(string $body): array
    {
        $segments = [];
        $current = '';
        $braceDepth = 0;
        $linkDepth = 0;
        $length = strlen($body);
        $i = 0;

        while ($i < $length) {
            $two = substr($body, $i, 2);

            if ($two === '{{') {
                $braceDepth++;
                $current .= $two;
                $i += 2;
                continue;
            }

            if ($two === '}}' && $braceDepth > 0) {
                $braceDepth--;
                $current .= $two;
                $i += 2;
                continue;
            }

            if ($two === '[[') {
                $linkDepth++;
                $current .= $two;
                $i += 2;
                continue;
            }

            if ($two === ']]' && $linkDepth > 0) {
                $linkDepth--;
                $current .= $two;
                $i += 2;
                continue;
            }

            $char = $body[$i];

            if ($char === '|' && $braceDepth === 0 && $linkDepth === 0) {
                $segments[] = $current;
                $current = '';
                $i++;
                continue;
            }

            $current .= $char;
            $i++;
        }

        $segments[] = $current;

        array_shift($segments);

        $params = [];

        foreach ($segments as $segment) {
            $eq = strpos($segment, '=');
            if ($eq === false) {
                continue;
            }
            $key = strtolower(trim(substr($segment, 0, $eq)));
            $value = trim(substr($segment, $eq + 1));
            if ($key !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * Strips refs, comments, links and templates to a plain-text-ish value.
     */
    private function cleanValue(string $value): string
    {
        $value = preg_replace('/<ref\b[^>]*?\/>/i', '', $value) ?? $value;
        $value = preg_replace('/<ref\b.*?<\/ref>/is', '', $value) ?? $value;
        $value = preg_replace('/<!--.*?-->/s', '', $value) ?? $value;
        $value = preg_replace('/<gallery\b.*?<\/gallery>/is', '', $value) ?? $value;
        $value = preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]+)\]\]/', '$1', $value) ?? $value;
        $value = preg_replace('/\{\{[^}]*\}\}/', '', $value) ?? $value;
        $value = preg_replace('/<[^>]+>/', '', $value) ?? $value;

        return trim($value);
    }

    /**
     * The image field often contains a <gallery>...</gallery> with one filename per line
     * (Filename.png|Caption). We grab the first .png reference.
     */
    private function extractImageFilename(string $value): ?string
    {
        if (preg_match('/([A-Za-z0-9_\-\(\) ]+\.(?:png|jpg|jpeg|webp))/i', $value, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Extracts the Background section as plain text (links and refs stripped).
     * Returns null when no Background/History section exists.
     */
    public function parseLore(string $wikitext): ?string
    {
        // Aceita == Background ==, ==Background==, == History == etc.
        if (! preg_match('/==\s*(?:Background|History|Lore|Story)\s*==\s*\n(.*?)(?=\n\s*==[^=]|$)/is', $wikitext, $m)) {
            return null;
        }

        $body = $m[1];

        // Remove placeholders comuns "There is no available background..."
        if (preg_match('/^\s*\'\'There is no available background.*?\'\'/is', $body)) {
            return null;
        }

        $cleaned = $this->stripWikitext($body);
        $cleaned = trim($cleaned);

        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Extracts skills/abilities table. Returns array of unique skills (deduplicated by name —
     * keeps the first occurrence which has the full description; later upgrade rows are folded
     * into ascension_unlock notes).
     *
     * @return array<int, array{name: string, ascension_unlock: string|null, description: string|null, display_order: int, type: string}>
     */
    public function parseAbilities(string $wikitext): array
    {
        // Encontra a primeira tabela de Skills (== Skills == header pode aparecer 1+ vezes)
        if (! preg_match('/==\s*\'?\'?Skills\'?\'?\s*==(.*?)(?=\n\s*==[^=]|$)/is', $wikitext, $sectionMatch)) {
            return [];
        }

        $section = $sectionMatch[1];

        // Captura o conteúdo de {| ... |}
        if (! preg_match('/\{\|.*?\|\}/s', $section, $tableMatch)) {
            return [];
        }

        $table = $tableMatch[0];
        // Remove header line e cabeçalho
        $rows = preg_split('/\n\|-\s*\n/', $table);
        if ($rows === false || count($rows) < 2) {
            return [];
        }

        $abilities = [];
        $byName = [];
        $order = 0;

        foreach ($rows as $rowText) {
            // Skip header (contém "Unlock Level" ou começa com {|)
            if (preg_match('/Unlock\s*Level/i', $rowText) || preg_match('/^\{\|/', $rowText)) {
                continue;
            }

            // Cells split por linhas iniciadas com | ou !
            $cells = $this->extractRowCells($rowText);
            if (count($cells) < 4) {
                continue;
            }

            $unlockLevel = trim($cells[0]);
            // Cell 1 (nome) costuma estar em ! ' ' '...' ' ' ou ! Name
            $nameRaw = trim($cells[1]);
            $name = trim(preg_replace("/^'+|'+$/", '', $nameRaw) ?? $nameRaw);
            $name = $this->stripWikitext($name);
            // Skip cell 2 (icon) — não usado.
            $description = $this->stripWikitext($cells[3]);

            if ($name === '' || $description === '') {
                continue;
            }

            if (! isset($byName[$name])) {
                $byName[$name] = count($abilities);
                $abilities[] = [
                    'name' => $name,
                    'ascension_unlock' => $unlockLevel !== '' ? "Nv {$unlockLevel}" : null,
                    'description' => $description,
                    'display_order' => $order++,
                    'type' => 'active',
                ];
            }
            // Linhas subsequentes de upgrade são ignoradas (mantemos só a primeira).
        }

        return $abilities;
    }

    /**
     * Splits a wikitable row body into cells (each cell starts with | or ! at line start).
     *
     * @return array<int, string>
     */
    private function extractRowCells(string $rowText): array
    {
        // Quebra em linhas e agrupa por inicial | ou !
        $lines = preg_split('/\r?\n/', $rowText);
        if ($lines === false) {
            return [];
        }

        $cells = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^([|!])(.*)$/', $line, $m)) {
                if ($current !== null) {
                    $cells[] = $current;
                }
                $current = ltrim($m[2]);
            } elseif ($current !== null) {
                $current .= "\n" . $line;
            }
        }
        if ($current !== null) {
            $cells[] = $current;
        }

        return $cells;
    }

    /**
     * Convert wikitext to plain-ish text: links → label, strip refs/templates/HTML/files.
     */
    private function stripWikitext(string $value): string
    {
        $value = preg_replace('/<ref\b[^>]*?\/>/i', '', $value) ?? $value;
        $value = preg_replace('/<ref\b.*?<\/ref>/is', '', $value) ?? $value;
        $value = preg_replace('/<!--.*?-->/s', '', $value) ?? $value;
        $value = preg_replace('/<gallery\b.*?<\/gallery>/is', '', $value) ?? $value;
        // [[File:...]] e [[Image:...]] removidos
        $value = preg_replace('/\[\[(?:File|Image):[^\]]+\]\]/i', '', $value) ?? $value;
        // [[X|Y]] → Y;  [[X]] → X
        $value = preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]+)\]\]/', '$1', $value) ?? $value;
        // {{Template}} removidos
        $value = preg_replace('/\{\{[^{}]*\}\}/', '', $value) ?? $value;
        // tags HTML
        $value = preg_replace('/<[^>]+>/', '', $value) ?? $value;
        // '''bold''' e ''italic''
        $value = preg_replace("/'{2,5}/", '', $value) ?? $value;
        // colapsa espaços duplicados em linhas
        $value = preg_replace('/[ \t]+/', ' ', $value) ?? $value;
        // remove linhas vazias triplicadas
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

        return trim($value);
    }
}
