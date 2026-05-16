<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formation_teams', function (Blueprint $table) {
            $table->foreignId('beast_id')
                ->nullable()
                ->after('faction_bonuses')
                ->constrained('beasts')
                ->nullOnDelete();

            $table->index('beast_id');
        });

        // Backfill: cada team da formation herda o beast_id da formation.
        // Pré-Fase 48 a besta era 1 por formation; agora distribuímos pra todos os teams.
        // Usa subquery (agnóstico MySQL/SQLite — UPDATE...JOIN não funciona em SQLite).
        DB::table('formation_teams')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('formations')
                    ->whereColumn('formations.id', 'formation_teams.formation_id')
                    ->whereNotNull('formations.beast_id');
            })
            ->update([
                'beast_id' => DB::raw('(SELECT beast_id FROM formations WHERE formations.id = formation_teams.formation_id)'),
            ]);

        Schema::table('formations', function (Blueprint $table) {
            $table->dropForeign(['beast_id']);
            $table->dropColumn('beast_id');
        });
    }

    public function down(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->foreignId('beast_id')
                ->nullable()
                ->after('share_token')
                ->constrained('beasts')
                ->nullOnDelete();
        });

        // Reverse backfill: pega o beast_id do primeiro team com beast da formation.
        DB::table('formations')->update([
            'beast_id' => DB::raw('(SELECT beast_id FROM formation_teams WHERE formation_teams.formation_id = formations.id AND beast_id IS NOT NULL ORDER BY position ASC LIMIT 1)'),
        ]);

        Schema::table('formation_teams', function (Blueprint $table) {
            $table->dropForeign(['beast_id']);
            $table->dropIndex(['beast_id']);
            $table->dropColumn('beast_id');
        });
    }
};
