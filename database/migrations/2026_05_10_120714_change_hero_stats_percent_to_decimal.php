<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stats de crit_rate, crit_amp, accuracy, dodge e haste são exibidos em jogo
 * com decimais (ex: 25.5%, 30.7%). Mudança para decimal(8,2) permite até
 * 999999.99, mais que suficiente. Valores inteiros existentes são preservados.
 */
return new class extends Migration
{
    private const FIELDS = ['crit_rate', 'crit_amp', 'accuracy', 'dodge', 'haste'];

    public function up(): void
    {
        Schema::table('hero_stats', function (Blueprint $table) {
            foreach (self::FIELDS as $f) {
                $table->decimal($f, 8, 2)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hero_stats', function (Blueprint $table) {
            foreach (self::FIELDS as $f) {
                $table->unsignedSmallInteger($f)->nullable()->change();
            }
        });
    }
};
