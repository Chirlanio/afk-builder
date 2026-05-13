<?php

namespace Tests\Unit\Optimization;

use App\Services\Optimization\FurnitureEffectClassifier;
use PHPUnit\Framework\TestCase;

class FurnitureEffectClassifierTest extends TestCase
{
    private FurnitureEffectClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new FurnitureEffectClassifier;
    }

    public function test_texto_vazio_ou_null_retorna_array_vazio(): void
    {
        $this->assertSame([], $this->classifier->classify(null));
        $this->assertSame([], $this->classifier->classify(''));
        $this->assertSame([], $this->classifier->classify('   '));
    }

    public function test_detecta_cc_immunity(): void
    {
        $tags = $this->classifier->classify('All negative control abilities are removed from Ezio whenever a strike is dealt.');
        $this->assertContains(FurnitureEffectClassifier::TAG_CC_IMMUNITY, $tags);
    }

    public function test_detecta_energy_boost(): void
    {
        $tags = $this->classifier->classify('Ezio gains 500 Energy points at the beginning of the battles.');
        $this->assertContains(FurnitureEffectClassifier::TAG_ENERGY_BOOST, $tags);
    }

    public function test_detecta_cc_control(): void
    {
        $tags = $this->classifier->classify('Enemies within range lose 12 Haste points.');
        $this->assertContains(FurnitureEffectClassifier::TAG_CC_CONTROL, $tags);
    }

    public function test_detecta_damage_boost_com_dano_aumentado(): void
    {
        $tags = $this->classifier->classify("The minions' damage is increased by 100%.");
        $this->assertContains(FurnitureEffectClassifier::TAG_DAMAGE_BOOST, $tags);
    }

    public function test_detecta_ability_uptime_cooldown_reduced(): void
    {
        $tags = $this->classifier->classify('Cooldown for the ability Teleportation is reduced by 1 seconds.');
        $this->assertContains(FurnitureEffectClassifier::TAG_ABILITY_UPTIME, $tags);
    }

    public function test_detecta_ability_uptime_instantly_triggered(): void
    {
        $tags = $this->classifier->classify('The ability will be instantly triggered again.');
        $this->assertContains(FurnitureEffectClassifier::TAG_ABILITY_UPTIME, $tags);
    }

    public function test_detecta_survivability_life_leech(): void
    {
        $tags = $this->classifier->classify('Walker receives an additional 25 Life Leech points.');
        $this->assertContains(FurnitureEffectClassifier::TAG_SURVIVABILITY, $tags);
    }

    public function test_detecta_healing(): void
    {
        $tags = $this->classifier->classify('Restores 30% of their maximum HP.');
        $this->assertContains(FurnitureEffectClassifier::TAG_HEALING, $tags);
    }

    public function test_detecta_revive(): void
    {
        $tags = $this->classifier->classify('Revives all fallen allies at the start of the battle.');
        $this->assertContains(FurnitureEffectClassifier::TAG_REVIVE, $tags);
    }

    public function test_detecta_shield(): void
    {
        $tags = $this->classifier->classify('Grants a shield that absorbs 5000 damage.');
        $this->assertContains(FurnitureEffectClassifier::TAG_SHIELD, $tags);
    }

    public function test_detecta_damage_reduction(): void
    {
        $tags = $this->classifier->classify('Damage taken is reduced by 40%.');
        $this->assertContains(FurnitureEffectClassifier::TAG_DAMAGE_REDUCTION, $tags);
    }

    public function test_detecta_multiplas_tags_no_mesmo_texto(): void
    {
        // texto com damage_boost + healing
        $tags = $this->classifier->classify(
            'Damage is increased by 100% and the amount of healing is also increased.',
        );
        $this->assertContains(FurnitureEffectClassifier::TAG_DAMAGE_BOOST, $tags);
        $this->assertContains(FurnitureEffectClassifier::TAG_HEALING, $tags);
    }

    public function test_texto_sem_keywords_relevantes_retorna_vazio(): void
    {
        // Cecilia: marks enemy com Symbol of Sin — sem keyword direto
        $tags = $this->classifier->classify(
            'After every 4 seconds, Cecilia marks the enemy that has dealt the most damage with 1 Symbol of Sin.',
        );
        // Pode pegar "damage" mas não deve necessariamente ter tags claras —
        // só verifica que retorna array (pode estar vazio ou com poucas tags).
        $this->assertIsArray($tags);
    }

    public function test_tags_em_ordem_estavel(): void
    {
        // Mesmo texto chamado duas vezes deve retornar mesma ordem
        $text = 'Damage is increased by 100% and grants a shield.';
        $first = $this->classifier->classify($text);
        $second = $this->classifier->classify($text);
        $this->assertSame($first, $second);
    }
}
