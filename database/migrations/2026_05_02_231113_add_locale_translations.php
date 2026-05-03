<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->default('en')->after('is_admin');
        });

        Schema::table('heroes', function (Blueprint $table) {
            $table->text('lore_pt')->nullable()->after('lore');
        });

        Schema::table('hero_abilities', function (Blueprint $table) {
            $table->string('name_pt')->nullable()->after('name');
            $table->text('description_pt')->nullable()->after('description');
        });

        Schema::table('hero_furniture_abilities', function (Blueprint $table) {
            $table->string('name_pt')->nullable()->after('name');
            $table->text('f3_description_pt')->nullable()->after('f3_description');
            $table->text('f9_description_pt')->nullable()->after('f9_description');
        });

        Schema::table('guides', function (Blueprint $table) {
            $table->string('title_pt')->nullable()->after('title');
            $table->string('summary_pt')->nullable()->after('summary');
            $table->longText('content_pt')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
        Schema::table('heroes', function (Blueprint $table) {
            $table->dropColumn('lore_pt');
        });
        Schema::table('hero_abilities', function (Blueprint $table) {
            $table->dropColumn(['name_pt', 'description_pt']);
        });
        Schema::table('hero_furniture_abilities', function (Blueprint $table) {
            $table->dropColumn(['name_pt', 'f3_description_pt', 'f9_description_pt']);
        });
        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn(['title_pt', 'summary_pt', 'content_pt']);
        });
    }
};
