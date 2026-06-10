<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->string('category_en')->nullable()->after('category');
            $table->string('subcategory_en')->nullable()->after('subcategory');
            $table->string('species_type_en')->nullable()->after('species_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->dropColumn(['category_en', 'subcategory_en', 'species_type_en']);
        });
    }
};
