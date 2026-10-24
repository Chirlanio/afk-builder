<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hero;
use App\Models\HeroCounter;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cadastro de Counters pelo admin: por herói, "counters contra quem" (ele
 * countera) e "quem o countera" (ele é o alvo).
 */
class HeroCounterAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $heroes = Hero::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'portrait_url']);

        $hero = $request->filled('hero') ? Hero::find($request->integer('hero')) : null;

        return Inertia::render('admin/counters/index', [
            'heroes' => $heroes,
            'contexts' => HeroCounter::CONTEXTS,
            'hero' => $hero?->only(['id', 'slug', 'name', 'portrait_url']),
            'counters' => $hero ? $this->list($hero->counters()->with('target:id,name,portrait_url')) : [],
            'counteredBy' => $hero ? $this->list($hero->counteredBy()->with('hero:id,name,portrait_url')) : [],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $this->ensureAllowedPair($data);

        return response()->json(['counter' => HeroCounter::create($data)], 201);
    }

    /**
     * Só força, motivo e fonte mudam: trocar o par ou o Contexto é outro Counter.
     */
    public function update(Request $request, HeroCounter $counter): JsonResponse
    {
        $editable = array_intersect_key($this->rules(), array_flip(['strength', 'reason', 'source_url']));
        $counter->update($request->validate(array_map(fn (array $rules) => ['sometimes', ...$rules], $editable)));

        return response()->json(['counter' => $counter->fresh()]);
    }

    public function destroy(HeroCounter $counter): JsonResponse
    {
        $counter->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Combinações que o domínio proíbe: o herói contra ele mesmo, entre as
     * versões do mesmo Personagem e o mesmo par repetido no mesmo Contexto.
     */
    private function ensureAllowedPair(array $data): void
    {
        $hero = Hero::findOrFail($data['hero_id']);
        $target = Hero::findOrFail($data['target_hero_id']);

        $error = match (true) {
            $hero->id === $target->id => 'Um herói não pode counterar a si mesmo.',
            $hero->characterKey() === $target->characterKey() => 'A versão base e a Desperta são o mesmo Personagem: não há Counter entre elas.',
            HeroCounter::query()
                ->where('hero_id', $hero->id)
                ->where('target_hero_id', $target->id)
                ->where('context', $data['context'])
                ->exists() => 'Este Counter já existe neste Contexto: edite o existente.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['target_hero_id' => $error]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'hero_id' => ['required', 'integer', Rule::exists('heroes', 'id')],
            'target_hero_id' => ['required', 'integer', Rule::exists('heroes', 'id')],
            'context' => ['required', Rule::in(HeroCounter::CONTEXTS)],
            'strength' => ['required', 'integer', 'min:1', 'max:'.HeroCounter::MAX_STRENGTH],
            'reason' => ['required', 'string', 'max:500'],
            'source_url' => ['nullable', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function list(HasMany $relation): array
    {
        return $relation->orderBy('context')->get()->map(fn (HeroCounter $counter) => $this->present($counter))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(HeroCounter $counter): array
    {
        return [
            'id' => $counter->id,
            'hero' => $counter->relationLoaded('hero') ? $counter->hero?->only(['id', 'name', 'portrait_url']) : null,
            'target' => $counter->relationLoaded('target') ? $counter->target?->only(['id', 'name', 'portrait_url']) : null,
            'context' => $counter->context,
            'strength' => $counter->strength,
            'reason' => $counter->reason,
            'source_url' => $counter->source_url,
        ];
    }
}
