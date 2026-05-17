<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_resonating_crystals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Nível atual do cristal (default 240 — quando a UI muda do pentagrama para barra de progresso).
            // Pré-upgrade (5 heroes ainda não no 240) o nível é derivado do 5º maior em runtime — não persistido aqui.
            $table->unsignedSmallInteger('level')->default(240);

            // Progresso de fortalecimento no nível atual (0-9). Quando chega a 10 e há custo,
            // o controller incrementa `level` e zera de volta para 0.
            $table->unsignedTinyInteger('strengthen_progress')->default(0);

            // Quantos slots de cristal o user destravou via Essências Revigorantes.
            // Pentagrama inicial = 5; cresce conforme essências gastas.
            $table->unsignedSmallInteger('slots_unlocked')->default(5);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_resonating_crystals');
    }
};
