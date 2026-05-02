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

        $name = $this->cleanValue($params['name'] ?? '');

        if ($name === '') {
            return null;
        }

        $factionRaw = strtolower($this->cleanValue($params['faction'] ?? ''));
        $classRaw = strtolower($this->cleanValue($params['class'] ?? ''));
        $rarityRaw = strtolower($this->cleanValue($params['rarity'] ?? ''));
        $genderRaw = strtolower($this->cleanValue($params['gender'] ?? ''));

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
            'role_label' => $this->cleanValue($params['role'] ?? '') ?: null,
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
}
