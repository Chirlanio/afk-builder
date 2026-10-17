<?php

namespace Tests\Unit\Optimization;

use App\Services\Optimization\HeroCapabilityClassifier as Cap;
use PHPUnit\Framework\TestCase;

/**
 * Frases reais das habilidades (Fandom Wiki), incluindo os casos que quebraram
 * regras durante a calibração.
 */
class HeroCapabilityClassifierTest extends TestCase
{
    private Cap $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new Cap;
    }

    public function test_cura_do_time_exige_aliados_na_frase(): void
    {
        $this->assertContains(Cap::HEALING, $this->classifier->classifyText(
            'Lucius heals the ally with the lowest health for 200% of his own Attack Rating and raises their Defense Rating for 5 seconds.'
        ));
        $this->assertNotContains(Cap::HEALING, $this->classifier->classifyText(
            '50% of all damage caused by this skill will be converted into health that heals Shemira.'
        ));
    }

    public function test_cura_propria_vira_autossustentacao(): void
    {
        $this->assertContains(Cap::SELF_SUSTAIN, $this->classifier->classifyText(
            "Shemira continuously leeches an enemy's life to restore her own which deals 45% damage every 0.5 seconds."
        ));
    }

    public function test_escudo_e_reducao_de_dano_para_aliados(): void
    {
        $tags = $this->classifier->classifyText(
            'Lucius blesses all of his allies with a divine shield that reduces damage equal to 500% of his own attack rating\'s value for 8 seconds.'
        );
        $this->assertContains(Cap::SHIELD, $tags);

        $this->assertContains(Cap::DAMAGE_REDUCTION, $this->classifier->classifyText(
            'While the blessing is active, the amount of damage dealt to the ally is reduced by 20%.'
        ));
    }

    public function test_derrubar_com_palavras_no_meio_e_controle_forte(): void
    {
        $this->assertContains(Cap::CC_HARD, $this->classifier->classifyText(
            'Lucius smashes the ground with his warhammer that causes a shockwave, knocking nearby enemies over, while also dealing 130% AoE damage to them.'
        ));
    }

    public function test_silenciar_sem_a_palavra_silence(): void
    {
        $this->assertContains(Cap::CC_SOFT, $this->classifier->classifyText(
            'Shemira deals 100% damage to a magic based enemy and simultaneously prevents them from casting any abilities for several seconds.'
        ));
    }

    public function test_provocar_inimigos_e_controle_leve(): void
    {
        $this->assertContains(Cap::CC_SOFT, $this->classifier->classifyText(
            'When the totem is placed, deal 120% of attack power in AoE damage, and mock nearby enemies for 2 seconds, forcing them to use normal attacks against it.'
        ));
    }

    public function test_imunidade_propria_nao_e_controle_sobre_inimigos(): void
    {
        $tags = $this->classifier->classifyText('Estrilda is immune to control abilities while charging.');

        $this->assertContains(Cap::CC_IMMUNITY, $tags);
        $this->assertNotContains(Cap::CC_HARD, $tags);
    }

    public function test_roubo_de_energia_com_apostrofo(): void
    {
        $tags = $this->classifier->classifyText(
            "Rowan's duck attacks the nearest enemy multiple times for 60% damage each, causing the enemy's accuracy to drop by 120 points for 10 seconds while also allowing Rowan to steal 80 of the enemy\u{2019}s energy points."
        );

        $this->assertContains(Cap::ENERGY_DRAIN, $tags);
        $this->assertContains(Cap::DEBUFF_ENEMY, $tags);
        $this->assertNotContains(Cap::ENERGY_GAIN, $tags);
    }

    public function test_energia_e_buff_para_aliados(): void
    {
        $tags = $this->classifier->classifyText(
            'If the coins are collected by any allies, they will recover 50 energy points and their attack rating will be increased by 30% for 8 seconds.'
        );

        $this->assertContains(Cap::ENERGY_GAIN, $tags);
        $this->assertContains(Cap::BUFF_ALLY, $tags);
    }

    public function test_inimigo_proximo_no_singular_nao_e_dano_em_area(): void
    {
        $this->assertNotContains(Cap::DAMAGE_AOE, $this->classifier->classifyText(
            'Nara hits a nearby enemy, leaving them stunned for several seconds and then proceeds to attack the enemy multiple times, inflicting 35% damage per attack.'
        ));
        $this->assertContains(Cap::DAMAGE_AOE, $this->classifier->classifyText(
            'Saurus deals 100% AoE damage 4 times to all enemies within his attack range.'
        ));
    }

    public function test_linha_de_tras_exige_ataque_na_frase(): void
    {
        $this->assertNotContains(Cap::BACKLINE_ACCESS, $this->classifier->classifyText(
            'Lorsan links the farthest and closest enemies together at the start of battle.'
        ));
        $this->assertContains(Cap::BACKLINE_ACCESS, $this->classifier->classifyText(
            'Nara leaps to the furthest enemy, dealing 200% damage to them.'
        ));
    }

    public function test_frases_do_brutus(): void
    {
        $this->assertContains(Cap::CC_IMMUNITY, $this->classifier->classifyText(
            'Brutus hacks at his enemies twice before spinning into a frenzy, dealing damage to them. Brutus is impervious to any mind control skills while spinning.'
        ));
        $this->assertContains(Cap::DEBUFF_ENEMY, $this->classifier->classifyText(
            'Brutus lets out a terrifying roar that causes enemies to become more vulnerable. Any physical damage dealt to enemies is increased by 25%.'
        ));
        $this->assertContains(Cap::REVIVE, $this->classifier->classifyText(
            'When Brutus is dealt a fatal blow he is able to retain 1 health point and is immune to all subsequent damage for 4 seconds.'
        ));
    }

    public function test_frases_do_mortas(): void
    {
        $this->assertContains(Cap::BUFF_ALLY, $this->classifier->classifyText(
            "Mortas grants the allied target with the highest Attack Rating 25% of the target's own Attack Rating and 25 Attack Speed points."
        ));
        $this->assertContains(Cap::SELF_SUSTAIN, $this->classifier->classifyText(
            "This is a passive skill that allows for Mortas' Normal Attacks to steal 4% of an enemy's current health and 10% of their current shield value."
        ));
    }

    public function test_texto_vazio_nao_gera_capacidades(): void
    {
        $this->assertSame([], $this->classifier->classifyText(null));
        $this->assertSame([], $this->classifier->classifyText('   '));
    }

    public function test_perfil_soma_habilidades_ate_forca_maxima_e_usa_papel(): void
    {
        $profile = $this->classifier->profile([
            'Numisu heals 2 allied heroes for 400% of Numisu\'s Attack Rating.',
            'The totem then remains active and heals nearby allies for 3% of their max health per second.',
            'Numisu heals allies again.',
        ], 'Tank', 'support');

        $this->assertSame(Cap::MAX_STRENGTH, $profile[Cap::HEALING]);
        $this->assertSame(1, $profile[Cap::FRONTLINE]); // vem do papel "Tank"
        $this->assertSame(array_values(array_intersect(Cap::ALL, array_keys($profile))), array_keys($profile)); // ordem estável
    }

    public function test_classe_tank_garante_linha_de_frente(): void
    {
        $this->assertArrayHasKey(Cap::FRONTLINE, $this->classifier->profile([], null, 'tank'));
        $this->assertSame([], $this->classifier->profile([], null, 'mage'));
    }
}
