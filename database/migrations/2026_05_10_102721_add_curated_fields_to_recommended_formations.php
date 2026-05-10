<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommended_formations', function (Blueprint $table) {
            $table->string('tier', 4)->nullable()->after('mode');
            $table->string('level_deficit', 32)->nullable()->after('tier');
            $table->unsignedTinyInteger('core_count')->default(0)->after('hero_slugs');
            $table->json('artifacts')->nullable()->after('core_count');
            $table->text('notes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('recommended_formations', function (Blueprint $table) {
            $table->dropColumn(['tier', 'level_deficit', 'core_count', 'artifacts', 'notes']);
        });
    }
};
