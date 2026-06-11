<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Species extends Model
{
    /** @use HasFactory<\Database\Factories\SpeciesFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_id',
        'scientific_name',
        'common_name_lao',
        'common_name_english',
        'family',
        'category',
        'category_en',
        'subcategory',
        'subcategory_en',
        'species_type',
        'species_type_en',
        'iucn_status',
        'iucn_status_en',
        'national_conservation_status',
        'national_conservation_status_en',
        'native_status',
        'native_status_en',
        'invasiveness',
        'invasiveness_en',
        'data_collection_level',
        'harvest_season',
        'local_names',
        'synonyms',
        'related_species',
        'habitat_types',
        'landscape_units',
        'use_types',
        'use_units',
        'ntfp_lists',
        'timber_lists',
        'domestication',
        'domestication_en',
        'data_status',
        'data_status_en',
        'nutrition',
        'image_urls',
        'map_urls',
        'references',
        'botanical_description',
        'botanical_description_en',
        'global_distribution',
        'topographic_description',
        'topographic_description_en',
        'landscape_description',
        'landscape_description_en',
        'observation_description',
        'observation_description_en',
        'conservation_note',
        'conservation_note_en',
        'endemism_description',
        'endemism_description_en',
        'lao_distribution',
        'distribution_units',
        'provinces',
        'external_links',
        'inaturalist_taxon_id',
        'gbif_taxon_key',
        'use_description',
        'use_description_en',
        'cultivation_info',
        'cultivation_info_en',
        'market_data',
        'market_data_en',
        'management_info',
        'management_info_en',
        'threats',
        'nutrition_description',
        'nutrition_description_en',
        'content_hash',
        'embedding',
        'scrape_status',
        'scrape_error',
        'scraped_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'inaturalist_taxon_id' => 'integer',
            'gbif_taxon_key' => 'integer',
            'local_names' => 'array',
            'synonyms' => 'array',
            'related_species' => 'array',
            'habitat_types' => 'array',
            'landscape_units' => 'array',
            'use_types' => 'array',
            'use_units' => 'array',
            'ntfp_lists' => 'array',
            'timber_lists' => 'array',
            'nutrition' => 'array',
            'image_urls' => 'array',
            'map_urls' => 'array',
            'references' => 'array',
            'external_links' => 'array',
            'distribution_units' => 'array',
            'provinces' => 'array',
            'embedding' => 'array',
            'scraped_at' => 'datetime',
        ];
    }
}
