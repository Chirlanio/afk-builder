<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 1. "joyous-reamer" é erro de digitação da página Beasts da wiki; a besta é
     *    "Joyous Roamer" (já cadastrada com tier e buffs). Equipes que usem a
     *    errada passam para a correta e a errada sai. O sync não a recria mais
     *    (SyncBeastsCommand::NAME_FIXES).
     * 2. Reclassifica as capacidades com as regras ajustadas (Brutus, Mortas...).
     *    Só roda quando há heróis — em banco novo/testes não há o que classificar.
     */
    public function up(): void
    {
        $wrong = DB::table('beasts')->where('slug', 'joyous-reamer')->value('id');
        $right = DB::table('beasts')->where('slug', 'joyous-roamer')->value('id');

        if ($wrong !== null && $right !== null) {
            DB::table('formation_teams')->where('beast_id', $wrong)->update(['beast_id' => $right]);
            DB::table('beasts')->where('id', $wrong)->delete();
        } elseif ($wrong !== null) {
            DB::table('beasts')->where('id', $wrong)->update(['slug' => 'joyous-roamer', 'name' => 'Joyous Roamer']);
        }

        if (DB::table('heroes')->exists()) {
            Artisan::call('heroes:classify-capabilities');
        }
    }

    public function down(): void
    {
        // Correção de dados: sem volta.
    }
};
