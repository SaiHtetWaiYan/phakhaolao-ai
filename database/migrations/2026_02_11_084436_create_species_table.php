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
        Schema::create('species', function (Blueprint $table) {
            $table->id();

            // Source identifier
            $table->unsignedInteger('source_id')->unique();

            // Core taxonomy
            $table->string('scientific_name')->nullable();
            $table->string('common_name_lao')->nullable();
            $table->string('common_name_english')->nullable();
            $table->string('family')->nullable();

            // Classification & status
            $table->string('iucn_status')->nullable();
            $table->string('native_status')->nullable();
            $table->string('invasiveness')->nullable();
            $table->string('data_collection_level')->nullable();
            $table->string('harvest_season')->nullable();

            // JSON columns
            $table->json('local_names')->nullable();
            $table->json('synonyms')->nullable();
            $table->json('related_species')->nullable();
            $table->json('habitat_types')->nullable();
            $table->json('use_types')->nullable();
            $table->json('nutrition')->nullable();
            $table->json('image_urls')->nullable();
            $table->json('map_urls')->nullable();
            $table->json('references')->nullable();

            // Text columns
            $table->text('botanical_description')->nullable();
            $table->text('global_distribution')->nullable();
            $table->text('lao_distribution')->nullable();
            $table->text('use_description')->nullable();
            $table->text('cultivation_info')->nullable();
            $table->text('market_data')->nullable();
            $table->text('management_info')->nullable();
            $table->text('threats')->nullable();
            $table->text('nutrition_description')->nullable();

            // Scrape control
            $table->string('scrape_status')->default('pending');
            $table->text('scrape_error')->nullable();
            $table->timestamp('scraped_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('species');
    }
};
