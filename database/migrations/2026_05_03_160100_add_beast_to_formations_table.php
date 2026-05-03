<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->foreignId('beast_id')->nullable()->after('share_token')->constrained('beasts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->dropForeign(['beast_id']);
            $table->dropColumn('beast_id');
        });
    }
};
