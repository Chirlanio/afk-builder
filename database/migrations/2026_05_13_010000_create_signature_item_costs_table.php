<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_item_costs', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->primary();
            $table->unsignedInteger('cost')->nullable();
            $table->string('emblem_type', 16);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_item_costs');
    }
};
