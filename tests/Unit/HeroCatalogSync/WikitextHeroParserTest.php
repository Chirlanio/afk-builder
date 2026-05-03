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

    public function test_wikitext_to_markdown_converte_headers(): void
    {
        $wt = "Some intro.\n\n== Section A ==\n\nContent here.\n\n=== Sub Section ===\n\nMore content.\n\n==== Sub-sub ====\n\nDeep.";
        $md = $this->parser->wikitextToMarkdown($wt);

        $this->assertStringContainsString('## Section A', $md);
        $this->assertStringContainsString('### Sub Section', $md);
        $this->assertStringContainsString('#### Sub-sub', $md);
    }

    public function test_wikitext_to_markdown_converte_formatacao_inline(): void
    {
        $wt = "This is '''bold''' and this is ''italic'' and this is '''''both'''''.";
        $md = $this->parser->wikitextToMarkdown($wt);

        $this->assertStringContainsString('**bold**', $md);
        $this->assertStringContainsString('*italic*', $md);
        $this->assertStringContainsString('***both***', $md);
    }

    public function test_wikitext_to_markdown_converte_links(): void
    {
        $wt = 'Visit [[Ranhorn]] or [[The_Forbidden_Library|the library]].';
        $md = $this->parser->wikitextToMarkdown($wt);

        $this->assertSame('Visit Ranhorn or the library.', $md);
    }

    public function test_wikitext_to_markdown_remove_files_e_refs(): void
    {
        $wt = "Hero shown [[File:hero.png|thumb|caption]] in battle.<ref>citation</ref><ref name=\"x\">other</ref>";
        $md = $this->parser->wikitextToMarkdown($wt);

        $this->assertStringNotContainsString('File:', $md);
        $this->assertStringNotContainsString('<ref', $md);
        $this->assertStringNotContainsString('citation', $md);
        $this->assertStringContainsString('Hero shown', $md);
    }

    public function test_wikitext_to_markdown_converte_listas_aninhadas(): void
    {
        $wt = "Items:\n* primeiro item\n* segundo item\n** sub item\n** outro sub\n* terceiro";
        $md = $this->parser->wikitextToMarkdown($wt);

        $this->assertStringContainsString('- primeiro item', $md);
        $this->assertStringContainsString('  - sub item', $md);
        $this->assertStringContainsString('  - outro sub', $md);
        $this->assertStringContainsString('- terceiro', $md);
    }

    public function test_wikitext_to_markdown_converte_wikitable_em_tabela_gfm(): void
    {
        $wt = <<<'TEXT'
{| class="wikitable"
! Question !! Answer
|-
| What is X? || X is a feature.
|-
| How to use? || Click here.
|}
TEXT;
        $md = $this->parser->wikitextToMarkdown($wt);

        $this->assertStringContainsString('| Question | Answer |', $md);
        $this->assertStringContainsString('| --- | --- |', $md);
        $this->assertStringContainsString('| What is X? | X is a feature. |', $md);
        $this->assertStringContainsString('| How to use? | Click here. |', $md);
    }

    public function test_wikitext_to_markdown_remove_templates_e_galerias(): void
    {
        $wt = "Header.\n{{ClearSpace}}\n\n<gallery>\nimage.png|caption\n</gallery>\n\nMore content.";
        $md = $this->parser->wikitextToMarkdown($wt);

        $this->assertStringNotContainsString('{{', $md);
        $this->assertStringNotContainsString('<gallery', $md);
        $this->assertStringNotContainsString('image.png', $md);
        $this->assertStringContainsString('Header.', $md);
        $this->assertStringContainsString('More content.', $md);
    }

    public function test_parse_furniture_abilities_extrai_3_entradas(): void
    {
        $wikitext = <<<'TEXT'
== List of Furniture Abilities ==

=== Lightbearer Furniture Abilities ===
{| class="wikitable" border="1"
|-
! Hero
! Ability
! Description
|-
| [[File:Adrian Icon.jpg|100px]]<br>[[Adrian & Elyse]]
| [[File:icon.png|100px]]<br> Spirited Steps
| <div style='border:1px'>'''[3/9 Mythic Pieces]''' Adrian gains 50 Energy every second.</div><div style='border:1px'>'''[9/9 Mythic Pieces]''' Adrian gains 100 Energy every second.</div>
|-
| [[File:Belinda Icon.jpg|100px]]<br>[[Belinda]]
| [[File:fl.png|100px]]<br> Focused Light
| <div>'''[3/9 Mythic Pieces]''' Damage to single target +30%.</div><div>'''[9/9 Mythic Pieces]''' Crit Resistance reduced by 35.</div>
|-
| [[File:Lucius Icon.jpg|100px]]<br>[[Lucius]]
| [[File:hp.png|100px]]<br> Heaven's Protection
| <div>'''[3/9 Mythic Pieces]''' Shield strength +20%.</div>
|}
TEXT;

        $result = $this->parser->parseFurnitureAbilitiesIndex($wikitext);

        $this->assertCount(3, $result);

        $this->assertSame('Adrian & Elyse', $result[0]['hero_name']);
        $this->assertSame('Spirited Steps', $result[0]['ability_name']);
        $this->assertStringContainsString('50 Energy every second', $result[0]['f3_description']);
        $this->assertStringContainsString('100 Energy every second', $result[0]['f9_description']);
        $this->assertStringNotContainsString('[3/9 Mythic Pieces]', $result[0]['f3_description'], 'Prefixo deve ser removido');

        $this->assertSame('Belinda', $result[1]['hero_name']);
        $this->assertSame('Focused Light', $result[1]['ability_name']);

        $this->assertSame('Lucius', $result[2]['hero_name']);
        $this->assertNull($result[2]['f9_description'], 'F9 ausente quando há só 1 div');
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
