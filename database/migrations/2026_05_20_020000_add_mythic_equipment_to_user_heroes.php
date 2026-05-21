<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona slots de Equipamento Mítico ao UserHero. 4 slots (weapon/helmet/
 * chest/boots) com FK pro equipment + nível de upgrade individual (0-5).
 *
 * Coexiste com o sistema de tier genérico (T1-T6 + level) que continua sendo
 * usado pra equipamentos comuns. Quando um item mítico está equipado, ele
 * SUBSTITUI a contribuição do tier genérico naquele slot (resolvido em runtime).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_heroes', function (Blueprint $table) {
            $table->foreignId('weapon_equipment_id')->nullable()->after('boots_level')
                ->constrained('equipments')->nullOnDelete();
            $table->unsignedTinyInteger('weapon_equipment_level')->default(0)->after('weapon_equipment_id');

            $table->foreignId('helmet_equipment_id')->nullable()->after('weapon_equipment_level')
                ->constrained('equipments')->nullOnDelete();
            $table->unsignedTinyInteger('helmet_equipment_level')->default(0)->after('helmet_equipment_id');

            $table->foreignId('chest_equipment_id')->nullable()->after('helmet_equipment_level')
                ->constrained('equipments')->nullOnDelete();
            $table->unsignedTinyInteger('chest_equipment_level')->default(0)->after('chest_equipment_id');

            $table->foreignId('boots_equipment_id')->nullable()->after('chest_equipment_level')
                ->constrained('equipments')->nullOnDelete();
            $table->unsignedTinyInteger('boots_equipment_level')->default(0)->after('boots_equipment_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_heroes', function (Blueprint $table) {
            $table->dropForeign(['weapon_equipment_id']);
            $table->dropForeign(['helmet_equipment_id']);
            $table->dropForeign(['chest_equipment_id']);
            $table->dropForeign(['boots_equipment_id']);
            $table->dropColumn([
                'weapon_equipment_id', 'weapon_equipment_level',
                'helmet_equipment_id', 'helmet_equipment_level',
                'chest_equipment_id', 'chest_equipment_level',
                'boots_equipment_id', 'boots_equipment_level',
            ]);
        });
    }
};
