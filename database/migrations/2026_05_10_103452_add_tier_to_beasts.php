<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beasts', function (Blueprint $table) {
            $table->string('tier', 4)->nullable()->after('agility_buff');
            $table->text('tier_note')->nullable()->after('tier');
            $table->index('tier');
        });
    }

    public function down(): void
    {
        Schema::table('beasts', function (Blueprint $table) {
            $table->dropIndex(['tier']);
            $table->dropColumn(['tier', 'tier_note']);
        });
    }
};
