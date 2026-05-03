<?php

namespace App\Http\Controllers;

use App\Models\Artifact;
use App\Models\Collection;
use App\Models\Hero;
use App\Models\UserHero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UserHeroController extends Controller
{
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

        $userHero = $request->user()->userHeroes()->updateOrCreate(
            ['hero_id' => $data['hero_id']],
            [
                'level' => $data['level'],
                'ascension_code' => $data['ascension_code'] ?? null,
                'ascension_value' => $this->ascensionValue($data['ascension_code'] ?? null),
                'signature_item' => $data['signature_item'] ?? 0,
                'furniture' => $data['furniture'] ?? 0,
                'engraving' => $data['engraving'] ?? 0,
                ...$this->equipmentSlotFields($data),
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
        abort_unless($userHero->user_id === $request->user()->id, 403);

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
            ...$this->equipmentSlotFields($data),
            ...$equipmentFields,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'user_hero' => $userHero->fresh()]);
        }
        return back();
    }

    /**
     * Normaliza os 4 slots de equipamento do payload validado.
     * Tier sem level (ou vice-versa) → ambos viram null pra evitar registros parciais.
     *
     * @return array<string, string|int|null>
     */
    private function equipmentSlotFields(array $data): array
    {
        $out = [];
        foreach (UserHero::EQUIPMENT_SLOTS as $slot) {
            $tier = $data[$slot.'_tier'] ?? null;
            $level = $data[$slot.'_level'] ?? null;
            if ($tier === null || $level === null) {
                $out[$slot.'_tier'] = null;
                $out[$slot.'_level'] = null;
            } else {
                $out[$slot.'_tier'] = $tier;
                $out[$slot.'_level'] = $level;
            }
        }
        return $out;
    }

    private function validationRules(array $extra = []): array
    {
        $tierRule = ['nullable', 'string', Rule::in(UserHero::EQUIPMENT_TIERS)];
        $levelRule = ['nullable', 'integer', 'min:1', 'max:'.UserHero::EQUIPMENT_LEVEL_MAX];

        return [
            ...$extra,
            'level' => ['required', 'integer', 'min:1', 'max:240'],
            'ascension_code' => ['nullable', 'string', Rule::in(self::ASCENSION_CODES)],
            'signature_item' => ['nullable', 'integer', 'min:0', 'max:40'],
            'furniture' => ['nullable', 'integer', 'min:0', 'max:9'],
            'engraving' => ['nullable', 'integer', 'min:0', 'max:100'],
            'weapon_tier' => $tierRule,
            'weapon_level' => $levelRule,
            'helmet_tier' => $tierRule,
            'helmet_level' => $levelRule,
            'chest_tier' => $tierRule,
            'chest_level' => $levelRule,
            'boots_tier' => $tierRule,
            'boots_level' => $levelRule,
            'artifact_id' => ['nullable', 'integer', Rule::exists('artifacts', 'id')->where('is_active', true)],
            'artifact_star_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'collection_id' => ['nullable', 'integer', Rule::exists('collections', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
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
        abort_unless($userHero->user_id === $request->user()->id, 403);

        $userHero->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back();
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
