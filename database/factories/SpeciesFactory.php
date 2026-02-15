<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Species>
 */
class SpeciesFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_id' => fake()->unique()->numberBetween(1, 5000),
            'scientific_name' => fake()->words(2, true),
            'common_name_lao' => fake()->word(),
            'common_name_english' => fake()->word(),
            'family' => fake()->word(),
            'iucn_status' => fake()->randomElement(['LC', 'NT', 'VU', 'EN', 'CR', 'DD']),
            'native_status' => fake()->randomElement(['Native', 'Introduced', 'Unknown']),
            'invasiveness' => fake()->randomElement(['Non-invasive', 'Invasive', 'Unknown']),
            'data_collection_level' => fake()->randomElement(['High', 'Medium', 'Low']),
            'harvest_season' => fake()->randomElement(['Year-round', 'Dry season', 'Wet season']),
            'local_names' => [fake()->word(), fake()->word()],
            'synonyms' => [fake()->words(2, true)],
            'related_species' => [fake()->words(2, true)],
            'habitat_types' => [fake()->word(), fake()->word()],
            'use_types' => [fake()->word(), fake()->word()],
            'nutrition' => [['nutrient' => 'Protein', 'value' => fake()->randomFloat(1, 0, 50), 'unit' => 'g']],
            'image_urls' => ['https://example.com/image.jpg'],
            'map_urls' => ['https://example.com/map.png'],
            'references' => [['title' => fake()->sentence(), 'url' => fake()->url()]],
            'botanical_description' => fake()->paragraph(),
            'global_distribution' => fake()->paragraph(),
            'lao_distribution' => fake()->paragraph(),
            'use_description' => fake()->paragraph(),
            'cultivation_info' => fake()->paragraph(),
            'market_data' => fake()->paragraph(),
            'management_info' => fake()->paragraph(),
            'threats' => fake()->paragraph(),
            'nutrition_description' => fake()->paragraph(),
            'scrape_status' => 'pending',
            'scrape_error' => null,
            'scraped_at' => null,
        ];
    }

    public function scraped(): static
    {
        return $this->state(fn (array $attributes) => [
            'scrape_status' => 'scraped',
            'scrape_error' => null,
            'scraped_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'scrape_status' => 'failed',
            'scrape_error' => 'Connection timed out',
            'scraped_at' => null,
        ]);
    }
}
