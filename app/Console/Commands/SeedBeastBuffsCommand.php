<?php

namespace App\Console\Commands;

use App\Models\Beast;
use Illuminate\Console\Command;

class SeedBeastBuffsCommand extends Command
{
    protected $signature = 'beasts:seed-buffs
        {--force : Sobrescreve bestas que já tem algum buff > 0}';

    protected $description = 'Popula strength/intelligence/agility buffs nas bestas com valores baseados na meta do jogo. Por default, só atualiza bestas com os 3 buffs em 0.';

    /**
     * Map slug → [strength, intelligence, agility].
     * Valores em pontos (0-100). Heurísticos baseados no role e tier de cada besta no AFK Journey.
     * Bestas omitidas ficam com 0/0/0.
     */
    private const BUFFS = [
        // S+ tier — top picks
        'slumber-seal' => [40, 30, 80],          // Maior dano: principalmente carries (agi/str)
        // S tier
        'flutterplume-owl' => [40, 40, 40],      // Flexível: serve qualquer comp
        'pandy-dumpty' => [50, 50, 50],          // Crit Rate/Amp universal
        'nightfall-pegasus' => [45, 45, 30],     // Fabled — carry geral
        'treatspector' => [35, 35, 35],          // Sobrevivência geral
        // A tier
        'aureus' => [30, 30, 60],                // Buffa carry (geralmente DPS agi)
        'talismane' => [70, 20, 30],             // Buff massivo em 1 hero (geralmente tank str)
        'ice-crown' => [20, 30, 60],             // Control + agi
        'rock-crown-lizard' => [60, 20, 20],     // Defensiva: tank str
        'fox-fatale' => [20, 60, 20],            // Charm: mage int
        'trickery-chest' => [25, 50, 25],        // Energia: synergia com mage
        // B tier
        'phantasmoth' => [40, 20, 50],           // Normal attack carries (agi)
        'pearlcrest-ibis' => [40, 20, 20],       // Defensiva tank
        'savage-souffle' => [30, 30, 40],        // Energia + CC
        'shroom-spooder' => [20, 50, 20],        // Debuffs: mage int
        'winged-lion' => [40, 20, 30],           // Atk buff + def reduce
        'count-snickerfang' => [25, 35, 25],     // Niche
        // C tier
        'bellbellow' => [25, 15, 15],            // Def
        'blade-ridge' => [25, 25, 15],           // Res reduction (anti-boss)
        'fire-breather' => [30, 15, 25],         // Dano CC immunity
        'radish-rotunda' => [25, 25, 15],        // Def healer-friendly
        // D tier
        'polar-beast' => [15, 25, 15],           // Debuffs
        'tufty-ears' => [20, 10, 15],            // Cleanse
        'feline-vesperio' => [10, 10, 15],       // Obsoleto
        'joyous-roamer' => [15, 10, 10],         // Fraco
        // F tier (rara básica)
        'dreary-ball' => [10, 5, 5],
        'grassy-orb' => [5, 10, 15],
        // Sem tier conhecido — deixa zerado (fica como "não sugerir")
        // 'joyous-reamer', 'thistlekin'
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $updated = 0;
        $skipped = 0;
        $unmapped = [];

        $beasts = Beast::query()->where('is_active', true)->get();

        foreach ($beasts as $beast) {
            $buffs = self::BUFFS[$beast->slug] ?? null;
            if ($buffs === null) {
                $unmapped[] = $beast->slug;

                continue;
            }

            $hasManualValues = $beast->strength_buff > 0
                || $beast->intelligence_buff > 0
                || $beast->agility_buff > 0;

            if ($hasManualValues && ! $force) {
                $skipped++;
                $this->line("  skip {$beast->name} (já tem buff configurado — use --force)");

                continue;
            }

            $beast->update([
                'strength_buff' => $buffs[0],
                'intelligence_buff' => $buffs[1],
                'agility_buff' => $buffs[2],
            ]);
            $updated++;
            $this->line("  ✓ {$beast->name}: F+{$buffs[0]} / I+{$buffs[1]} / A+{$buffs[2]}");
        }

        $this->newLine();
        $this->info("Atualizadas: {$updated}");
        if ($skipped > 0) {
            $this->warn("Puladas (já tinham buff): {$skipped}");
        }
        if (! empty($unmapped)) {
            $this->warn('Sem mapeamento (preserva 0/0/0): '.implode(', ', $unmapped));
        }

        return self::SUCCESS;
    }
}
