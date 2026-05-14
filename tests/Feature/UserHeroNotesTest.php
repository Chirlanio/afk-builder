<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserHero;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserHeroNotesTest extends TestCase
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

    private function makeHero(string $slug = 'thoran'): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    private function makeUserHero(User $user, Hero $hero): UserHero
    {
        return UserHero::create([
            'user_id' => $user->id,
            'hero_id' => $hero->id,
            'level' => 200,
            'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star',
            'signature_item' => 0,
            'furniture' => 0,
            'engraving' => 0,
        ]);
    }

    public function test_update_persiste_notes_markdown(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $userHero = $this->makeUserHero($user, $hero);

        $notes = "# Plano\n\n- Subir SI até 30\n- F9 antes de E80";

        $this->actingAs($user)
            ->patch("/user/heroes/{$userHero->id}", [
                'level' => 240,
                'notes' => $notes,
            ])
            ->assertRedirect();

        $this->assertSame($notes, $userHero->fresh()->notes);
    }

    public function test_update_aceita_notes_null(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $userHero = $this->makeUserHero($user, $hero);
        $userHero->update(['notes' => 'algo antigo']);

        $this->actingAs($user)
            ->patch("/user/heroes/{$userHero->id}", [
                'level' => 240,
                'notes' => null,
            ])
            ->assertRedirect();

        $this->assertNull($userHero->fresh()->notes);
    }

    public function test_update_rejeita_notes_acima_de_1000_chars(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $userHero = $this->makeUserHero($user, $hero);

        $this->actingAs($user)
            ->patch("/user/heroes/{$userHero->id}", [
                'level' => 240,
                'notes' => str_repeat('a', 1001),
            ])
            ->assertSessionHasErrors(['notes']);
    }

    public function test_details_inclui_notes_atuais(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        $userHero = $this->makeUserHero($user, $hero);
        $userHero->update(['notes' => '**plano:** Asc 5⭐ até fim do mês']);

        $response = $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk();

        $this->assertSame('**plano:** Asc 5⭐ até fim do mês', $response->json('userHero.notes'));
    }

    public function test_index_inclui_notes_no_roster(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('athalia');
        $userHero = $this->makeUserHero($user, $hero);
        $userHero->update(['notes' => '# meu favorito']);

        $response = $this->actingAs($user)->get('/heroes')->assertOk();
        $roster = $response->viewData('page')['props']['roster'];

        $this->assertSame('# meu favorito', $roster[$hero->id]['notes']);
    }

    public function test_outro_user_nao_atualiza_notes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $hero = $this->makeHero();
        $userHero = $this->makeUserHero($owner, $hero);

        $this->actingAs($other)
            ->patch("/user/heroes/{$userHero->id}", [
                'level' => 240,
                'notes' => 'invasão',
            ])
            ->assertStatus(403);
    }
}
