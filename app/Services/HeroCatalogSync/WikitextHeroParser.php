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

        $cleaned = $this->wikitextToMarkdown($body);
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
            // Skip rows que começam com {|
            if (preg_match('/^\{\|/', $rowText)) {
                continue;
            }

            // Cells split por linhas iniciadas com | ou !
            $cells = $this->extractRowCells($rowText);

            // Limpa atributo scope="col" que aparece antes do conteúdo de cells header.
            $cells = array_map(
                fn ($c) => preg_replace('/^scope=["\'][^"\']*["\']\s*\|?\s*/', '', $c) ?? $c,
                $cells,
            );

            $cellCount = count($cells);
            if ($cellCount < 3) {
                continue;
            }

            // Detecta linha de cabeçalho (Name|Icon|Description ou Unlock Level|Name|...)
            $cleanedFirst = trim($this->stripWikitext($cells[0]));
            if ($this->isHeaderRow($cells, $cleanedFirst)) {
                continue;
            }

            // Dois formatos:
            //   Padrão (4 colunas): Unlock Level | Name | Icon | Description
            //   Despertos (3 colunas): Name | Icon | Description
            if ($cellCount >= 4) {
                $unlockLevel = $cleanedFirst;
                $nameRaw = trim($cells[1]);
                $description = $this->wikitextToMarkdown($cells[3]);
            } else {
                $unlockLevel = '';
                $nameRaw = trim($cells[0]);
                $description = $this->wikitextToMarkdown($cells[2]);
            }

            $name = trim(preg_replace("/^'+|'+$/", '', $nameRaw) ?? $nameRaw);
            $name = $this->stripWikitext($name);

            if ($name === '' || $description === '') {
                continue;
            }

            if (! isset($byName[$name])) {
                $byName[$name] = count($abilities);
                $abilities[] = [
                    'name' => $name,
                    'ascension_unlock' => $unlockLevel !== '' && is_numeric($unlockLevel) ? "Nv {$unlockLevel}" : null,
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
     * Heurística para detectar linha de cabeçalho de tabela de skills.
     *
     * @param  string[]  $cells
     */
    private function isHeaderRow(array $cells, string $firstCellClean): bool
    {
        // Cabeçalho clássico: contém "Unlock Level"
        foreach ($cells as $c) {
            if (preg_match('/Unlock\s*Level/i', $c)) {
                return true;
            }
        }

        // Cabeçalho Despertos: cells exatas Name/Icon/Description
        $cleaned = array_map(fn ($c) => trim($this->stripWikitext($c)), $cells);
        $first3 = array_slice($cleaned, 0, 3);
        $signature = strtolower(implode('|', $first3));
        if ($signature === 'name|icon|description') {
            return true;
        }

        return false;
    }

    /**
     * Splits a wikitable row body into cells (each cell starts with | or ! at line start).
     * Ignora a linha de fechamento `|}`.
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
            // Linha de fechamento de tabela `|}` — ignora
            if (preg_match('/^\s*\|\}\s*$/', $line)) {
                continue;
            }

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
     * Converte wikitext em Markdown (CommonMark + GFM tables).
     *
     * Cobre o subset de wikitext usado nas páginas do AFK Arena Fandom:
     * - == Header == → ## Header (níveis 2-6)
     * - '''bold''' → **bold**
     * - ''italic'' → *italic*
     * - [[Page]] e [[Page|Label]] → Label (sem hiperlink)
     * - [[File:...]] → removido
     * - * item → - item; ** sub → "  - sub"
     * - {| wikitable |} → tabela GFM
     * - <ref>, <gallery>, comments, {{templates}}, HTML → removidos
     * - <br/> → quebra de linha
     */
    public function wikitextToMarkdown(string $wikitext): string
    {
        // Remove gallery/refs/comments primeiro (multiline)
        $text = preg_replace('/<gallery\b.*?<\/gallery>/is', '', $wikitext) ?? $wikitext;
        $text = preg_replace('/<ref\b[^>]*?\/>/i', '', $text) ?? $text;
        $text = preg_replace('/<ref\b.*?<\/ref>/is', '', $text) ?? $text;
        $text = preg_replace('/<!--.*?-->/s', '', $text) ?? $text;

        // Converte tabelas wikitable → markdown ANTES de outras transformações
        $text = preg_replace_callback(
            '/\{\|[^\n]*\n(.*?)\n\|\}/s',
            fn ($m) => "\n" . $this->wikitableToMarkdown($m[1]) . "\n",
            $text,
        ) ?? $text;

        // Headers (mais específico antes de menos específico)
        $text = preg_replace_callback(
            '/^(={2,6})\s*(.+?)\s*\1\s*$/m',
            function ($m) {
                $level = strlen($m[1]);
                return str_repeat('#', $level) . ' ' . trim($m[2]);
            },
            $text,
        ) ?? $text;

        // Templates {{X}} — remove (depois das tabelas)
        $text = preg_replace('/\{\{[^{}]*\}\}/', '', $text) ?? $text;

        // Imagens/Arquivos
        $text = preg_replace('/\[\[(?:File|Image):[^\]]+\]\]/i', '', $text) ?? $text;

        // Links: [[X|Y]] → Y; [[X]] → X
        $text = preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]+)\]\]/', '$1', $text) ?? $text;

        // Bold + italic
        $text = preg_replace("/'''''(.+?)'''''/s", '***$1***', $text) ?? $text;
        $text = preg_replace("/'''(.+?)'''/s", '**$1**', $text) ?? $text;
        $text = preg_replace("/''(.+?)''/s", '*$1*', $text) ?? $text;

        // <br/> → newline
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;

        // Listas: ** ou * no início da linha
        $text = preg_replace_callback(
            '/^(\*+)\s*(.+)$/m',
            fn ($m) => str_repeat('  ', strlen($m[1]) - 1) . '- ' . trim($m[2]),
            $text,
        ) ?? $text;

        // Tags HTML restantes
        $text = preg_replace('/<[^>]+>/', '', $text) ?? $text;

        // Espaços múltiplos NO MEIO de uma linha (não toca em indentação)
        $text = preg_replace('/(?<=\S)[ \t]{2,}(?=\S)/', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;

        // Colapsa 3+ newlines em 2
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Converte o corpo (sem `{|...` e sem `|}`) de uma wikitable em tabela GFM.
     */
    private function wikitableToMarkdown(string $body): string
    {
        // Quebra em linhas; pula a linha de atributos da tabela se existir
        $rows = preg_split('/\n\|-\s*\n?/', $body);
        if ($rows === false || count($rows) === 0) {
            return '';
        }

        $parsedRows = [];
        $maxCols = 0;

        foreach ($rows as $rowText) {
            $cells = $this->extractRowCells($rowText);
            // Limpa scope="..." e style="..."
            $cells = array_map(
                fn ($c) => preg_replace('/^(?:scope=["\'][^"\']*["\']|style=["\'][^"\']*["\'])\s*\|?\s*/', '', $c) ?? $c,
                $cells,
            );

            // Expande células com `||` ou `!!` (multi-célula em linha única)
            $expanded = [];
            foreach ($cells as $c) {
                $parts = preg_split('/\s*(?:\|\||!!)\s*/', $c);
                foreach ($parts as $p) {
                    // Recursivo: deixa converter wikitext interno mas SEM rodar wikitableToMarkdown
                    // (evita infinite loop). Strip-only.
                    $clean = $this->stripWikitextLight(trim($p));
                    // Escapa pipe no conteúdo
                    $clean = str_replace('|', '\\|', $clean);
                    $expanded[] = $clean;
                }
            }

            if (empty($expanded)) {
                continue;
            }

            $parsedRows[] = $expanded;
            $maxCols = max($maxCols, count($expanded));
        }

        if (empty($parsedRows) || $maxCols === 0) {
            return '';
        }

        // Pad e monta GFM
        $headers = $parsedRows[0];
        while (count($headers) < $maxCols) {
            $headers[] = '';
        }

        $md = '| ' . implode(' | ', array_slice($headers, 0, $maxCols)) . ' |' . "\n";
        $md .= '|' . str_repeat(' --- |', $maxCols) . "\n";

        for ($i = 1; $i < count($parsedRows); $i++) {
            $row = $parsedRows[$i];
            while (count($row) < $maxCols) {
                $row[] = '';
            }
            $md .= '| ' . implode(' | ', array_slice($row, 0, $maxCols)) . ' |' . "\n";
        }

        return $md;
    }

    /**
     * Variante simplificada de strip — usada DENTRO do conversor de tabelas
     * (não chama wikitextToMarkdown recursivamente).
     */
    private function stripWikitextLight(string $value): string
    {
        $value = preg_replace('/<ref\b[^>]*?\/>/i', '', $value) ?? $value;
        $value = preg_replace('/<ref\b.*?<\/ref>/is', '', $value) ?? $value;
        $value = preg_replace('/\[\[(?:File|Image):[^\]]+\]\]/i', '', $value) ?? $value;
        $value = preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]+)\]\]/', '$1', $value) ?? $value;
        $value = preg_replace('/\{\{[^{}]*\}\}/', '', $value) ?? $value;
        $value = preg_replace("/'''(.+?)'''/s", '**$1**', $value) ?? $value;
        $value = preg_replace("/''(.+?)''/s", '*$1*', $value) ?? $value;
        $value = preg_replace('/<[^>]+>/', '', $value) ?? $value;
        return trim($value);
    }

    /**
     * Convert wikitext to plain-ish text: links → label, strip refs/templates/HTML/files.
     */
    public function stripWikitext(string $value): string
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
