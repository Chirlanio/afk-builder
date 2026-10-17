<?php

namespace App\Services\HeroCatalogSync;

use Composer\CaBundle\CaBundle;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FandomWikiClient
{
    public const API_URL = 'https://afk-arena.fandom.com/api.php';

    public const HEROES_CATEGORY = 'Category:Heroes';

    private const USER_AGENT = 'AFK-Builder/0.1 (https://github.com/local; afk-builder@local) Laravel-HTTP';

    private const THROTTLE_MICROSECONDS = 250_000;

    private float $lastRequestAt = 0.0;

    /**
     * Returns ALL hero page titles from Category:Heroes, walking pagination automatically.
     *
     * @return array<int, string>
     */
    public function listHeroPageTitles(int $perPage = 500): array
    {
        $titles = [];
        $continue = null;

        do {
            $params = [
                'action' => 'query',
                'list' => 'categorymembers',
                'cmtitle' => self::HEROES_CATEGORY,
                'cmlimit' => $perPage,
                'cmtype' => 'page',
                'format' => 'json',
                'formatversion' => 2,
            ];

            if ($continue) {
                $params['cmcontinue'] = $continue;
            }

            $json = $this->get($params);

            foreach ($json['query']['categorymembers'] ?? [] as $member) {
                if (($member['ns'] ?? null) === 0 && ! empty($member['title'])) {
                    $titles[] = $member['title'];
                }
            }

            $continue = $json['continue']['cmcontinue'] ?? null;
        } while ($continue);

        return $titles;
    }

    /**
     * Returns the wikitext content of a page, or null when missing.
     */
    public function fetchWikitext(string $pageTitle): ?string
    {
        $json = $this->get([
            'action' => 'parse',
            'page' => $pageTitle,
            'prop' => 'wikitext',
            'format' => 'json',
            'formatversion' => 2,
        ]);

        return $json['parse']['wikitext'] ?? null;
    }

    /**
     * Resolves the canonical CDN thumbnail URL for one or more page titles
     * using the MediaWiki pageimages API. Returns a map of pageTitle => url.
     * Pages without a representative image are absent from the result.
     *
     * Up to 50 titles per call (MediaWiki API limit for non-bots).
     *
     * @param  array<int, string>  $pageTitles
     * @return array<string, string>
     */
    public function fetchPageThumbnails(array $pageTitles, int $thumbSize = 400): array
    {
        if (empty($pageTitles)) {
            return [];
        }

        $result = [];

        foreach (array_chunk($pageTitles, 50) as $chunk) {
            $json = $this->get([
                'action' => 'query',
                'titles' => implode('|', $chunk),
                'prop' => 'pageimages',
                'piprop' => 'thumbnail',
                'pithumbsize' => $thumbSize,
                'format' => 'json',
                'formatversion' => 2,
            ]);

            foreach ($json['query']['pages'] ?? [] as $page) {
                $title = $page['title'] ?? null;
                $url = $page['thumbnail']['source'] ?? null;

                if ($title && $url) {
                    $result[$title] = $url;
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     */
    private function get(array $params): array
    {
        $this->throttle();

        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->withOptions(['verify' => CaBundle::getSystemCaRootBundlePath()])
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 500)
            ->get(self::API_URL, $params);

        $this->lastRequestAt = microtime(true);

        if (! $response->ok()) {
            throw new RuntimeException("Fandom API failed ({$response->status()}) for params ".json_encode($params));
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('Fandom API returned non-JSON body');
        }

        if (isset($json['error'])) {
            $code = $json['error']['code'] ?? 'unknown';
            $info = $json['error']['info'] ?? '';
            throw new RuntimeException("Fandom API error {$code}: {$info}");
        }

        return $json;
    }

    private function throttle(): void
    {
        if ($this->lastRequestAt === 0.0) {
            return;
        }

        $elapsed = (microtime(true) - $this->lastRequestAt) * 1_000_000;

        if ($elapsed < self::THROTTLE_MICROSECONDS) {
            usleep((int) (self::THROTTLE_MICROSECONDS - $elapsed));
        }
    }
}
