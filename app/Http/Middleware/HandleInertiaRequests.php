<?php

namespace App\Http\Middleware;

use App\Models\Hero;
use App\Services\ActiveGameAccount;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly ActiveGameAccount $activeGameAccount) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();

        // Fase 38: contagem de heróis em pending_review pra admins. Query barata (count com index),
        // mas evitamos rodar pra não-admins. Usa Inertia closure (lazy) pra que partial reloads
        // não invalidem o cache do shared data desnecessariamente.
        $pendingHeroesCount = ($user && $user->is_admin)
            ? fn () => Hero::where('pending_review', true)->count()
            : null;

        $gameAccount = ($user && $user->gameAccounts()->exists())
            ? fn () => [
                'active' => $this->activeGameAccount->get()->only(['id', 'name', 'server', 'avatar_hero_id']),
                'accounts' => $user->gameAccounts()
                    ->select(['id', 'name', 'server', 'avatar_hero_id', 'is_default'])
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->get(),
            ]
            : null;

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
            ],
            'admin' => [
                'pending_heroes_count' => $pendingHeroesCount,
            ],
            'gameAccount' => $gameAccount,
        ]);
    }
}
