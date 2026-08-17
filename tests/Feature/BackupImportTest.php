<?php

namespace Tests\Feature;

use App\Http\Controllers\Settings\BackupController;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
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

    /**
     * Helper pra shape v2 (multi-account). Aceita 1+ accounts.
     */
    private function v2Payload(array $accounts): array
    {
        return [
            'schema_version' => 2,
            'exported_at' => now()->toIso8601String(),
            'user' => ['id' => 1, 'name' => 'x', 'email' => 'x@x.com', 'locale' => 'pt'],
            'accounts' => $accounts,
        ];
    }

    public function test_guest_recebe_redirect(): void
    {
        $this->post('/settings/backup/import')->assertRedirect('/login');
    }

    public function test_rejeita_arquivo_sem_schema_version(): void
    {
        $user = User::factory()->create();
        $file = $this->uploadJson(['accounts' => []]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertSessionHasErrors(['file']);
    }

    public function test_rejeita_schema_version_incompativel(): void
    {
        $user = User::factory()->create();
        $file = $this->uploadJson(['schema_version' => 999, 'accounts' => []]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertSessionHasErrors(['file']);
    }

    // ============================================================
    // v2 (multi-account)
    // ============================================================

    public function test_v2_merge_cria_nova_conta_com_roster(): void
    {
        $user = User::factory()->create();
        $this->makeHero('thoran');

        $file = $this->uploadJson($this->v2Payload([
            [
                'name' => 'Alt',
                'roster' => [['hero_slug' => 'thoran', 'level' => 240, 'signature_item' => 40]],
            ],
        ]));

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertRedirect();

        // Continua com Conta Principal + adiciona Alt
        $this->assertSame(2, $user->gameAccounts()->count());
        $alt = $user->gameAccounts()->where('name', 'Alt')->first();
        $this->assertNotNull($alt);
        $this->assertSame(1, $alt->userHeroes()->count());
        $this->assertSame(40, $alt->userHeroes()->first()->signature_item);
    }

    public function test_v2_merge_sufixa_nome_quando_colide(): void
    {
        $user = User::factory()->create();

        $file = $this->uploadJson($this->v2Payload([
            ['name' => 'Conta Principal', 'roster' => []],
        ]));

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertRedirect();

        $this->assertSame(2, $user->gameAccounts()->count());
        $this->assertNotNull($user->gameAccounts()->where('name', 'Conta Principal (importada)')->first());
    }

    public function test_v2_replace_apaga_todas_e_recria(): void
    {
        $user = User::factory()->create();
        $this->makeHero('thoran');
        $user->gameAccounts()->create(['name' => 'Alt']);
        $this->assertSame(2, $user->gameAccounts()->count());

        $file = $this->uploadJson($this->v2Payload([
            [
                'name' => 'Nova',
                'is_default' => true,
                'roster' => [['hero_slug' => 'thoran', 'level' => 240]],
            ],
        ]));

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'replace'])
            ->assertRedirect();

        // Apagou as 2 originais, criou só a "Nova"
        $this->assertSame(1, $user->gameAccounts()->count());
        $nova = $user->gameAccounts()->first();
        $this->assertSame('Nova', $nova->name);
        $this->assertSame(1, $nova->userHeroes()->count());
    }

    public function test_v2_importa_multiplas_contas(): void
    {
        $user = User::factory()->create();
        $this->makeHero('thoran');
        $this->makeHero('athalia');

        $file = $this->uploadJson($this->v2Payload([
            ['name' => 'Main', 'is_default' => true, 'roster' => [['hero_slug' => 'thoran']]],
            ['name' => 'Alt', 'roster' => [['hero_slug' => 'athalia']]],
        ]));

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'replace'])
            ->assertRedirect();

        $this->assertSame(2, $user->gameAccounts()->count());
        $main = $user->gameAccounts()->where('name', 'Main')->first();
        $alt = $user->gameAccounts()->where('name', 'Alt')->first();
        $this->assertTrue($main->is_default);
        $this->assertSame('thoran', $main->userHeroes->first()->hero->slug);
        $this->assertSame('athalia', $alt->userHeroes->first()->hero->slug);
    }

    // ============================================================
    // v1 (retrocompat)
    // ============================================================

    public function test_v1_retrocompat_cria_conta_importada(): void
    {
        $user = User::factory()->create();
        $this->makeHero('thoran');

        // Backup v1 legacy: dados no topo, sem accounts[]
        $file = $this->uploadJson([
            'schema_version' => 1,
            'roster' => [['hero_slug' => 'thoran', 'level' => 240, 'signature_item' => 30]],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertRedirect();

        $imported = $user->gameAccounts()->where('name', 'Conta Importada')->first();
        $this->assertNotNull($imported);
        $this->assertSame(1, $imported->userHeroes()->count());
        $this->assertSame(30, $imported->userHeroes()->first()->signature_item);
    }

    public function test_v1_replace_apaga_tudo_e_cria_conta_importada(): void
    {
        $user = User::factory()->create();
        $user->gameAccounts()->create(['name' => 'Alt']);
        $this->makeHero('thoran');

        $file = $this->uploadJson([
            'schema_version' => 1,
            'roster' => [['hero_slug' => 'thoran']],
        ]);

        $this->actingAs($user)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'replace'])
            ->assertRedirect();

        $this->assertSame(1, $user->gameAccounts()->count());
        $this->assertSame('Conta Importada', $user->gameAccounts()->first()->name);
    }

    // ============================================================
    // Isolamento
    // ============================================================

    public function test_isola_por_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->makeHero('thoran');

        $file = $this->uploadJson($this->v2Payload([
            ['name' => 'Alt A', 'roster' => [['hero_slug' => 'thoran']]],
        ]));

        $this->actingAs($userA)
            ->post('/settings/backup/import', ['file' => $file, 'strategy' => 'merge'])
            ->assertRedirect();

        $this->assertSame(2, $userA->gameAccounts()->count());
        $this->assertSame(1, $userB->gameAccounts()->count());
        $this->assertSame(0, $userB->userHeroes()->count());
    }
}
