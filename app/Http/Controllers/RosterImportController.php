<?php

namespace App\Http\Controllers;

use App\Models\Hero;
use App\Services\ActiveGameAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RosterImportController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    public function index(): Response
    {
        $heroes = Hero::select('id', 'name', 'thumbnail_url', 'faction_id')
            ->where('is_active', true)
            ->with('faction:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('roster/import', [
            'heroes' => $heroes,
        ]);
    }

    /**
     * Grava os níveis lidos via OCR. Heróis já no roster só têm o nível
     * atualizado — os demais atributos (ascensão, SI, móveis...) ficam intactos.
     * O nível é limitado ao max_level do tier do herói, como no cadastro manual.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'heroes' => ['required', 'array', 'min:1', 'max:300'],
            'heroes.*.hero_id' => ['required', 'integer', 'distinct', Rule::exists('heroes', 'id')->where('is_active', true)],
            'heroes.*.level' => ['required', 'integer', 'min:1', 'max:240'],
        ]);

        $account = $this->active->get();
        $heroes = Hero::with('tier')
            ->whereIn('id', array_column($data['heroes'], 'hero_id'))
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($data, $account, $heroes) {
            foreach ($data['heroes'] as $row) {
                $maxLevel = $heroes[$row['hero_id']]->tier?->max_level;
                $level = $maxLevel ? min($row['level'], $maxLevel) : $row['level'];

                $account->userHeroes()->updateOrCreate(
                    ['hero_id' => $row['hero_id']],
                    ['level' => $level],
                );
            }
        });

        return redirect()->route('heroes.index')
            ->with('success', trans_choice(':count herói importado.|:count heróis importados.', count($data['heroes'])));
    }
}
