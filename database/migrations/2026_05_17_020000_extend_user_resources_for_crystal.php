<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_resources', function (Blueprint $table) {
            // Recursos do Cristal Ressonante
            $table->unsignedInteger('revigorating_essences')->default(0)->after('engraving_scrolls');
            // Ouro e EXP de herói (também usados em upgrades de cristal)
            $table->unsignedBigInteger('gold')->default(0)->after('revigorating_essences');
            $table->unsignedBigInteger('hero_exp')->default(0)->after('gold');
            // Essência de herói (pó) — consumida no último fortalecimento de cada nível
            $table->unsignedInteger('hero_essence')->default(0)->after('hero_exp');
        });
    }

    public function down(): void
    {
        Schema::table('user_resources', function (Blueprint $table) {
            $table->dropColumn(['revigorating_essences', 'gold', 'hero_exp', 'hero_essence']);
        });
    }
};
