<?php

namespace Tests\Feature;

use App\Http\Controllers\Settings\BackupController;
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
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BackupImportTest extends TestCase
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

    private function uploadJson(array $data): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'backup_');
        file_put_contents($path, json_encode($data));

        return new UploadedFile($path, 'backup.json', 'application/json', null, true);
    }

    public function test_guest_recebe_redirect(): void
    {
        $this->post('/settings/backup/import')->assertRedirect('/login');
    }

    public function test_rejeita_arquivo_sem_schema_version(): void
    {
        $user = User::factory()->create();
        $file = $this->uploadJson(['roster' => []]);

        $this->actingAs($user)
            ->post('/settings/backup/import', [
                'file' => $file,
                'strategy' => 'merge',
            ])
            ->assertSessionHasErrors(['file']);
    }

    public function test_rejeita_schema_version_incompativel(): void
    {
        $user = User::factory()->create();
        $file = $this->uploadJson([
            'schema_version' => 999,
            'roster' => [],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', [
                'file' => $file,
                'strategy' => 'merge',
            ])
            ->assertSessionHasErrors(['file']);
    }

    public function test_merge_importa_roster_completo(): void
    {
        $user = User::factory()->create();
        $this->makeHero('thoran');
        $file = $this->uploadJson([
            'schema_version' => BackupController::SCHEMA_VERSION,
            'roster' => [
                [
                    'hero_slug' => 'thoran',
                    'level' => 240,
                    'ascension_code' => 'ascended_5_stars',
                    'ascension_value' => 16,
                    'signature_item' => 40,
                    'furniture' => 9,
                    'engraving' => 80,
                    'notes' => '# meu fav',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', [
                'file' => $file,
                'strategy' => 'merge',
            ])
            ->assertRedirect();

        $this->assertSame(1, $user->userHeroes()->count());
        $uh = $user->userHeroes()->first();
        $this->assertSame(40, $uh->signature_item);
        $this->assertSame('# meu fav', $uh->notes);
    }

    public function test_merge_skipa_hero_ja_no_roster(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('thoran');
        UserHero::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'hero_id' => $hero->id,
            'level' => 100,
            'ascension_value' => 5,
            'ascension_code' => 'rare_plus',
            'signature_item' => 0,
            'furniture' => 0,
            'engraving' => 0,
        ]);

        $file = $this->uploadJson([
            'schema_version' => BackupController::SCHEMA_VERSION,
            'roster' => [
                ['hero_slug' => 'thoran', 'level' => 240, 'signature_item' => 40],
            ],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertRedirect();

        // Mantém o original (level 100, SI 0) — merge não sobrescreve
        $this->assertSame(1, $user->userHeroes()->count());
        $this->assertSame(100, $user->userHeroes()->first()->level);
        $this->assertSame(0, $user->userHeroes()->first()->signature_item);
    }

    public function test_replace_apaga_e_recria_roster(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('thoran');
        UserHero::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'hero_id' => $hero->id,
            'level' => 100,
            'ascension_value' => 5,
            'ascension_code' => 'rare_plus',
            'signature_item' => 0,
            'furniture' => 0,
            'engraving' => 0,
        ]);

        $file = $this->uploadJson([
            'schema_version' => BackupController::SCHEMA_VERSION,
            'roster' => [
                ['hero_slug' => 'thoran', 'level' => 240, 'signature_item' => 40],
            ],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'replace'])
            ->assertRedirect();

        $this->assertSame(1, $user->userHeroes()->count());
        // Replace sobrescreve com SI 40
        $this->assertSame(40, $user->userHeroes()->first()->signature_item);
    }

    public function test_slug_inexistente_e_skipado(): void
    {
        $user = User::factory()->create();
        $this->makeHero('thoran');
        $file = $this->uploadJson([
            'schema_version' => BackupController::SCHEMA_VERSION,
            'roster' => [
                ['hero_slug' => 'thoran'],
                ['hero_slug' => 'nao-existe'],
                ['hero_slug' => 'tambem-nao-existe'],
            ],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($msg) => str_contains($msg, '1 heroes') && str_contains($msg, '2 itens skipados'));

        $this->assertSame(1, $user->userHeroes()->count());
    }

    public function test_importa_formacao_com_tags_e_teams(): void
    {
        $user = User::factory()->create();
        $this->makeHero('athalia');

        $file = $this->uploadJson([
            'schema_version' => BackupController::SCHEMA_VERSION,
            'formation_tags' => [
                ['name' => 'speedrun', 'color' => 'emerald'],
            ],
            'formations' => [
                [
                    'name' => 'Counter Wilders',
                    'challenge_type_code' => 'campaign',
                    'mode' => 'target',
                    'teams_count' => 1,
                    'notes' => 'plano',
                    'tag_names' => ['speedrun'],
                    'teams' => [
                        [
                            'position' => 1,
                            'heroes' => [
                                ['slot' => 1, 'hero_slug' => 'athalia'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertRedirect();

        $f = $user->formations()->with('tags', 'teams.teamHeroes')->first();
        $this->assertNotNull($f);
        $this->assertSame('Counter Wilders', $f->name);
        $this->assertSame('plano', $f->notes);
        $this->assertCount(1, $f->tags);
        $this->assertSame('speedrun', $f->tags->first()->name);
        $this->assertSame(1, $f->teams->first()->teamHeroes->first()->hero->slug ? 1 : 0);
    }

    public function test_challenge_type_code_inexistente_skipa_formacao(): void
    {
        $user = User::factory()->create();
        $file = $this->uploadJson([
            'schema_version' => BackupController::SCHEMA_VERSION,
            'formations' => [
                ['name' => 'X', 'challenge_type_code' => 'nao_existe', 'teams' => []],
            ],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertRedirect();

        $this->assertSame(0, $user->formations()->count());
    }

    public function test_importa_favoritos(): void
    {
        $user = User::factory()->create();
        $h1 = $this->makeHero('h1');
        $h2 = $this->makeHero('h2');
        $file = $this->uploadJson([
            'schema_version' => BackupController::SCHEMA_VERSION,
            'favorite_hero_slugs' => ['h1', 'h2', 'h-nao-existe'],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertRedirect();

        $ids = $user->defaultGameAccount->favoriteHeroes()->pluck('heroes.id')->all();
        $this->assertContains($h1->id, $ids);
        $this->assertContains($h2->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_importa_presets_skipa_nome_duplicado_em_merge(): void
    {
        $user = User::factory()->create();
        ScoringWeightPreset::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'name' => 'pvp',
            'challenge_type_id' => null,
            'weights' => ['power_weight' => 1.0],
        ]);

        $file = $this->uploadJson([
            'schema_version' => BackupController::SCHEMA_VERSION,
            'scoring_weight_presets' => [
                ['name' => 'pvp', 'weights' => ['power_weight' => 3.0]],
                ['name' => 'novo', 'weights' => ['power_weight' => 2.0]],
            ],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertRedirect();

        $this->assertSame(2, $user->scoringWeightPresets()->count());
        // O original 'pvp' deve ter power_weight 1.0 (não sobrescrito)
        $pvp = $user->defaultGameAccount->scoringWeightPresets()->where('name', 'pvp')->first();
        $this->assertSame(1.0, (float) $pvp->weights['power_weight']);
    }

    public function test_isola_por_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $hero = $this->makeHero('h');

        $file = $this->uploadJson([
            'schema_version' => BackupController::SCHEMA_VERSION,
            'roster' => [['hero_slug' => 'h', 'level' => 240]],
        ]);

        $this->actingAs($userA)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertRedirect();

        $this->assertSame(1, $userA->userHeroes()->count());
        $this->assertSame(0, $userB->userHeroes()->count());
    }

    public function test_replace_remove_tags_existentes_antes_de_importar(): void
    {
        $user = User::factory()->create();
        FormationTag::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'name' => 'tag-velha',
            'color' => 'amber',
        ]);

        $file = $this->uploadJson([
            'schema_version' => BackupController::SCHEMA_VERSION,
            'formation_tags' => [
                ['name' => 'tag-nova', 'color' => 'sky'],
            ],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'replace'])
            ->assertRedirect();

        $this->assertSame(1, $user->formationTags()->count());
        $this->assertSame('tag-nova', $user->formationTags()->first()->name);
    }
}
