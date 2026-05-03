<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    public function index(): Response
    {
        $guides = Guide::orderBy('display_order')
            ->orderBy('title')
            ->get(['id', 'slug', 'title', 'title_pt', 'category', 'summary', 'summary_pt'])
            ->groupBy('category');

        $categories = collect(Guide::CATEGORY_LABELS)
            ->map(fn ($label, $code) => [
                'code' => $code,
                'label' => $label,
                'guides' => $guides->get($code, collect())->values(),
            ])
            ->values();

        return Inertia::render('guides/index', [
            'categories' => $categories,
        ]);
    }

    public function show(string $slug): Response
    {
        $guide = Guide::where('slug', $slug)->firstOrFail();

        return Inertia::render('guides/show', [
            'guide' => $guide,
            'categoryLabel' => Guide::CATEGORY_LABELS[$guide->category] ?? $guide->category,
        ]);
    }
}
