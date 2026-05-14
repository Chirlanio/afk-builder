<?php

namespace Tests\Feature;

use App\Http\Controllers\Settings\BackupController;
use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\FormationTag;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\ScoringWeightPreset;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserHero;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            FactionSeeder::class,
            HeroClassSeeder::class,
            TierSeeder::class,
            ChallengeTypeSeeder::class,
            SignatureItemCostsSeeder::class,
        ]);
    }

    private function makeHero(string $slug): Hero
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

    public function test_guest_recebe_login_redirect(): void
    {
        $this->get('/settings/backup')->assertRedirect('/login');
        $this->get('/settings/backup/download')->assertRedirect('/login');
    }

    public function test_page_renderiza_com_summary(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        UserHero::create([
            'user_id' => $user->id,
            'hero_id' => $hero->id,
            'level' => 200,
            'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star',
            'signature_item' => 30,
            'furniture' => 0,
            'engraving' => 0,
        ]);

        $response = $this->actingAs($user)->get('/settings/backup')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(1, $props['summary']['roster_count']);
        $this->assertSame(BackupController::SCHEMA_VERSION, $props['schema_version']);
    }

    public function test_download_retorna_json_com_schema_correto(): void
    {
        $user = User::factory()->create(['locale' => 'pt']);

        $response = $this->actingAs($user)
            ->get('/settings/backup/download')
            ->assertOk();

        $this->assertSame('application/json; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $data = json_decode($response->streamedContent(), true);
        $this->assertSame(BackupController::SCHEMA_VERSION, $data['schema_version']);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertSame($user->id, $data['user']['id']);
        $this->assertSame('pt', $data['user']['locale']);
        $this->assertArrayHasKey('roster', $data);
        $this->assertArrayHasKey('formations', $data);
        $this->assertArrayHasKey('tier_lists', $data);
        $this->assertArrayHasKey('formation_tags', $data);
        $this->assertArrayHasKey('favorite_hero_slugs', $data);
        $this->assertArrayHasKey('scoring_weight_presets', $data);
    }

    public function test_download_inclui_roster_completo(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('thoran');
        UserHero::create([
            'user_id' => $user->id,
            'hero_id' => $hero->id,
            'level' => 240,
            'ascension_value' => 16,
            'ascension_code' => 'ascended_5_stars',
            'signature_item' => 40,
            'furniture' => 9,
            'engraving' => 80,
            'notes' => '# meu favorito',
        ]);

        $data = json_decode(
            $this->actingAs($user)->get('/settings/backup/download')->streamedContent(),
            true,
        );

        $this->assertCount(1, $data['roster']);
        $this->assertSame('thoran', $data['roster'][0]['hero_slug']);
        $this->assertSame(40, $data['roster'][0]['signature_item']);
        $this->assertSame(9, $data['roster'][0]['furniture']);
        $this->assertSame('# meu favorito', $data['roster'][0]['notes']);
    }

    public function test_download_inclui_formacao_com_teams_e_tags(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('athalia');
        $tag = FormationTag::create([
            'user_id' => $user->id,
            'name' => 'speedrun',
            'color' => 'emerald',
        ]);

        $challenge = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->formations()->create([
            'challenge_type_id' => $challenge->id,
            'name' => 'Counter Wilders',
            'mode' => 'target',
            'teams_count' => 1,
            'notes' => 'plano: tank front',
        ]);
        $team = $formation->teams()->create(['position' => 1]);
        $team->teamHeroes()->create(['hero_id' => $hero->id, 'slot' => 1]);
        $formation->tags()->attach($tag->id);

        $data = json_decode(
            $this->actingAs($user)->get('/settings/backup/download')->streamedContent(),
            true,
        );

        $this->assertCount(1, $data['formations']);
        $f = $data['formations'][0];
        $this->assertSame('Counter Wilders', $f['name']);
        $this->assertSame('campaign', $f['challenge_type_code']);
        $this->assertSame('plano: tank front', $f['notes']);
        $this->assertSame(['speedrun'], $f['tag_names']);
        $this->assertCount(1, $f['teams']);
        $this->assertSame('athalia', $f['teams'][0]['heroes'][0]['hero_slug']);
    }

    public function test_download_inclui_tier_list_com_rows_e_items(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');

        $tierList = $user->tierLists()->create(['name' => 'PvP Top']);
        $row = $tierList->rows()->create(['label' => 'S', 'color' => 'amber', 'position' => 1]);
        $row->items()->create(['hero_id' => $hero->id, 'position' => 1]);

        $data = json_decode(
            $this->actingAs($user)->get('/settings/backup/download')->streamedContent(),
            true,
        );

        $this->assertCount(1, $data['tier_lists']);
        $tl = $data['tier_lists'][0];
        $this->assertSame('PvP Top', $tl['name']);
        $this->assertSame('S', $tl['rows'][0]['label']);
        $this->assertSame('lucius', $tl['rows'][0]['items'][0]['hero_slug']);
    }

    public function test_download_inclui_favoritos_como_slugs(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('skreg');
        $user->favoriteHeroes()->attach($hero->id);

        $data = json_decode(
            $this->actingAs($user)->get('/settings/backup/download')->streamedContent(),
            true,
        );

        $this->assertSame(['skreg'], $data['favorite_hero_slugs']);
    }

    public function test_download_inclui_presets_de_peso(): void
    {
        $user = User::factory()->create();
        ScoringWeightPreset::create([
            'user_id' => $user->id,
            'name' => 'PvP agressivo',
            'challenge_type_id' => null,
            'weights' => ['power_weight' => 2.0],
        ]);

        $data = json_decode(
            $this->actingAs($user)->get('/settings/backup/download')->streamedContent(),
            true,
        );

        $this->assertCount(1, $data['scoring_weight_presets']);
        $this->assertSame('PvP agressivo', $data['scoring_weight_presets'][0]['name']);
        $this->assertSame(2.0, (float) $data['scoring_weight_presets'][0]['weights']['power_weight']);
    }

    public function test_dados_sao_isolados_por_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $hero = $this->makeHero('shared');
        UserHero::create([
            'user_id' => $userA->id,
            'hero_id' => $hero->id,
            'level' => 200,
            'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star',
            'signature_item' => 0,
            'furniture' => 0,
            'engraving' => 0,
        ]);

        $data = json_decode(
            $this->actingAs($userB)->get('/settings/backup/download')->streamedContent(),
            true,
        );

        $this->assertCount(0, $data['roster']);
    }

    public function test_filename_inclui_user_id_e_timestamp(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/backup/download');
        $disposition = $response->headers->get('Content-Disposition');

        $this->assertStringContainsString("afk-builder-backup-{$user->id}-", $disposition);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}-\d{6}\.json/', $disposition);
    }
}
