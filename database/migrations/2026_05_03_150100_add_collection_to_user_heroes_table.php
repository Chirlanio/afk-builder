<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_heroes', function (Blueprint $table) {
            $table->foreignId('collection_id')->nullable()->after('artifact_star_level')->constrained('collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_heroes', function (Blueprint $table) {
            $table->dropForeign(['collection_id']);
            $table->dropColumn('collection_id');
        });
    }
};
