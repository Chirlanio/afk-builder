<?php

namespace App\Http\Controllers;

use App\Models\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserResourceController extends Controller
{
    /**
     * Atualiza (ou cria via updateOrCreate) os recursos do user logado.
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

        $user = $request->user();
        $resources = UserResource::updateOrCreate(
            ['user_id' => $user->id],
            $payload,
        );

        return response()->json([
            'resources' => $resources->only(UserResource::FIELDS),
        ]);
    }
}
