<?php

namespace App\Services;

use Composer\CaBundle\CaBundle;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Tradução pelo endpoint gratuito do Google (o mesmo do widget "gtx", sem
 * API key). Substitui o stichoza/google-translate-php, que passou a receber
 * HTTP 429 em toda requisição (2026-10-18).
 *
 * Por que HTTP/2 + POST: por HTTP/1.1 o Google responde 429 (trata como robô);
 * por HTTP/2 responde 200. POST leva o texto no corpo — lore de vários
 * parágrafos estoura o limite de tamanho da URL num GET.
 */
class GoogleFreeTranslator
{
    private const ENDPOINT = 'https://translate.googleapis.com/translate_a/single';

    private const RETRIES = 3;

    public function __construct(
        private readonly string $source = 'en',
        private readonly string $target = 'pt',
    ) {}

    /**
     * @throws RuntimeException quando o Google recusa depois das tentativas
     */
    public function translate(string $text): string
    {
        $response = Http::withOptions([
            'version' => 2.0,
            'verify' => CaBundle::getSystemCaRootBundlePath(),
        ])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->asForm()
            ->timeout(30)
            // 429/5xx: espera 2s, 4s, 6s antes de desistir.
            ->retry(self::RETRIES, fn (int $attempt) => $attempt * 2000, throw: false)
            ->post(self::ENDPOINT.'?'.http_build_query(['client' => 'gtx', 'sl' => $this->source, 'tl' => $this->target, 'dt' => 't']), ['q' => $text]);

        if (! $response->successful()) {
            throw new RuntimeException("Google Translate respondeu HTTP {$response->status()}");
        }

        // Formato: [[["trecho traduzido", "trecho original", ...], ...], ...]
        $segments = $response->json('0');
        if (! is_array($segments)) {
            throw new RuntimeException('Resposta inesperada do Google Translate');
        }

        return implode('', array_map(fn ($segment) => (string) ($segment[0] ?? ''), $segments));
    }
}
