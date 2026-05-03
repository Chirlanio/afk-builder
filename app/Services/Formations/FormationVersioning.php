<?php

namespace App\Services\Formations;

use App\Models\Formation;
use App\Models\FormationVersion;
use Illuminate\Support\Facades\DB;

class FormationVersioning
{
    public const MAX_VERSIONS_PER_FORMATION = 20;

    public function snapshot(Formation $formation): FormationVersion
    {
        $formation->load('teams.teamHeroes');

        $snapshot = [
            'name' => $formation->name,
            'notes' => $formation->notes,
            'teams' => $formation->teams->map(fn ($team) => [
                'position' => $team->position,
                'name' => $team->name,
                'heroes' => $team->teamHeroes->map(fn ($th) => [
                    'slot' => $th->slot,
                    'hero_id' => $th->hero_id,
                    'row' => $th->row,
                ])->values()->all(),
            ])->values()->all(),
        ];

        $version = $formation->versions()->create([
            'snapshot' => $snapshot,
            'created_at' => now(),
        ]);

        $this->prune($formation);

        return $version;
    }

    public function restore(Formation $formation, FormationVersion $version): void
    {
        if ($version->formation_id !== $formation->id) {
            abort(404);
        }

        $snapshot = $version->snapshot;

        DB::transaction(function () use ($formation, $snapshot) {
            $formation->update([
                'name' => $snapshot['name'] ?? $formation->name,
                'notes' => $snapshot['notes'] ?? null,
            ]);

            $teamsByPosition = $formation->teams()->get()->keyBy('position');

            foreach ($snapshot['teams'] ?? [] as $teamPayload) {
                $team = $teamsByPosition[$teamPayload['position']] ?? null;
                if (! $team) {
                    continue;
                }

                $team->update(['name' => $teamPayload['name'] ?? null]);
                $team->teamHeroes()->delete();

                foreach ($teamPayload['heroes'] ?? [] as $h) {
                    $team->teamHeroes()->create([
                        'hero_id' => $h['hero_id'],
                        'slot' => $h['slot'],
                        'row' => $h['row'] ?? null,
                    ]);
                }
            }
        });
    }

    private function prune(Formation $formation): void
    {
        $count = $formation->versions()->count();
        if ($count <= self::MAX_VERSIONS_PER_FORMATION) {
            return;
        }

        $idsToKeep = $formation->versions()
            ->orderByDesc('created_at')
            ->limit(self::MAX_VERSIONS_PER_FORMATION)
            ->pluck('id');

        $formation->versions()
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }
}
