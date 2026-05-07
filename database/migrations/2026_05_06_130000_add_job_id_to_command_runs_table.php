<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('command_runs', function (Blueprint $table) {
            // Sem FK pra jobs.id porque a tabela jobs é volátil (jobs são apagados ao processar).
            // Guardamos o id apenas pra suportar cancelamento (DELETE FROM jobs WHERE id = ?).
            $table->unsignedBigInteger('job_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('command_runs', function (Blueprint $table) {
            $table->dropColumn('job_id');
        });
    }
};
