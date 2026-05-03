<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_heroes', function (Blueprint $table) {
            $table->foreignId('artifact_id')->nullable()->after('engraving')->constrained('artifacts')->nullOnDelete();
            $table->unsignedTinyInteger('artifact_star_level')->nullable()->after('artifact_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_heroes', function (Blueprint $table) {
            $table->dropForeign(['artifact_id']);
            $table->dropColumn(['artifact_id', 'artifact_star_level']);
        });
    }
};
