<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use App\Models\Guide;
use App\Models\Hero;
use App\Models\UserTierList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public const MAX_PER_CATEGORY = 5;

    public const MIN_QUERY_LENGTH = 2;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = trim($data['q'] ?? '');

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return response()->json([
                'query' => $query,
                'results' => [
                    'heroes' => [],
                    'formations' => [],
                    'tier_lists' => [],
                    'guides' => [],
                ],
            ]);
        }

        $user = $request->user();
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

        // Heroes — catálogo público (qualquer user vê os mesmos)
        $heroes = Hero::query()
            ->where('is_active', true)
            ->where('pending_review', false)
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })
            ->orderBy('name')
            ->limit(self::MAX_PER_CATEGORY)
            ->get(['id', 'slug', 'name', 'title', 'portrait_url'])
            ->map(fn (Hero $h) => [
                'id' => $h->id,
                'label' => $h->name,
                'sublabel' => $h->title,
                'url' => "/heroes?open={$h->slug}",
                'image_url' => $h->portrait_url,
            ]);

        // Formations — só do user
        $formations = $user->defaultGameAccount->formations()
            ->with('challengeType:id,name_pt')
            ->where('name', 'like', $like)
            ->orderByDesc('updated_at')
            ->limit(self::MAX_PER_CATEGORY)
            ->get(['id', 'name', 'challenge_type_id'])
            ->map(fn (Formation $f) => [
                'id' => $f->id,
                'label' => $f->name,
                'sublabel' => $f->challengeType?->name_pt,
                'url' => "/formations/{$f->id}/edit",
                'image_url' => null,
            ]);

        // Tier lists — só do user
        $tierLists = $user->defaultGameAccount->tierLists()
            ->where('name', 'like', $like)
            ->orderByDesc('updated_at')
            ->limit(self::MAX_PER_CATEGORY)
            ->get(['id', 'name'])
            ->map(fn (UserTierList $t) => [
                'id' => $t->id,
                'label' => $t->name,
                'sublabel' => null,
                'url' => "/tier-lists/{$t->id}/edit",
                'image_url' => null,
            ]);

        // Guides — públicos (todos do banco; não há flag de publicação)
        $guides = Guide::query()
            ->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('title_pt', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })
            ->orderBy('title_pt')
            ->limit(self::MAX_PER_CATEGORY)
            ->get(['id', 'slug', 'title', 'title_pt'])
            ->map(fn (Guide $g) => [
                'id' => $g->id,
                'label' => $g->title_pt ?? $g->title,
                'sublabel' => null,
                'url' => "/guides/{$g->slug}",
                'image_url' => null,
            ]);

        return response()->json([
            'query' => $query,
            'results' => [
                'heroes' => $heroes,
                'formations' => $formations,
                'tier_lists' => $tierLists,
                'guides' => $guides,
            ],
        ]);
    }
}
