<?php

namespace App\Http\Controllers;

use App\Models\Artifact;
use App\Models\Collection;
use App\Models\Equipment;
use App\Models\Hero;
use App\Models\UserHero;
use App\Services\ActiveGameAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UserHeroController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    private const ASCENSION_CODES = [
        'common', 'common_plus', 'rare', 'rare_plus',
        'elite', 'elite_plus', 'legendary', 'legendary_plus',
        'mythic', 'mythic_plus',
        'ascended', 'ascended_plus',
        'ascended_1_star', 'ascended_2_stars', 'ascended_3_stars',
        'ascended_4_stars', 'ascended_5_stars',
    ];

    public function store(Request $request): Response
    {
        $data = $request->validate($this->validationRules(['hero_id' => ['required', 'integer', Rule::exists('heroes', 'id')->where('is_active', true)]]));

        $hero = Hero::with('tier')->findOrFail($data['hero_id']);
        $equipmentFields = $this->resolveEquipmentFields($data, $hero);

        if ($data['level'] > $hero->tier->max_level) {
            $data['level'] = $hero->tier->max_level;
        }

        $userHero = $this->active->get()->userHeroes()->updateOrCreate(
            ['hero_id' => $data['hero_id']],
            [
                'level' => $data['level'],
                'ascension_code' => $data['ascension_code'] ?? null,
                'ascension_value' => $this->ascensionValue($data['ascension_code'] ?? null),
                'signature_item' => $data['signature_item'] ?? 0,
                'furniture' => $data['furniture'] ?? 0,
                'engraving' => $data['engraving'] ?? 0,
                ...$this->mythicEquipmentSlotFields($data),
                ...$equipmentFields,
                'notes' => $data['notes'] ?? null,
            ]
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'user_hero' => $userHero]);
        }

        return back();
    }

    public function update(Request $request, UserHero $userHero): Response
    {
        abort_unless($userHero->game_account_id === $this->active->get()->id, 403);

        $data = $request->validate($this->validationRules());

        $hero = $userHero->hero()->with('tier')->first();
        $equipmentFields = $this->resolveEquipmentFields($data, $hero);

        if ($hero && $data['level'] > $hero->tier->max_level) {
            $data['level'] = $hero->tier->max_level;
        }

        $userHero->update([
            'level' => $data['level'],
            'ascension_code' => $data['ascension_code'] ?? null,
            'ascension_value' => $this->ascensionValue($data['ascension_code'] ?? null),
            'signature_item' => $data['signature_item'] ?? 0,
            'furniture' => $data['furniture'] ?? 0,
            'engraving' => $data['engraving'] ?? 0,
            ...$this->mythicEquipmentSlotFields($data),
            ...$equipmentFields,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'user_hero' => $userHero->fresh()]);
        }

        return back();
    }

    private function validationRules(array $extra = []): array
    {
        $equipmentIdRule = ['nullable', 'integer', Rule::exists('equipments', 'id')->where('is_active', true)];
        $equipmentLevelRule = ['nullable', 'integer', 'min:0', 'max:5'];

        return [
            ...$extra,
            'level' => ['required', 'integer', 'min:1', 'max:240'],
            'ascension_code' => ['nullable', 'string', Rule::in(self::ASCENSION_CODES)],
            'signature_item' => ['nullable', 'integer', 'min:0', 'max:40'],
            'furniture' => ['nullable', 'integer', 'min:0', 'max:9'],
            'engraving' => ['nullable', 'integer', 'min:0', 'max:100'],
            'weapon_equipment_id' => $equipmentIdRule,
            'weapon_equipment_level' => $equipmentLevelRule,
            'helmet_equipment_id' => $equipmentIdRule,
            'helmet_equipment_level' => $equipmentLevelRule,
            'chest_equipment_id' => $equipmentIdRule,
            'chest_equipment_level' => $equipmentLevelRule,
            'boots_equipment_id' => $equipmentIdRule,
            'boots_equipment_level' => $equipmentLevelRule,
            'artifact_id' => ['nullable', 'integer', Rule::exists('artifacts', 'id')->where('is_active', true)],
            'artifact_star_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'collection_id' => ['nullable', 'integer', Rule::exists('collections', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Normaliza os 4 slots de Equipamentos Míticos. Quando há equipment_id,
     * valida que o equipment é da slot correta e clampa o level a max_upgrade_level.
     * Sem id, ambos viram null (slot vazio).
     *
     * @return array<string, int|null>
     */
    private function mythicEquipmentSlotFields(array $data): array
    {
        $out = [];
        foreach (Equipment::SLOTS as $slot) {
            $id = $data[$slot.'_equipment_id'] ?? null;
            $level = $data[$slot.'_equipment_level'] ?? null;

            if ($id === null) {
                $out[$slot.'_equipment_id'] = null;
                $out[$slot.'_equipment_level'] = 0;

                continue;
            }

            $equipment = Equipment::find($id);
            if (! $equipment || $equipment->slot !== $slot) {
                // ID inválido pro slot — descarta silenciosamente
                $out[$slot.'_equipment_id'] = null;
                $out[$slot.'_equipment_level'] = 0;

                continue;
            }

            $clampedLevel = max(0, min((int) ($level ?? 0), $equipment->max_upgrade_level));
            $out[$slot.'_equipment_id'] = $equipment->id;
            $out[$slot.'_equipment_level'] = $clampedLevel;
        }

        return $out;
    }

    /**
     * Coleção e Artefato compartilham o mesmo slot — apenas um pode estar equipado.
     * Quando ambos vêm preenchidos, prioriza Coleção (regra do jogo: Coleção sobrescreve Artefato).
     *
     * @return array{artifact_id: int|null, artifact_star_level: int|null, collection_id: int|null}
     */
    private function resolveEquipmentFields(array $data, ?Hero $hero): array
    {
        $collectionId = $data['collection_id'] ?? null;

        if ($collectionId !== null) {
            $exists = Collection::where('id', $collectionId)->where('is_active', true)->exists();
            if ($exists) {
                return ['artifact_id' => null, 'artifact_star_level' => null, 'collection_id' => $collectionId];
            }
        }

        $artifactFields = $this->resolveArtifactFields($data, $hero);

        return [...$artifactFields, 'collection_id' => null];
    }

    /**
     * @return array{artifact_id: int|null, artifact_star_level: int|null}
     */
    private function resolveArtifactFields(array $data, ?Hero $hero): array
    {
        $artifactId = $data['artifact_id'] ?? null;

        if ($artifactId === null) {
            return ['artifact_id' => null, 'artifact_star_level' => null];
        }

        $artifact = Artifact::find($artifactId);
        if (! $artifact) {
            return ['artifact_id' => null, 'artifact_star_level' => null];
        }

        // Class artifacts só podem ser equipados pela classe correspondente
        if ($artifact->kind === Artifact::KIND_CLASS && $hero && $artifact->hero_class_id !== $hero->hero_class_id) {
            return ['artifact_id' => null, 'artifact_star_level' => null];
        }

        $starLevel = $data['artifact_star_level'] ?? 1;
        $starLevel = max(1, min((int) $starLevel, $artifact->max_stars));

        return [
            'artifact_id' => $artifact->id,
            'artifact_star_level' => $starLevel,
        ];
    }

    public function destroy(Request $request, UserHero $userHero): Response
    {
        abort_unless($userHero->game_account_id === $this->active->get()->id, 403);

        $userHero->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    /**
     * Atualiza os 35 stats do roster (HP/ATK/DEF + extras). Mesma lista de
     * campos que HeroStat. hp/atk/def são integer; demais aceitam decimais.
     */
    public function updateStats(Request $request, UserHero $userHero): JsonResponse
    {
        abort_unless($userHero->game_account_id === $this->active->get()->id, 403);

        $integerFields = ['hp', 'attack', 'defense'];
        $rules = [];
        foreach (UserHero::STAT_FIELDS as $f) {
            $type = in_array($f, $integerFields, true) ? 'integer' : 'numeric';
            $max = match ($f) {
                'hp' => 9_999_999_999,
                'attack', 'defense' => 999_999_999,
                default => 99_999_999.99,
            };
            $rules[$f] = ['nullable', $type, 'min:0', "max:{$max}"];
        }

        $validated = $request->validate($rules);

        $data = [];
        foreach (UserHero::STAT_FIELDS as $f) {
            $data[$f] = $validated[$f] ?? null;
        }

        $userHero->update($data);

        return response()->json(['ok' => true, 'userHero' => $userHero->fresh()]);
    }

    private function ascensionValue(?string $code): int
    {
        if ($code === null) {
            return 0;
        }

        $index = array_search($code, self::ASCENSION_CODES, true);

        return $index === false ? 0 : $index;
    }
}
