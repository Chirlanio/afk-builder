<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HeroAdminController extends Controller
{
    public function pendingIndex(Request $request): Response
    {
        $heroes = Hero::query()
            ->with(['faction:id,code,name_pt,color_hex', 'heroClass:id,code,name_pt', 'tier:id,code,name_pt'])
            ->where('pending_review', true)
            ->orderBy('created_at')
            ->get([
                'id', 'slug', 'name', 'title', 'faction_id', 'hero_class_id', 'tier_id',
                'gender', 'role_label', 'portrait_url', 'thumbnail_url', 'source_url',
                'is_active', 'pending_review', 'created_at',
            ]);

        return Inertia::render('admin/heroes/pending', [
            'heroes' => $heroes,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/heroes/create', [
            'factions' => Faction::orderBy('display_order')
                ->get(['id', 'code', 'name_pt', 'color_hex', 'display_order']),
            'heroClasses' => HeroClass::orderBy('display_order')
                ->get(['id', 'code', 'name_pt', 'display_order']),
            'tiers' => Tier::orderBy('display_order')
                ->get(['id', 'code', 'name_pt', 'max_level', 'display_order']),
        ]);
    }

    public function storeManual(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9-]+$/', Rule::unique('heroes', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'faction_id' => ['required', 'integer', Rule::exists('factions', 'id')],
            'hero_class_id' => ['required', 'integer', Rule::exists('hero_classes', 'id')],
            'tier_id' => ['required', 'integer', Rule::exists('tiers', 'id')],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'role_label' => ['nullable', 'string', 'max:255'],
            'portrait_url' => ['nullable', 'url', 'max:500'],
            'source_url' => ['nullable', 'url', 'max:500'],
        ]);

        Hero::create([
            ...$data,
            'is_active' => true,
            'pending_review' => false,
        ]);

        return redirect()
            ->route('heroes.index')
            ->with('flash', "Herói '{$data['name']}' criado.");
    }

    public function approve(Hero $hero): RedirectResponse
    {
        $hero->update([
            'pending_review' => false,
            'is_active' => true,
        ]);

        return back()->with('flash', "Herói '{$hero->name}' aprovado.");
    }

    public function reject(Hero $hero): RedirectResponse
    {
        $hero->update([
            'pending_review' => false,
            'is_active' => false,
        ]);

        return back()->with('flash', "Herói '{$hero->name}' rejeitado.");
    }
}
