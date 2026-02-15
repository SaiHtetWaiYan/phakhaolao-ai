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
            $table->string('category')->nullable()->index()->after('data_collection_level');
            $table->string('subcategory')->nullable()->index()->after('category');
            $table->string('species_type')->nullable()->index()->after('subcategory');
            $table->string('national_conservation_status')->nullable()->index()->after('iucn_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->dropColumn(['category', 'subcategory', 'species_type', 'national_conservation_status']);
        });
    }
};
