<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Faction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'faction' => ['nullable', 'string'],
            'slot' => ['nullable', 'string'],
            'hero_type' => ['nullable', 'string'],
            'quality_tier' => ['nullable', 'string'],
            'set_code' => ['nullable', 'string'],
        ]);

        $query = Equipment::with('faction:id,code,name_pt')
            ->where('is_active', true);

        if (! empty($data['faction'])) {
            $query->whereHas('faction', fn ($q) => $q->where('code', $data['faction']));
        }
        if (! empty($data['slot'])) {
            $query->where('slot', $data['slot']);
        }
        if (! empty($data['hero_type'])) {
            $query->where('hero_type', $data['hero_type']);
        }
        if (! empty($data['quality_tier'])) {
            $query->where('quality_tier', $data['quality_tier']);
        }
        if (! empty($data['set_code'])) {
            $query->where('set_code', $data['set_code']);
        }

        $equipments = $query->orderBy('set_code')
            ->orderBy('slot')
            ->orderBy('quality_tier')
            ->get()
            ->map(fn (Equipment $e) => [
                'id' => $e->id,
                'slug' => $e->slug,
                'name' => $e->name,
                'name_pt' => $e->name_pt,
                'set_code' => $e->set_code,
                'slot' => $e->slot,
                'material' => $e->material,
                'hero_type' => $e->hero_type,
                'faction_code' => $e->faction?->code,
                'faction_name_pt' => $e->faction?->name_pt,
                'quality_tier' => $e->quality_tier,
                'power_rating' => $e->power_rating,
                'stats' => $e->stats,
                'set_bonuses' => $e->set_bonuses,
                'max_upgrade_level' => $e->max_upgrade_level,
                'upgrade_percent_per_level' => $e->upgrade_percent_per_level,
            ]);

        $factions = Faction::orderBy('display_order')
            ->get(['code', 'name_pt'])
            ->map(fn ($f) => ['code' => $f->code, 'name_pt' => $f->name_pt]);

        return Inertia::render('equipments/index', [
            'equipments' => $equipments,
            'factions' => $factions,
            'filters' => [
                'faction' => $data['faction'] ?? null,
                'slot' => $data['slot'] ?? null,
                'hero_type' => $data['hero_type'] ?? null,
                'quality_tier' => $data['quality_tier'] ?? null,
                'set_code' => $data['set_code'] ?? null,
            ],
        ]);
    }
}
