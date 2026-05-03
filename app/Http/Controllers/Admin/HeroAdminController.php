<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
