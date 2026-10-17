<?php

namespace App\Http\Controllers;

use App\Models\ResonatingCrystalCost;
use App\Models\UserHero;
use App\Models\UserResonatingCrystal;
use App\Models\UserResource;
use App\Services\ActiveGameAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ResonatingCrystalController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    public function show(Request $request): Response
    {
        $account = $this->active->get();

        $crystal = $this->getOrCreateCrystal($account->id);
        $resources = $this->getOrCreateResources($account->id);

        // Top 5 do roster por nível (decresce). Esses são os heroes que definem o pentagrama
        // pré-upgrade (5º maior é quem dita o nível compartilhado).
        $top5 = $account->userHeroes()
            ->with([
                'hero:id,slug,name,portrait_url,faction_id,hero_class_id,tier_id',
                'hero.faction:id,code,name_pt',
                'hero.heroClass:id,code,name_pt',
                'hero.tier:id,code',
            ])
            ->orderByDesc('level')
            ->limit(5)
            ->get()
            ->map(fn (UserHero $uh) => [
                'hero_id' => $uh->hero_id,
                'slug' => $uh->hero?->slug,
                'name' => $uh->hero?->name,
                'portrait_url' => $uh->hero?->portrait_url,
                'faction_code' => $uh->hero?->faction?->code,
                'class_code' => $uh->hero?->heroClass?->code,
                'tier_code' => $uh->hero?->tier?->code,
                'level' => (int) $uh->level,
                'ascension_value' => (int) $uh->ascension_value,
            ]);

        // Nível efetivo do cristal:
        // - pré-upgrade (5 heroes < 240): 5º maior do roster
        // - pós-upgrade: stored.level
        $fifthHighestLevel = (int) ($top5->last()['level'] ?? 0);
        // Também conta como evoluído quando o jogador informou nível/teto do jogo:
        // o roster no app pode estar incompleto (ex: conta nova, 675/1040 no jogo).
        $isUpgraded = ($top5->count() >= 5 && $fifthHighestLevel >= UserResonatingCrystal::MIN_LEVEL)
            || $crystal->level > UserResonatingCrystal::MIN_LEVEL
            || $crystal->level_cap !== null;
        $effectiveLevel = $isUpgraded ? $crystal->level : $fifthHighestLevel;

        $levelCap = UserResonatingCrystal::levelCapForAccount($account);
        $canUpgrade = $isUpgraded && $crystal->level < $levelCap;

        // Custo do próximo "fortalecimento" (incremento de progress). Custo total do nível atual + 1,
        // dividido por 10 fortalecimentos. O 10º também cobra dust.
        $nextLevelCost = $canUpgrade
            ? ResonatingCrystalCost::forLevel($crystal->level + 1)
            : null;

        $strengthenCost = null;
        if ($nextLevelCost) {
            $isLastStrengthen = ($crystal->strengthen_progress + 1) >= UserResonatingCrystal::STRENGTHENS_PER_LEVEL;
            $strengthenCost = [
                'gold' => (int) ceil($nextLevelCost->gold_cost / UserResonatingCrystal::STRENGTHENS_PER_LEVEL),
                'hero_exp' => (int) ceil($nextLevelCost->hero_exp_cost / UserResonatingCrystal::STRENGTHENS_PER_LEVEL),
                'hero_essence' => $isLastStrengthen ? $nextLevelCost->dust_cost : 0,
                'is_last_in_cycle' => $isLastStrengthen,
                'next_level' => $crystal->level + 1,
            ];
        }

        return Inertia::render('crystal/index', [
            'crystal' => [
                'level' => $crystal->level,
                'strengthen_progress' => $crystal->strengthen_progress,
                'strengthens_per_level' => UserResonatingCrystal::STRENGTHENS_PER_LEVEL,
                'slots_unlocked' => $crystal->slots_unlocked,
                'slots_occupied' => $crystal->slots_occupied,
                'level_cap_manual' => $crystal->level_cap,
                'cost_data_max_level' => UserResonatingCrystal::COST_DATA_MAX_LEVEL,
                'effective_level' => $effectiveLevel,
                'is_upgraded' => $isUpgraded,
                'level_cap' => $levelCap,
                'can_upgrade' => $canUpgrade,
                'max_absolute' => UserResonatingCrystal::MAX_LEVEL,
            ],
            'pentagram' => $top5,
            'nextLevelCost' => $nextLevelCost ? [
                'level' => $nextLevelCost->level,
                'gold_cost' => $nextLevelCost->gold_cost,
                'hero_exp_cost' => $nextLevelCost->hero_exp_cost,
                'dust_cost' => $nextLevelCost->dust_cost,
            ] : null,
            'strengthenCost' => $strengthenCost,
            'resources' => $resources->only(UserResource::FIELDS),
        ]);
    }

    /**
     * Atualiza nível/progresso/slots manualmente (sync com o jogo, sem custo).
     */
    public function update(Request $request): JsonResponse
    {
        $crystal = $this->getOrCreateCrystal($this->active->get()->id);

        $data = $request->validate([
            'level' => ['nullable', 'integer', 'min:'.UserResonatingCrystal::MIN_LEVEL, 'max:'.UserResonatingCrystal::MAX_LEVEL],
            // Teto que o jogo mostra (ex: 675/1040). Vazio = estimar pelo roster.
            'level_cap' => ['nullable', 'integer', 'min:'.UserResonatingCrystal::MIN_LEVEL, 'max:'.UserResonatingCrystal::MAX_LEVEL, 'gte:'.($request->integer('level') ?: $crystal->level)],
            'strengthen_progress' => ['nullable', 'integer', 'min:0', 'max:'.(UserResonatingCrystal::STRENGTHENS_PER_LEVEL - 1)],
            'slots_unlocked' => ['nullable', 'integer', 'min:'.UserResonatingCrystal::INITIAL_SLOTS, 'max:'.UserResonatingCrystal::MAX_SLOTS],
            'slots_occupied' => ['nullable', 'integer', 'min:0', 'lte:'.($request->integer('slots_unlocked') ?: $crystal->slots_unlocked)],
        ], [
            'level_cap.gte' => 'O teto não pode ser menor que o nível atual.',
            'slots_occupied.lte' => 'As posições ocupadas não podem passar das liberadas.',
        ]);

        $payload = array_filter($data, fn ($v) => $v !== null);
        // level_cap/slots_occupied aceitam ser apagados (voltar a estimar / não informar).
        foreach (['level_cap', 'slots_occupied'] as $clearable) {
            if ($request->exists($clearable) && $request->input($clearable) === null) {
                $payload[$clearable] = null;
            }
        }
        $crystal->update($payload);

        return response()->json(['crystal' => $crystal->only(['level', 'level_cap', 'strengthen_progress', 'slots_unlocked', 'slots_occupied'])]);
    }

    /**
     * Aplica 1 fortalecimento: debita recursos, incrementa progress; ao chegar
     * em 10 sobe o level e zera o progress.
     */
    public function strengthen(Request $request): JsonResponse
    {
        $account = $this->active->get();
        $crystal = $this->getOrCreateCrystal($account->id);
        $resources = $this->getOrCreateResources($account->id);

        $levelCap = UserResonatingCrystal::levelCapForAccount($account);
        if ($crystal->level >= $levelCap) {
            return response()->json(['error' => 'level_cap_reached', 'cap' => $levelCap], 422);
        }

        $nextLevelCost = ResonatingCrystalCost::forLevel($crystal->level + 1);
        if ($nextLevelCost === null) {
            return response()->json(['error' => 'no_cost_data_for_next_level'], 422);
        }

        $isLast = ($crystal->strengthen_progress + 1) >= UserResonatingCrystal::STRENGTHENS_PER_LEVEL;
        $goldCost = (int) ceil($nextLevelCost->gold_cost / UserResonatingCrystal::STRENGTHENS_PER_LEVEL);
        $expCost = (int) ceil($nextLevelCost->hero_exp_cost / UserResonatingCrystal::STRENGTHENS_PER_LEVEL);
        $dustCost = $isLast ? $nextLevelCost->dust_cost : 0;

        if ($resources->gold < $goldCost || $resources->hero_exp < $expCost || $resources->hero_essence < $dustCost) {
            return response()->json([
                'error' => 'insufficient_resources',
                'required' => ['gold' => $goldCost, 'hero_exp' => $expCost, 'hero_essence' => $dustCost],
                'available' => $resources->only(['gold', 'hero_exp', 'hero_essence']),
            ], 422);
        }

        DB::transaction(function () use ($crystal, $resources, $goldCost, $expCost, $dustCost, $isLast) {
            $resources->decrement('gold', $goldCost);
            $resources->decrement('hero_exp', $expCost);
            if ($dustCost > 0) {
                $resources->decrement('hero_essence', $dustCost);
            }
            if ($isLast) {
                $crystal->level++;
                $crystal->strengthen_progress = 0;
            } else {
                $crystal->strengthen_progress++;
            }
            $crystal->save();
        });

        return response()->json([
            'crystal' => $crystal->fresh()->only(['level', 'strengthen_progress', 'slots_unlocked']),
            'resources' => $resources->fresh()->only(UserResource::FIELDS),
        ]);
    }

    private function getOrCreateCrystal(int $accountId): UserResonatingCrystal
    {
        return UserResonatingCrystal::firstOrCreate(['game_account_id' => $accountId]);
    }

    private function getOrCreateResources(int $accountId): UserResource
    {
        return UserResource::firstOrCreate(['game_account_id' => $accountId]);
    }
}
