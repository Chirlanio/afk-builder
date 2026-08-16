<?php

namespace App\Http\Controllers;

use App\Models\UserResource;
use App\Services\ActiveGameAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserResourceController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    /**
     * Atualiza (ou cria via updateOrCreate) os recursos da conta ativa.
     * Aceita qualquer subset de UserResource::FIELDS. Campos ausentes preservam
     * o valor atual.
     */
    public function update(Request $request): JsonResponse
    {
        $rules = [];
        foreach (UserResource::FIELDS as $field) {
            $rules[$field] = ['nullable', 'integer', 'min:0', 'max:99999999'];
        }
        $data = $request->validate($rules);

        $payload = array_filter(
            $data,
            fn ($value) => $value !== null,
        );

        $account = $this->active->get();
        $resources = UserResource::updateOrCreate(
            ['game_account_id' => $account->id],
            $payload,
        );

        return response()->json([
            'resources' => $resources->only(UserResource::FIELDS),
        ]);
    }
}
