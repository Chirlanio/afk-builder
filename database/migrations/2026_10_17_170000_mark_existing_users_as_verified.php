<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A verificação de e-mail passou a ser exigida (User implementa
     * MustVerifyEmail e as rotas do app usam o middleware `verified`).
     * Contas criadas antes disso nunca receberam o link — marcá-las como
     * verificadas evita trancar quem já usava o app. Só afeta quem existe
     * no momento do deploy; cadastros novos passam pela confirmação.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Irreversível: não há como distinguir quem foi marcado aqui de quem
        // confirmou o e-mail de verdade.
    }
};
