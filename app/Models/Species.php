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
        'subcategory',
        'species_type',
        'iucn_status',
        'national_conservation_status',
        'native_status',
        'invasiveness',
        'data_collection_level',
        'harvest_season',
        'local_names',
        'synonyms',
        'related_species',
        'habitat_types',
        'use_types',
        'nutrition',
        'image_urls',
        'map_urls',
        'references',
        'botanical_description',
        'global_distribution',
        'lao_distribution',
        'use_description',
        'cultivation_info',
        'market_data',
        'management_info',
        'threats',
        'nutrition_description',
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
            'local_names' => 'array',
            'synonyms' => 'array',
            'related_species' => 'array',
            'habitat_types' => 'array',
            'use_types' => 'array',
            'nutrition' => 'array',
            'image_urls' => 'array',
            'map_urls' => 'array',
            'references' => 'array',
            'embedding' => 'array',
            'scraped_at' => 'datetime',
        ];
    }
}
