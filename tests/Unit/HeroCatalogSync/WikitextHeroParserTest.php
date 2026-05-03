<?php

namespace Tests\Unit\HeroCatalogSync;

use App\Services\HeroCatalogSync\WikitextHeroParser;
use PHPUnit\Framework\TestCase;

class WikitextHeroParserTest extends TestCase
{
    private WikitextHeroParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new WikitextHeroParser;
    }

    public function test_retorna_null_quando_nao_ha_template_hero(): void
    {
        $result = $this->parser->parse("Apenas texto comum, sem template.\n\n== Section ==\nMore text.");

        $this->assertNull($result);
    }

    public function test_parseia_template_hero_padrao(): void
    {
        $wikitext = <<<'TEXT'
{{Hero
|image = Lucius.png
|name = Lucius
|title = Lightbringer
|faction = Lightbearer
|class = Tank
|rarity = Ascended
|gender = Male
|role = Tank
}}
'''Lucius''' is a tank hero...
TEXT;

        $parsed = $this->parser->parse($wikitext);

        $this->assertNotNull($parsed);
        $this->assertSame('Lucius', $parsed['name']);
        $this->assertSame('Lightbringer', $parsed['title']);
        $this->assertSame('lightbearer', $parsed['faction_code']);
        $this->assertSame('tank', $parsed['class_code']);
        $this->assertSame('ascended', $parsed['tier_code']);
        $this->assertSame('male', $parsed['gender']);
        $this->assertSame('Tank', $parsed['role_label']);
    }

    public function test_fallback_para_nickname_quando_name_ausente(): void
    {
        $wikitext = <<<'TEXT'
{{Hero
|image = Daimon.png
|nickname = Daimon
|title = The Forsaken Child
|faction = Graveborn
|class = Tank
|rarity = Ascended
|gender = Male
}}
TEXT;

        $parsed = $this->parser->parse($wikitext);

        $this->assertNotNull($parsed);
        $this->assertSame('Daimon', $parsed['name']);
        $this->assertSame('graveborn', $parsed['faction_code']);
        $this->assertSame('tank', $parsed['class_code']);
        $this->assertSame('ascended', $parsed['tier_code']);
    }

    public function test_fallback_para_role1_quando_role_ausente(): void
    {
        $wikitext = <<<'TEXT'
{{Hero
|name = Saveas
|faction = Mauler
|class = Ranger
|rarity = Legendary
|role1 = Continuous Damage
|role2 = Burst Damage
}}
TEXT;

        $parsed = $this->parser->parse($wikitext);

        $this->assertNotNull($parsed);
        $this->assertSame('Continuous Damage', $parsed['role_label']);
    }

    public function test_class_ausente_resulta_em_class_code_null(): void
    {
        // Caso real: Saveas, Angelo, etc. — wiki sem `class=`
        $wikitext = <<<'TEXT'
{{Hero
|name = Saveas
|faction = Mauler
|type = Agility
|role1 = Continuous Damage
|rarity = Legendary
}}
TEXT;

        $parsed = $this->parser->parse($wikitext);

        $this->assertNotNull($parsed);
        $this->assertSame('Saveas', $parsed['name']);
        $this->assertSame('mauler', $parsed['faction_code']);
        $this->assertNull($parsed['class_code']);
        $this->assertSame('legendary', $parsed['tier_code']);
    }

    public function test_rarity_ausente_resulta_em_tier_code_null(): void
    {
        // Caso real: Bronn — wiki sem `rarity=`
        $wikitext = <<<'TEXT'
{{Hero
|name = Bronn
|faction = Graveborn
|class = Warrior
|gender = Male
}}
TEXT;

        $parsed = $this->parser->parse($wikitext);

        $this->assertNotNull($parsed);
        $this->assertSame('Bronn', $parsed['name']);
        $this->assertSame('warrior', $parsed['class_code']);
        $this->assertNull($parsed['tier_code']);
    }

    public function test_aceita_variantes_plurais_de_faccao(): void
    {
        $wikitext = <<<'TEXT'
{{Hero
|name = Test
|faction = Lightbearers
|class = Tank
|rarity = Ascended
}}
TEXT;

        $parsed = $this->parser->parse($wikitext);

        $this->assertSame('lightbearer', $parsed['faction_code']);
    }

    public function test_mapeia_rarity_intermediario_para_tier_legendary(): void
    {
        foreach (['Elite', 'Mythic', 'Mythic+'] as $rarity) {
            $wikitext = <<<TEXT
{{Hero|name = X|faction = Mauler|class = Ranger|rarity = {$rarity}}}
TEXT;
            $parsed = $this->parser->parse($wikitext);
            $this->assertSame('legendary', $parsed['tier_code'], "rarity={$rarity} should map to legendary");
        }
    }

    public function test_extrai_imagem_do_campo_image_simples(): void
    {
        $wikitext = '{{Hero|name=X|faction=Mauler|class=Ranger|rarity=Legendary|image = Hero.png}}';
        $parsed = $this->parser->parse($wikitext);

        $this->assertSame('Hero.png', $parsed['image_filename']);
    }

    public function test_extrai_imagem_de_gallery(): void
    {
        $wikitext = <<<'TEXT'
{{Hero
|name = Test
|faction = Mauler
|class = Ranger
|rarity = Legendary
|image = <gallery>
Test.png|Default
Test_Skin.png|Skin
</gallery>
}}
TEXT;
        $parsed = $this->parser->parse($wikitext);

        $this->assertSame('Test.png', $parsed['image_filename']);
    }

    public function test_lida_com_template_aninhado_no_valor(): void
    {
        $wikitext = <<<'TEXT'
{{Hero
|name = X
|faction = Mauler
|class = Tank
|rarity = Legendary
|added = [[Update 1.10|v1.10]]
|relatives = * [[Brother]] -- {{ref|something}} * [[Sister]]
}}
TEXT;
        $parsed = $this->parser->parse($wikitext);

        $this->assertNotNull($parsed);
        $this->assertSame('X', $parsed['name']);
    }
}
