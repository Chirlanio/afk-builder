<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_heroes', function (Blueprint $table) {
            // 4 slots de equipamento: Arma / Capacete / Peitoral / Bota.
            // Tier: T1..T6 (Comum/Raro/Elite/Lendário/Mítico/Ascendido). Level: 1-20.
            $table->string('weapon_tier', 8)->nullable()->after('engraving');
            $table->unsignedTinyInteger('weapon_level')->nullable()->after('weapon_tier');
            $table->string('helmet_tier', 8)->nullable()->after('weapon_level');
            $table->unsignedTinyInteger('helmet_level')->nullable()->after('helmet_tier');
            $table->string('chest_tier', 8)->nullable()->after('helmet_level');
            $table->unsignedTinyInteger('chest_level')->nullable()->after('chest_tier');
            $table->string('boots_tier', 8)->nullable()->after('chest_level');
            $table->unsignedTinyInteger('boots_level')->nullable()->after('boots_tier');
        });
    }

    public function down(): void
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
};
