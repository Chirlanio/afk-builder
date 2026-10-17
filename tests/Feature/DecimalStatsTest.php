<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroStat;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserHero;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Atributos com casas decimais (vitalidade, armadura, competência e todos os
 * demais extras) — só HP, Ataque e Defesa são inteiros. O formulário converte
 * "1234,56" (vírgula) para 1234.56 antes de enviar; aqui garante o backend.
 */
class DecimalStatsTest extends TestCase
{
    use RefreshDatabase;

    private Hero $hero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
        $this->hero = Hero::create([
            'slug' => 'lucius',
            'name' => 'Lucius',
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_roster_salva_extras_com_decimais(): void
    {
        $user = User::factory()->create();
        $userHero = $user->defaultGameAccount->userHeroes()->create(['hero_id' => $this->hero->id, 'level' => 240]);

        $this->actingAs($user)
            ->patchJson("/user/heroes/{$userHero->id}/stats", [
                'hp' => 890481,
                'vitality' => 12345.67,
                'armor' => 9876.5,
                'proficiency' => 25432.1,
                'healing' => 150.25,
                'recovery_per_second' => 3021.75,
            ])
            ->assertOk();

        $fresh = UserHero::findOrFail($userHero->id);
        $this->assertSame(12345.67, (float) $fresh->vitality);
        $this->assertSame(9876.5, (float) $fresh->armor);
        $this->assertSame(25432.1, (float) $fresh->proficiency);
        $this->assertSame(150.25, (float) $fresh->healing);
        $this->assertSame(3021.75, (float) $fresh->recovery_per_second);
    }

    public function test_hp_ataque_e_defesa_continuam_inteiros(): void
    {
        $user = User::factory()->create();
        $userHero = $user->defaultGameAccount->userHeroes()->create(['hero_id' => $this->hero->id, 'level' => 240]);

        $this->actingAs($user)
            ->patchJson("/user/heroes/{$userHero->id}/stats", ['hp' => 1234.5, 'attack' => 10.1, 'defense' => 7.7])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hp', 'attack', 'defense']);
    }

    public function test_admin_salva_stats_base_com_decimais(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patchJson("/admin/heroes/{$this->hero->slug}/stats", ['vitality' => 512.75, 'armor' => 88.4, 'proficiency' => 1.5])
            ->assertOk();

        $stats = HeroStat::where('hero_id', $this->hero->id)->sole();
        $this->assertSame(512.75, (float) $stats->vitality);
        $this->assertSame(88.4, (float) $stats->armor);
        $this->assertSame(1.5, (float) $stats->proficiency);
    }
}
