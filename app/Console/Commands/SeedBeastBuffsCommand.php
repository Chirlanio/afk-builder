<?php

namespace App\Console\Commands;

use App\Models\Beast;
use Illuminate\Console\Command;

class SeedBeastBuffsCommand extends Command
{
    protected $signature = 'beasts:seed-buffs
        {--force : Sobrescreve bestas que já têm buffs configurados}';

    protected $description = 'Popula buffs_strength/intelligence/agility/team com valores % no nível 36 baseados na meta do jogo. Por default, não sobrescreve bestas com buffs já configurados.';

    /**
     * Map slug → buffs por scope no nível 36.
     * Estrutura: ['scope' => ['stat_key' => valor_percentual]]
     * Stats em % no nível máximo (36); o PowerCalculator escala pelo level/36 ao aplicar.
     * Bestas omitidas ficam com buffs vazios (admin preenche depois).
     *
     * Valores são heurísticos baseados no tier/role no AFK Journey.
     * Apenas thistlekin (Flordecardo) tem valores fornecidos pelo user — usados como referência;
     * outras bestas seguem proporção similar ajustada por tier.
     */
    private const BUFFS = [
        // ── Referência fornecida pelo user (S+ tier, ainda que listada como sem tier no seeder)
        'thistlekin' => [
            'strength' => ['hp' => 17.59, 'defense' => 47.55, 'dodge' => 24.23, 'vitality' => 8.84, 'crit_block_rate' => 10.61, 'magic_resistance' => 66.09],
            'team' => ['hp' => 14.03, 'attack' => 10.24, 'defense' => 37.93],
        ],
        // ── S+ tier
        'slumber-seal' => [
            'agility' => ['attack' => 20, 'crit_amp' => 30, 'crit_rate' => 15],
            'team' => ['attack' => 15, 'crit_rate' => 8],
        ],
        // ── S tier
        'flutterplume-owl' => [
            'team' => ['hp' => 12, 'attack' => 12, 'defense' => 12, 'crit_rate' => 8],
        ],
        'pandy-dumpty' => [
            'team' => ['crit_rate' => 20, 'crit_amp' => 25, 'attack' => 10],
        ],
        'nightfall-pegasus' => [
            'strength' => ['hp' => 15, 'defense' => 25],
            'intelligence' => ['hp' => 15, 'defense' => 25],
            'team' => ['hp' => 10, 'attack' => 10, 'defense' => 15],
        ],
        'treatspector' => [
            'team' => ['hp' => 20, 'defense' => 20, 'vitality' => 10],
        ],
        // ── A tier
        'aureus' => [
            'agility' => ['attack' => 25, 'crit_rate' => 15, 'crit_amp' => 20],
            'team' => ['attack' => 8],
        ],
        'talismane' => [
            'strength' => ['hp' => 30, 'attack' => 40, 'defense' => 25, 'crit_amp' => 15],
        ],
        'ice-crown' => [
            'agility' => ['attack' => 20, 'crit_rate' => 18, 'dodge' => 15],
            'team' => ['attack' => 6, 'defense' => 6],
        ],
        'rock-crown-lizard' => [
            'strength' => ['hp' => 25, 'defense' => 40, 'vitality' => 12, 'crit_block_rate' => 8],
            'team' => ['defense' => 12, 'hp' => 8],
        ],
        'fox-fatale' => [
            'intelligence' => ['attack' => 30, 'crit_amp' => 25, 'magic_resistance' => 15],
            'team' => ['attack' => 6],
        ],
        'trickery-chest' => [
            'intelligence' => ['attack' => 25, 'crit_rate' => 12],
            'team' => ['attack' => 8, 'crit_rate' => 6],
        ],
        // ── B tier
        'phantasmoth' => [
            'agility' => ['attack' => 18, 'crit_amp' => 18],
            'team' => ['attack' => 6],
        ],
        'pearlcrest-ibis' => [
            'strength' => ['hp' => 18, 'defense' => 22, 'vitality' => 8],
            'team' => ['hp' => 6, 'defense' => 8],
        ],
        'savage-souffle' => [
            'team' => ['attack' => 10, 'crit_rate' => 8, 'crit_amp' => 10],
        ],
        'shroom-spooder' => [
            'intelligence' => ['attack' => 22, 'magic_resistance' => 12],
            'team' => ['attack' => 5],
        ],
        'winged-lion' => [
            'strength' => ['attack' => 20, 'hp' => 12],
            'team' => ['attack' => 8, 'defense' => 5],
        ],
        'count-snickerfang' => [
            'intelligence' => ['attack' => 18, 'crit_rate' => 10],
            'team' => ['attack' => 5],
        ],
        // ── C tier
        'bellbellow' => [
            'strength' => ['defense' => 18, 'hp' => 10],
            'team' => ['defense' => 6],
        ],
        'blade-ridge' => [
            'team' => ['attack' => 8, 'magic_resistance' => 10],
        ],
        'fire-breather' => [
            'strength' => ['attack' => 15, 'hp' => 10],
            'team' => ['attack' => 5],
        ],
        'radish-rotunda' => [
            'team' => ['hp' => 12, 'defense' => 10],
        ],
        // ── D tier
        'polar-beast' => [
            'team' => ['attack' => 5, 'defense' => 5],
        ],
        'tufty-ears' => [
            'team' => ['hp' => 8, 'defense' => 5],
        ],
        'feline-vesperio' => [
            'agility' => ['attack' => 8, 'crit_rate' => 5],
        ],
        'joyous-roamer' => [
            'team' => ['attack' => 4, 'defense' => 4],
        ],
        // ── F tier (raras básicas)
        'dreary-ball' => [
            'team' => ['attack' => 2, 'defense' => 2],
        ],
        'grassy-orb' => [
            'team' => ['attack' => 3],
        ],
        // Sem mapping: 'joyous-reamer' (deixa vazio)
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $updated = 0;
        $skipped = 0;
        $unmapped = [];

        $beasts = Beast::query()->where('is_active', true)->get();

        foreach ($beasts as $beast) {
            $buffsBySope = self::BUFFS[$beast->slug] ?? null;
            if ($buffsBySope === null) {
                $unmapped[] = $beast->slug;

                continue;
            }

            $hasManualValues = $this->hasAnyBuff($beast);

            if ($hasManualValues && ! $force) {
                $skipped++;
                $this->line("  skip {$beast->name} (já tem buffs configurados — use --force)");

                continue;
            }

            $beast->update([
                'buffs_strength' => $buffsBySope['strength'] ?? null,
                'buffs_intelligence' => $buffsBySope['intelligence'] ?? null,
                'buffs_agility' => $buffsBySope['agility'] ?? null,
                'buffs_team' => $buffsBySope['team'] ?? null,
            ]);
            $updated++;

            $summary = [];
            foreach (['strength', 'intelligence', 'agility', 'team'] as $scope) {
                $count = count($buffsBySope[$scope] ?? []);
                if ($count > 0) {
                    $summary[] = "{$scope}({$count})";
                }
            }
            $this->line("  ✓ {$beast->name}: ".implode(' ', $summary));
        }

        $this->newLine();
        $this->info("Atualizadas: {$updated}");
        if ($skipped > 0) {
            $this->warn("Puladas (já tinham buff): {$skipped}");
        }
        if (! empty($unmapped)) {
            $this->warn('Sem mapeamento (preserva vazio): '.implode(', ', $unmapped));
        }

        return self::SUCCESS;
    }

    private function hasAnyBuff(Beast $beast): bool
    {
        foreach (['buffs_strength', 'buffs_intelligence', 'buffs_agility', 'buffs_team'] as $field) {
            $value = $beast->$field;
            if (is_array($value) && ! empty($value)) {
                return true;
            }
        }

        return false;
    }
}
