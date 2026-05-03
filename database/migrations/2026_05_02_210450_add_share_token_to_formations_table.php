<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->string('share_token', 40)->nullable()->unique()->after('total_score');
        });
    }

    public function down(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
