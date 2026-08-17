<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices/uniques compostos que referenciam user_id e precisam ser dropados
     * antes de dropar a coluna (SQLite não permite dropar coluna com índices
     * dependentes; MySQL exige que FK seja dropada primeiro pra depois liberar
     * o índice pra drop).
     *
     * @var array<string, array{indexes: array<int, string>, uniques: array<int, string>}>
     */
    private const TABLES = [
        'user_heroes' => [
            'uniques' => ['user_heroes_user_id_hero_id_unique'],
            'indexes' => ['user_heroes_user_id_ascension_value_index'],
        ],
        'formations' => [
            'uniques' => [],
            'indexes' => ['formations_user_id_mode_index'],
        ],
        'formation_tags' => [
            'uniques' => ['formation_tags_user_id_name_unique'],
            'indexes' => [],
        ],
        'user_hero_favorites' => [
            'uniques' => ['user_hero_favorites_user_id_hero_id_unique'],
            'indexes' => [],
        ],
        'user_tier_lists' => [
            'uniques' => [],
            'indexes' => ['user_tier_lists_user_id_index'],
        ],
        'user_resources' => [
            'uniques' => ['user_resources_user_id_unique'],
            'indexes' => [],
        ],
        'user_resonating_crystals' => [
            'uniques' => ['user_resonating_crystals_user_id_unique'],
            'indexes' => [],
        ],
        'roster_power_snapshots' => [
            'uniques' => ['roster_power_snapshots_user_id_snapshot_date_unique'],
            'indexes' => ['roster_power_snapshots_user_id_snapshot_date_index'],
        ],
        'scoring_weight_presets' => [
            'uniques' => ['scoring_weight_presets_user_id_name_unique'],
            'indexes' => ['scoring_weight_presets_user_id_challenge_type_id_index'],
        ],
    ];

    public function up(): void
    {
        // Fase 1: NOT NULL em game_account_id (dados já backfilados na Etapa A)
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('game_account_id')->nullable(false)->change();
            });
        }

        // Fase 2: dropa FK do user_id (libera índices que dependem dela pra
        // serem dropados a seguir; MySQL exige essa ordem).
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['user_id']);
            });
        }

        // Fase 3: dropa índices/uniques compostos que dependem de user_id
        // (SQLite exige explícito, MySQL agora libera após drop da FK).
        foreach (self::TABLES as $table => $refs) {
            Schema::table($table, function (Blueprint $blueprint) use ($refs) {
                foreach ($refs['uniques'] as $name) {
                    $blueprint->dropUnique($name);
                }
                foreach ($refs['indexes'] as $name) {
                    $blueprint->dropIndex($name);
                }
            });
        }

        // Fase 4: dropa a coluna user_id.
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('user_id');
            });
        }

        // Fase 5: recria índices equivalentes por game_account_id.
        Schema::table('user_heroes', function (Blueprint $blueprint) {
            $blueprint->unique(['game_account_id', 'hero_id']);
            $blueprint->index(['game_account_id', 'ascension_value']);
        });
        Schema::table('formations', function (Blueprint $blueprint) {
            $blueprint->index(['game_account_id', 'mode']);
        });
        Schema::table('formation_tags', function (Blueprint $blueprint) {
            $blueprint->unique(['game_account_id', 'name']);
        });
        Schema::table('user_hero_favorites', function (Blueprint $blueprint) {
            $blueprint->unique(['game_account_id', 'hero_id']);
        });
        Schema::table('user_resources', function (Blueprint $blueprint) {
            $blueprint->unique('game_account_id');
        });
        Schema::table('user_resonating_crystals', function (Blueprint $blueprint) {
            $blueprint->unique('game_account_id');
        });
        Schema::table('roster_power_snapshots', function (Blueprint $blueprint) {
            $blueprint->unique(['game_account_id', 'snapshot_date']);
        });
        Schema::table('scoring_weight_presets', function (Blueprint $blueprint) {
            $blueprint->unique(['game_account_id', 'name']);
            $blueprint->index(['game_account_id', 'challenge_type_id']);
        });
    }

    public function down(): void
    {
        // Rollback destrutivo pra dados: recria user_id nullable, sem restaurar valores.
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('game_account_id')->nullable()->change();
            });
        }
    }
};
