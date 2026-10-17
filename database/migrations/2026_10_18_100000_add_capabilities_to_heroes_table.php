<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perfil de capacidades do herói (o que ele faz numa luta), usado pelo
     * CapabilityCoverageScorer:
     *  - capabilities: gerado pelo HeroCapabilityClassifier a partir das
     *    habilidades + role_label — {tag: força 1..2}. Regerado a cada classificação.
     *  - capabilities_manual: correções do admin — {"add": {tag: força}, "remove": [tag]}.
     *    Nunca é tocado pelo classificador.
     */
    public function up(): void
    {
        Schema::table('heroes', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('role_label');
            $table->json('capabilities_manual')->nullable()->after('capabilities');
        });
    }

    public function down(): void
    {
        Schema::table('heroes', function (Blueprint $table) {
            $table->dropColumn(['capabilities', 'capabilities_manual']);
        });
    }
};
