<?php

namespace Tests\Unit;

use App\Models\Hero;
use App\Services\GoogleFreeTranslator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

class HeroAwakenedAndTranslatorTest extends TestCase
{
    public function test_desperto_e_slug_com_o_titulo_da_versao_desperta(): void
    {
        $this->assertTrue(Hero::isAwakenedSlug('Shemira', 'shemira-the-soulreaver'));
        $this->assertTrue(Hero::isAwakenedSlug('Gwyneth', 'gwyneth-winged-vanquisher'));
        $this->assertFalse(Hero::isAwakenedSlug('Shemira', 'shemira'));
        // Falso positivo antigo: o NOME é mais longo que o slug.
        $this->assertFalse(Hero::isAwakenedSlug('Ukyo Tachibana', 'ukyo'));
        $this->assertFalse(Hero::isAwakenedSlug(null, 'ukyo'));
    }

    public function test_tradutor_junta_os_trechos_da_resposta(): void
    {
        Http::fake(['translate.googleapis.com/*' => Http::response([[['Lucius cura o aliado. ', 'Lucius heals the ally. '], ['Ele aumenta a defesa.', 'He raises defense.']]])]);

        $this->assertSame('Lucius cura o aliado. Ele aumenta a defesa.', (new GoogleFreeTranslator)->translate('Lucius heals the ally. He raises defense.'));

        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['q'] === 'Lucius heals the ally. He raises defense.');
    }

    public function test_tradutor_falha_de_forma_visivel_quando_o_google_recusa(): void
    {
        Sleep::fake(); // as novas tentativas esperam 2s/4s/6s
        Http::fake(['translate.googleapis.com/*' => Http::response('', 429)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 429');

        (new GoogleFreeTranslator)->translate('hello');
    }
}
