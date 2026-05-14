<?php

namespace App\Console\Commands;

use App\Models\RosterPowerSnapshot;
use App\Models\User;
use App\Models\UserHero;
use App\Services\Optimization\PowerCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SnapshotRosterPowerCommand extends Command
{
    protected $signature = 'roster:snapshot
        {--user-id= : Restringe a um único user_id (padrão: todos os users com roster)}
        {--date= : Data do snapshot no formato Y-m-d (padrão: hoje)}';

    protected $description = 'Cria/atualiza snapshot diário do power total do roster por user (idempotente via upsert).';

    public function handle(PowerCalculator $power): int
    {
        $date = $this->option('date')
            ? Carbon::createFromFormat('Y-m-d', $this->option('date'))->startOfDay()
            : Carbon::today();
        $dateStr = $date->toDateString();

        $userIdOption = $this->option('user-id');
        $usersQuery = User::query();
        if ($userIdOption !== null) {
            $usersQuery->where('id', (int) $userIdOption);
        }

        $users = $usersQuery->get();
        $total = $users->count();
        $created = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $userHeroes = UserHero::query()
                ->where('user_id', $user->id)
                ->with([
                    'hero:id,slug,name,faction_id,hero_class_id,tier_id',
                    'hero.faction:id,code',
                    'hero.heroClass:id,code',
                    'hero.tier:id,code',
                    'hero.stats',
                    'artifact:id,attributes',
                    'collection:id,hp_bonus,attack_bonus,defense_bonus',
                ])
                ->get();

            if ($userHeroes->isEmpty()) {
                $skipped++;

                continue;
            }

            $totalPower = 0;
            $topHeroId = null;
            $topHeroPower = 0;

            foreach ($userHeroes as $uh) {
                $p = (int) round($power->forUserHero($uh, $uh->hero));
                $totalPower += $p;
                if ($p > $topHeroPower) {
                    $topHeroPower = $p;
                    $topHeroId = $uh->hero?->id;
                }
            }

            // upsert por (user_id, snapshot_date) — re-rodar no mesmo dia atualiza
            RosterPowerSnapshot::updateOrCreate(
                ['user_id' => $user->id, 'snapshot_date' => $dateStr],
                [
                    'total_power' => $totalPower,
                    'hero_count' => $userHeroes->count(),
                    'top_hero_id' => $topHeroId,
                    'top_hero_power' => $topHeroPower,
                    'created_at' => now(),
                ],
            );

            $created++;
        }

        $this->info(sprintf(
            'Snapshot %s: %d processados, %d criados/atualizados, %d skipados (roster vazio).',
            $dateStr,
            $total,
            $created,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
