<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use App\Models\UserResource;
use App\Services\Optimization\InvestmentPlanner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RosterPlannerController extends Controller
{
    public function index(Request $request, InvestmentPlanner $planner): Response
    {
        $user = $request->user();

        $data = $request->validate([
            'formation_id' => ['nullable', 'integer', 'exists:formations,id'],
            'top' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        $formation = null;
        if (! empty($data['formation_id'])) {
            $candidate = Formation::find($data['formation_id']);
            if ($candidate && $candidate->user_id === $user->id) {
                $formation = $candidate;
            }
        }

        $topN = $data['top'] ?? 12;

        // Carrega recursos (cria registro vazio se ainda não existir, simplifica frontend)
        $resources = $user->resources;
        if ($resources === null) {
            $resources = UserResource::create(['user_id' => $user->id]);
        }

        $suggestions = $planner->plan($user, $formation, $topN, $resources);

        // Lista de formations do user (pra dropdown de filtro)
        $userFormations = $user->formations()
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'teams_count'])
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'teams_count' => $f->teams_count,
            ]);

        return Inertia::render('roster/planner', [
            'suggestions' => $suggestions,
            'formations' => $userFormations,
            'selectedFormationId' => $formation?->id,
            'rosterSize' => $user->userHeroes()->count(),
            'topN' => $topN,
            'resources' => $resources->only(UserResource::FIELDS),
        ]);
    }
}
