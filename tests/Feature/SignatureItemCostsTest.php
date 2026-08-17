<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\SignatureItemCost;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignatureItemCostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            FactionSeeder::class,
            HeroClassSeeder::class,
            TierSeeder::class,
            SignatureItemCostsSeeder::class,
        ]);
    }

    private function makeHero(string $factionCode = 'lightbearer'): Hero
    {
        return Hero::create([
            'slug' => 'test-hero-'.uniqid(),
            'name' => 'Test Hero',
            'faction_id' => Faction::where('code', $factionCode)->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_summary_si_zero_lightbearer_retorna_cap_30(): void
    {
        $s = SignatureItemCost::summary(0, 'lightbearer');
        $this->assertSame(0, $s['current']);
        $this->assertSame(30, $s['cap']);
        $this->assertSame(1, $s['next_level']);
        $this->assertSame(10, $s['next_cost']);
        $this->assertSame('primordial', $s['next_emblem_type']);
        $this->assertFalse($s['is_capped']);
    }

    public function test_summary_si_zero_celestial_retorna_cap_40(): void
    {
        $s = SignatureItemCost::summary(0, 'celestial');
        $this->assertSame(40, $s['cap']);
        $this->assertFalse($s['is_capped']);
        $this->assertSame(40, $s['levels_remaining']);
    }

    public function test_summary_si_10_aponta_para_nivel_11(): void
    {
        $s = SignatureItemCost::summary(10, 'lightbearer');
        $this->assertSame(11, $s['next_level']);
        $this->assertSame(10, $s['next_cost']);
        $this->assertSame('primordial', $s['next_emblem_type']);
        $this->assertSame(20, $s['levels_remaining']);
    }

    public function test_summary_si_20_aponta_para_amplifying(): void
    {
        $s = SignatureItemCost::summary(20, 'lightbearer');
        $this->assertSame(21, $s['next_level']);
        $this->assertSame(10, $s['next_cost']);
        $this->assertSame('amplifying', $s['next_emblem_type']);
    }

    public function test_summary_si_30_lightbearer_is_capped(): void
    {
        $s = SignatureItemCost::summary(30, 'lightbearer');
        $this->assertTrue($s['is_capped']);
        $this->assertNull($s['next_level']);
        $this->assertNull($s['next_cost']);
        $this->assertSame(0, $s['levels_remaining']);
    }

    public function test_summary_si_30_celestial_aponta_para_faction(): void
    {
        $s = SignatureItemCost::summary(30, 'celestial');
        $this->assertFalse($s['is_capped']);
        $this->assertSame(31, $s['next_level']);
        $this->assertSame(50, $s['next_cost']);
        $this->assertSame('faction', $s['next_emblem_type']);
    }

    public function test_summary_si_40_celestial_is_capped(): void
    {
        $s = SignatureItemCost::summary(40, 'celestial');
        $this->assertTrue($s['is_capped']);
        $this->assertSame(40, $s['cap']);
    }

    public function test_summary_clampa_valor_acima_do_cap(): void
    {
        // SI 35 em lightbearer (cap 30) → trata como capped em 30
        $s = SignatureItemCost::summary(35, 'lightbearer');
        $this->assertSame(30, $s['current']);
        $this->assertTrue($s['is_capped']);
    }

    public function test_cumulative_soma_apenas_custos_documentados(): void
    {
        // Lightbearer SI 28: faltam 29, 30 → 40 + 50 = 90
        $s = SignatureItemCost::summary(28, 'lightbearer');
        $this->assertSame(90, $s['cumulative_to_cap']);
    }

    public function test_payload_details_inclui_signature_item(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lightbearer');

        $response = $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk();

        $payload = $response->json('signatureItem');
        $this->assertIsArray($payload);
        $this->assertSame(0, $payload['current']);
        $this->assertSame(30, $payload['cap']);
        $this->assertSame(1, $payload['next_level']);
        $this->assertSame(10, $payload['next_cost']);
    }

    public function test_payload_reflete_si_do_user_hero(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('celestial');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240,
            'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star',
            'signature_item' => 25,
        ]);

        $payload = $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk()
            ->json('signatureItem');

        $this->assertSame(25, $payload['current']);
        $this->assertSame(40, $payload['cap']);
        $this->assertSame(26, $payload['next_level']);
    }
}
