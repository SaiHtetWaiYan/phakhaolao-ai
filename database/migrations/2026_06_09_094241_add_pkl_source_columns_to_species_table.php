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
            $table->text('topographic_description')->nullable()->after('global_distribution');
            $table->text('landscape_description')->nullable()->after('topographic_description');
            $table->text('observation_description')->nullable()->after('landscape_description');
            $table->text('conservation_note')->nullable()->after('observation_description');
            $table->text('endemism_description')->nullable()->after('conservation_note');
            $table->json('provinces')->nullable()->after('map_urls');
            $table->json('external_links')->nullable()->after('references');
            $table->unsignedBigInteger('inaturalist_taxon_id')->nullable()->after('external_links');
            $table->unsignedInteger('gbif_taxon_key')->nullable()->after('inaturalist_taxon_id');
            $table->string('content_hash')->nullable()->index()->after('gbif_taxon_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->dropColumn([
                'topographic_description',
                'landscape_description',
                'observation_description',
                'conservation_note',
                'endemism_description',
                'provinces',
                'external_links',
                'inaturalist_taxon_id',
                'gbif_taxon_key',
                'content_hash',
            ]);
        });
    }
};
