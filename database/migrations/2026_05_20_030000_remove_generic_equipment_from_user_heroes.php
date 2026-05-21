<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove o sistema legado de equipamento genérico T1-T6 do UserHero.
 *
 * O jogo AFK Arena não tem "tier de equipamento" genérico — equipamentos são
 * itens nomeados (Gaius/Ancelot/Místico). Esses 8 campos eram um modelo
 * placeholder e nunca refletiram a mecânica real. Agora que os equipamentos
 * míticos (`equipments` table + slots em UserHero) estão funcionando, esses
 * campos viram ruído.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_heroes', function (Blueprint $table) {
            $table->dropColumn([
                'weapon_tier', 'weapon_level',
                'helmet_tier', 'helmet_level',
                'chest_tier', 'chest_level',
                'boots_tier', 'boots_level',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('user_heroes', function (Blueprint $table) {
            $table->string('weapon_tier', 4)->nullable()->after('engraving');
            $table->unsignedTinyInteger('weapon_level')->nullable()->after('weapon_tier');
            $table->string('helmet_tier', 4)->nullable()->after('weapon_level');
            $table->unsignedTinyInteger('helmet_level')->nullable()->after('helmet_tier');
            $table->string('chest_tier', 4)->nullable()->after('helmet_level');
            $table->unsignedTinyInteger('chest_level')->nullable()->after('chest_tier');
            $table->string('boots_tier', 4)->nullable()->after('chest_level');
            $table->unsignedTinyInteger('boots_level')->nullable()->after('boots_tier');
        });
    }
};
