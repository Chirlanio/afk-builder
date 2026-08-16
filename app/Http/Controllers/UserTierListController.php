<?php

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\UserTierList;
use App\Models\UserTierListItem;
use App\Models\UserTierListRow;
use App\Services\ActiveGameAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserTierListController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    private const DEFAULT_TIERS = [
        ['label' => 'S+', 'color' => 'rose'],
        ['label' => 'S',  'color' => 'orange'],
        ['label' => 'A',  'color' => 'amber'],
        ['label' => 'B',  'color' => 'emerald'],
        ['label' => 'C',  'color' => 'sky'],
    ];

    public function index(Request $request): Response
    {
        $lists = $this->active->get()
            ->tierLists()
            ->withCount('rows')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'description', 'share_token', 'is_template', 'updated_at']);

        return Inertia::render('tier-lists/index', [
            'tierLists' => $lists,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('tier-lists/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $account = $this->active->get();

        $list = DB::transaction(function () use ($account, $data) {
            $list = $account->tierLists()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            foreach (self::DEFAULT_TIERS as $i => $tier) {
                $list->rows()->create([
                    'label' => $tier['label'],
                    'color' => $tier['color'],
                    'position' => $i,
                ]);
            }

            return $list;
        });

        return redirect()->route('tier-lists.edit', $list);
    }

    public function edit(Request $request, UserTierList $tierList): Response
    {
        abort_unless($tierList->game_account_id === $this->active->get()->id, 403);

        $tierList->load(['rows.items.hero:id,slug,name,faction_id,hero_class_id,portrait_url']);

        $heroes = Hero::query()
            ->where('is_active', true)
            ->where('pending_review', false)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'faction_id', 'hero_class_id', 'type', 'portrait_url']);

        return Inertia::render('tier-lists/edit', [
            'tierList' => $tierList,
            'heroes' => $heroes,
            'factions' => Faction::orderBy('display_order')->get(['id', 'code', 'name_pt', 'color_hex']),
            'heroClasses' => HeroClass::orderBy('display_order')->get(['id', 'code', 'name_pt']),
        ]);
    }

    public function update(Request $request, UserTierList $tierList): JsonResponse
    {
        abort_unless($tierList->game_account_id === $this->active->get()->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $tierList->update($data);

        return response()->json(['ok' => true, 'tierList' => $tierList->fresh()]);
    }

    public function destroy(Request $request, UserTierList $tierList): RedirectResponse
    {
        abort_unless($tierList->game_account_id === $this->active->get()->id, 403);

        $tierList->delete();

        return redirect()->route('tier-lists.index')->with('flash', 'Tier list removida.');
    }

    public function addRow(Request $request, UserTierList $tierList): JsonResponse
    {
        abort_unless($tierList->game_account_id === $this->active->get()->id, 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:16'],
            'color' => ['required', Rule::in(UserTierListRow::COLORS)],
        ]);

        $position = (int) ($tierList->rows()->max('position') ?? -1) + 1;
        $row = $tierList->rows()->create([
            'label' => $data['label'],
            'color' => $data['color'],
            'position' => $position,
        ]);

        return response()->json(['ok' => true, 'row' => $row]);
    }

    public function updateRow(Request $request, UserTierList $tierList, UserTierListRow $row): JsonResponse
    {
        abort_unless($tierList->game_account_id === $this->active->get()->id, 403);
        abort_unless($row->user_tier_list_id === $tierList->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:16'],
            'color' => ['required', Rule::in(UserTierListRow::COLORS)],
        ]);

        $row->update($data);

        return response()->json(['ok' => true, 'row' => $row->fresh()]);
    }

    public function deleteRow(Request $request, UserTierList $tierList, UserTierListRow $row): JsonResponse
    {
        abort_unless($tierList->game_account_id === $this->active->get()->id, 403);
        abort_unless($row->user_tier_list_id === $tierList->id, 404);

        $row->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Reordena os items de uma row específica de acordo com a ordem dos hero_ids enviada.
     * Items que pertencem à row mas não estão no array têm sua posição preservada após os listados.
     */
    public function reorderItems(Request $request, UserTierList $tierList, UserTierListRow $row): JsonResponse
    {
        abort_unless($tierList->game_account_id === $this->active->get()->id, 403);
        abort_unless($row->user_tier_list_id === $tierList->id, 404);

        $data = $request->validate([
            'hero_ids' => ['required', 'array', 'min:1'],
            'hero_ids.*' => ['integer'],
        ]);

        $heroIds = array_map('intval', $data['hero_ids']);

        DB::transaction(function () use ($row, $heroIds) {
            foreach ($heroIds as $position => $heroId) {
                \App\Models\UserTierListItem::where('user_tier_list_row_id', $row->id)
                    ->where('hero_id', $heroId)
                    ->update(['position' => $position]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Move um herói para uma row (ou para "pool" se row_id=null).
     * Idempotente: remove o herói de qualquer row anterior antes de inserir na nova.
     */
    public function placeHero(Request $request, UserTierList $tierList): JsonResponse
    {
        abort_unless($tierList->game_account_id === $this->active->get()->id, 403);

        $data = $request->validate([
            'hero_id' => ['required', 'integer', Rule::exists('heroes', 'id')],
            'row_id' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $rowId = $data['row_id'] ?? null;

        if ($rowId !== null) {
            $row = UserTierListRow::where('id', $rowId)
                ->where('user_tier_list_id', $tierList->id)
                ->firstOrFail();
        }

        DB::transaction(function () use ($tierList, $data, $rowId) {
            // Remove o herói de qualquer row desta lista
            $rowIds = $tierList->rows()->pluck('id');
            \App\Models\UserTierListItem::whereIn('user_tier_list_row_id', $rowIds)
                ->where('hero_id', $data['hero_id'])
                ->delete();

            if ($rowId === null) {
                return; // herói volta pro pool
            }

            $position = $data['position'] ?? (int) (\App\Models\UserTierListItem::where('user_tier_list_row_id', $rowId)->max('position') ?? -1) + 1;

            \App\Models\UserTierListItem::create([
                'user_tier_list_row_id' => $rowId,
                'hero_id' => $data['hero_id'],
                'position' => $position,
            ]);
        });

        return response()->json(['ok' => true]);
    }

    public function share(Request $request, UserTierList $tierList): JsonResponse
    {
        abort_unless($tierList->game_account_id === $this->active->get()->id, 403);

        if ($tierList->share_token === null) {
            $tierList->generateShareToken();
        }

        return response()->json([
            'ok' => true,
            'share_token' => $tierList->share_token,
            'public_url' => url('/tier/'.$tierList->share_token),
        ]);
    }

    public function unshare(Request $request, UserTierList $tierList): JsonResponse
    {
        abort_unless($tierList->game_account_id === $this->active->get()->id, 403);

        $tierList->revokeShareToken();

        return response()->json(['ok' => true]);
    }

    public function publicShow(string $token): Response
    {
        $tierList = UserTierList::where('share_token', $token)
            ->with(['rows.items.hero:id,slug,name,faction_id,portrait_url', 'user:id,name'])
            ->firstOrFail();

        return Inertia::render('tier-lists/public', [
            'tierList' => $tierList,
            'factions' => Faction::orderBy('display_order')->get(['id', 'code', 'name_pt', 'color_hex']),
        ]);
    }

    /**
     * Lista pública de templates (acessível a qualquer user autenticado).
     */
    public function templatesIndex(): Response
    {
        $templates = UserTierList::query()
            ->where('is_template', true)
            ->with('user:id,name')
            ->withCount(['rows', 'rows as items_count' => function ($q) {
                $q->join('user_tier_list_items', 'user_tier_list_rows.id', '=', 'user_tier_list_items.user_tier_list_row_id');
            }])
            ->orderByDesc('updated_at')
            ->get(['id', 'user_id', 'name', 'description', 'updated_at']);

        return Inertia::render('tier-lists/templates', [
            'templates' => $templates,
        ]);
    }

    /**
     * Toggle is_template (apenas admin). Não altera ownership: o admin é o dono do template.
     */
    public function setTemplate(Request $request, UserTierList $tierList): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);
        abort_unless($tierList->game_account_id === $this->active->get()->id, 403);

        $data = $request->validate([
            'is_template' => ['required', 'boolean'],
        ]);

        $tierList->forceFill(['is_template' => $data['is_template']])->save();

        return response()->json(['ok' => true, 'is_template' => $tierList->is_template]);
    }

    /**
     * Clona um template para o user autenticado. Copia rows + items numa transação.
     * Resultado: nova UserTierList com is_template=false e cloned_from_id apontando pro template.
     */
    public function clone(Request $request, UserTierList $tierList): RedirectResponse
    {
        abort_unless($tierList->is_template, 404);

        $account = $this->active->get();

        $copy = DB::transaction(function () use ($account, $tierList) {
            $tierList->load('rows.items');

            $copy = $account->tierLists()->create([
                'name' => $tierList->name.' (cópia)',
                'description' => $tierList->description,
                'is_template' => false,
                'cloned_from_id' => $tierList->id,
            ]);

            foreach ($tierList->rows as $row) {
                $newRow = $copy->rows()->create([
                    'label' => $row->label,
                    'color' => $row->color,
                    'position' => $row->position,
                ]);

                foreach ($row->items as $item) {
                    UserTierListItem::create([
                        'user_tier_list_row_id' => $newRow->id,
                        'hero_id' => $item->hero_id,
                        'position' => $item->position,
                    ]);
                }
            }

            return $copy;
        });

        return redirect()->route('tier-lists.edit', $copy);
    }
}
