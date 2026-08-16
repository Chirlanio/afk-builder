<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use App\Models\UserResource;
use App\Services\ActiveGameAccount;
use App\Services\Optimization\InvestmentPlanner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RosterPlannerController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    public function index(Request $request, InvestmentPlanner $planner): Response
    {
        $account = $this->active->get();

        $data = $request->validate([
            'formation_id' => ['nullable', 'integer', 'exists:formations,id'],
            'top' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        $formation = null;
        if (! empty($data['formation_id'])) {
            $candidate = Formation::find($data['formation_id']);
            if ($candidate && $candidate->game_account_id === $account->id) {
                $formation = $candidate;
            }
        }

        $topN = $data['top'] ?? 12;

        // Carrega recursos (cria registro vazio se ainda não existir, simplifica frontend)
        $resources = $account->resources;
        if ($resources === null) {
            $resources = $account->resources()->create([]);
        }

        $suggestions = $planner->plan($account, $formation, $topN, $resources);

        // Lista de formations da conta ativa (pra dropdown de filtro)
        $accountFormations = $account->formations()
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'teams_count'])
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'teams_count' => $f->teams_count,
            ]);

        return Inertia::render('roster/planner', [
            'suggestions' => $suggestions,
            'formations' => $accountFormations,
            'selectedFormationId' => $formation?->id,
            'rosterSize' => $account->userHeroes()->count(),
            'topN' => $topN,
            'resources' => $resources->only(UserResource::FIELDS),
        ]);
    }
}
