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

    public function test_parse_lore_extrai_section_background(): void
    {
        $wikitext = <<<'TEXT'
{{Hero|name = X|faction = Mauler|class = Tank|rarity = Legendary}}
'''X''' is a tank...

== Background ==
Once upon a time, in [[Esperia|the land of Esperia]], there lived a hero named X. He was [[brave]] and strong.<ref>citation needed</ref>

He fought many battles.

== Skills ==
{|class="wikitable"
|}
TEXT;
        $lore = $this->parser->parseLore($wikitext);

        $this->assertNotNull($lore);
        $this->assertStringContainsString('Once upon a time', $lore);
        $this->assertStringContainsString('the land of Esperia', $lore);
        $this->assertStringContainsString('brave', $lore);
        $this->assertStringNotContainsString('citation needed', $lore);
        $this->assertStringNotContainsString('[[', $lore);
        $this->assertStringNotContainsString('<ref>', $lore);
    }

    public function test_parse_lore_retorna_null_quando_section_ausente(): void
    {
        $wikitext = '{{Hero|name = X|faction = Mauler|class = Tank|rarity = Legendary}}';
        $this->assertNull($this->parser->parseLore($wikitext));
    }

    public function test_parse_lore_retorna_null_para_placeholder_padrao(): void
    {
        $wikitext = <<<'TEXT'
== Background ==

''There is no available background for this hero.''
{{ClearSpace}}
== Skills ==
TEXT;
        $this->assertNull($this->parser->parseLore($wikitext));
    }

    public function test_parse_abilities_extrai_skills_da_tabela(): void
    {
        $wikitext = <<<'TEXT'
== Skills ==
{|class="wikitable" border="0"
|-
! scope="col"|'''Unlock Level (Hero)'''
! scope="col"|'''Name'''
! scope="col"|'''Icon'''
! scope="col"|'''Description'''
|-
|1
!'''Heaven's Protection'''
|[[File:s441.png|78px]]
|Lucius blesses all of his allies with a divine shield that reduces damage equal to 500% of his attack rating.
|-
|11
!'''Divine Strike'''
|[[File:s442.png|78px]]
|Lucius smashes the ground causing a shockwave, dealing 130% AoE damage.
|-
|21
!'''Divine Strike'''
|[[File:s442.png|78px]]
|Damage is increased to 150%.
|-
|41
!'''Divine Blessing'''
|[[File:s443.png|78px]]
|Lucius heals the ally with the lowest health for 200% of his Attack Rating.
|}
TEXT;
        $abilities = $this->parser->parseAbilities($wikitext);

        $this->assertCount(3, $abilities, '3 skills únicas (upgrades agrupados)');
        $this->assertSame("Heaven's Protection", $abilities[0]['name']);
        $this->assertStringContainsString('divine shield', $abilities[0]['description']);
        $this->assertStringNotContainsString('[[File:', $abilities[0]['description']);
        $this->assertSame('Nv 1', $abilities[0]['ascension_unlock']);

        $this->assertSame('Divine Strike', $abilities[1]['name']);
        $this->assertSame('Nv 11', $abilities[1]['ascension_unlock']);
        // Mantém a primeira descrição (não a do upgrade lvl 21)
        $this->assertStringContainsString('shockwave', $abilities[1]['description']);

        $this->assertSame('Divine Blessing', $abilities[2]['name']);
        $this->assertSame('Nv 41', $abilities[2]['ascension_unlock']);
    }

    public function test_parse_abilities_retorna_vazio_quando_secao_skills_ausente(): void
    {
        $wikitext = '{{Hero|name = X|faction = Mauler|class = Tank|rarity = Legendary}}';
        $this->assertSame([], $this->parser->parseAbilities($wikitext));
    }

    public function test_parse_abilities_aceita_formato_desperto_3_colunas(): void
    {
        // Despertos (Awakened) usam tabela sem coluna de Unlock Level.
        $wikitext = <<<'TEXT'
== Skills ==
{|class="wikitable" border="0" cellspacing="1" cellpadding="1" style="width:100%;"
|-
! scope="col" |'''Name'''
! scope="col" |'''Icon'''
! scope="col" |'''Description'''
|-
!'''Infernal Jealousy'''
|[[File:Infernal Jealousy.png|78px]]
|Passive: After every 2 normal attacks, the next attack is enhanced.
|-
!'''Echoing Shackles'''
|[[File:Echoing Shackles.png|78px]]
|Passive: When an enemy suffers a debuff, the Shadowed Core records it.
|-
!'''Loom of Longing'''
|[[File:Loom of Longing.png|78px]]
|Passive: At the start of battle, marks the highest base Attack enemy.
|}
TEXT;
        $abilities = $this->parser->parseAbilities($wikitext);

        $this->assertCount(3, $abilities);
        $this->assertSame('Infernal Jealousy', $abilities[0]['name']);
        $this->assertNull($abilities[0]['ascension_unlock'], 'Despertos não têm Unlock Level');
        $this->assertStringContainsString('After every 2 normal attacks', $abilities[0]['description']);
        $this->assertSame('Echoing Shackles', $abilities[1]['name']);
        $this->assertSame('Loom of Longing', $abilities[2]['name']);
    }
}
