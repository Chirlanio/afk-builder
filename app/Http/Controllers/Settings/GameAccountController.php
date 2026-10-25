<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\GameAccount;
use App\Models\Hero;
use App\Services\ActiveGameAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GameAccountController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    public function index(Request $request): Response
    {
        $accounts = $request->user()
            ->gameAccounts()
            ->with('avatarHero:id,slug,name,portrait_url')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get(['id', 'name', 'server', 'avatar_hero_id', 'is_default'])
            ->map(function (GameAccount $account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'server' => $account->server,
                    'is_default' => $account->is_default,
                    'avatar' => $account->avatarHero ? [
                        'id' => $account->avatarHero->id,
                        'slug' => $account->avatarHero->slug,
                        'name' => $account->avatarHero->name,
                        'portrait_url' => $account->avatarHero->portrait_url,
                    ] : null,
                ];
            });

        $heroes = Hero::query()
            ->available()
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'portrait_url']);

        return Inertia::render('settings/accounts', [
            'accounts' => $accounts,
            'heroes' => $heroes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedPayload($request);

        $user = $request->user();

        $account = $user->gameAccounts()->create([
            'name' => $data['name'],
            'server' => $data['server'] ?? null,
            'avatar_hero_id' => $data['avatar_hero_id'] ?? null,
        ]);

        // Se pediu pra virar default, o observer trata via `is_default=true` na update
        if (! empty($data['is_default']) && ! $account->is_default) {
            $account->update(['is_default' => true]);
        }

        return back()->with('flash', 'Conta criada.');
    }

    public function update(Request $request, GameAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $data = $this->validatedPayload($request, $account->id);

        $account->update([
            'name' => $data['name'],
            'server' => $data['server'] ?? null,
            'avatar_hero_id' => $data['avatar_hero_id'] ?? null,
        ]);

        if (! empty($data['is_default']) && ! $account->is_default) {
            $account->update(['is_default' => true]);
        }

        return back()->with('flash', 'Conta atualizada.');
    }

    public function destroy(Request $request, GameAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        // Segurança: exige pelo menos 1 conta remanescente. Deleta cascade
        // apaga o roster inteiro (user_heroes, formations, etc.) — destrutivo.
        $remaining = $request->user()->gameAccounts()->count();
        if ($remaining <= 1) {
            return back()->withErrors(['delete' => 'Você precisa de pelo menos 1 conta.']);
        }

        $account->delete();

        // Se a conta ativa era essa, reset pra default (o get() do service resolve).
        if ($this->active->get()?->id === $account->id) {
            $request->session()->forget(ActiveGameAccount::SESSION_KEY);
        }

        return back()->with('flash', 'Conta removida.');
    }

    /**
     * @return array{name: string, server: ?string, avatar_hero_id: ?int, is_default: bool}
     */
    private function validatedPayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'min:1',
                'max:60',
                Rule::unique('game_accounts', 'name')
                    ->where('user_id', $request->user()->id)
                    ->ignore($ignoreId),
            ],
            'server' => ['nullable', 'string', 'max:60'],
            'avatar_hero_id' => [
                'nullable',
                'integer',
                Rule::exists('heroes', 'id')->where('is_active', true),
            ],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }
}
