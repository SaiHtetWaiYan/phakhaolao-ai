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
            $table->text('botanical_description_en')->nullable()->after('botanical_description');
            $table->text('topographic_description_en')->nullable()->after('topographic_description');
            $table->text('landscape_description_en')->nullable()->after('landscape_description');
            $table->text('observation_description_en')->nullable()->after('observation_description');
            $table->text('conservation_note_en')->nullable()->after('conservation_note');
            $table->text('endemism_description_en')->nullable()->after('endemism_description');
            $table->text('use_description_en')->nullable()->after('use_description');
            $table->text('cultivation_info_en')->nullable()->after('cultivation_info');
            $table->text('market_data_en')->nullable()->after('market_data');
            $table->text('management_info_en')->nullable()->after('management_info');
            $table->text('nutrition_description_en')->nullable()->after('nutrition_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->dropColumn([
                'botanical_description_en',
                'topographic_description_en',
                'landscape_description_en',
                'observation_description_en',
                'conservation_note_en',
                'endemism_description_en',
                'use_description_en',
                'cultivation_info_en',
                'market_data_en',
                'management_info_en',
                'nutrition_description_en',
            ]);
        });
    }
};
