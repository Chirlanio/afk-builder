<?php

namespace App\Console\Commands;

use App\Models\GameAccount;
use App\Models\RosterPowerSnapshot;
use App\Services\Optimization\PowerCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SnapshotRosterPowerCommand extends Command
{
    protected $signature = 'roster:snapshot
        {--user-id= : Restringe a users com este id (todas as game_accounts do user são processadas)}
        {--date= : Data do snapshot no formato Y-m-d (padrão: hoje)}';

    protected $description = 'Cria/atualiza snapshot diário do power total do roster por game_account (idempotente via upsert).';

    public function handle(PowerCalculator $power): int
    {
        $date = $this->option('date')
            ? Carbon::createFromFormat('Y-m-d', $this->option('date'))->startOfDay()
            : Carbon::today();
        $dateStr = $date->toDateString();

        $userIdOption = $this->option('user-id');
        $accountsQuery = GameAccount::query();
        if ($userIdOption !== null) {
            $accountsQuery->where('user_id', (int) $userIdOption);
        }

        $accounts = $accountsQuery->get();
        $total = $accounts->count();
        $created = 0;
        $skipped = 0;

        foreach ($accounts as $account) {
            $userHeroes = $account->userHeroes()
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

            // upsert por (game_account_id, snapshot_date) — re-rodar no mesmo dia atualiza
            RosterPowerSnapshot::updateOrCreate(
                ['game_account_id' => $account->id, 'snapshot_date' => $dateStr],
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
            'Snapshot %s: %d contas processadas, %d criados/atualizados, %d skipados (roster vazio).',
            $dateStr,
            $total,
            $created,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
