<?php

use App\Ai\Tools\SearchSpecies;
use App\Models\Species;
use Laravel\Ai\Tools\Request;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('finds species by scientific name', function () {
    Species::factory()->scraped()->create([
        'scientific_name' => 'Amorphophallus paeoniifolius',
        'common_name_english' => 'Elephant Foot Yam',
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'Amorphophallus']));

    expect($result)->toContain('Amorphophallus paeoniifolius');
    expect($result)->toContain('Elephant Foot Yam');
});

it('finds species by English common name', function () {
    Species::factory()->scraped()->create([
        'scientific_name' => 'Oryza sativa',
        'common_name_english' => 'Rice',
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'Rice']));

    expect($result)->toContain('Oryza sativa');
});

it('finds species by Lao name', function () {
    Species::factory()->scraped()->create([
        'scientific_name' => 'Amorphophallus paeoniifolius',
        'common_name_lao' => 'ຫົວກະບຸກ',
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'ຫົວກະບຸກ']));

    expect($result)->toContain('Amorphophallus paeoniifolius');
});

it('finds species by family', function () {
    Species::factory()->scraped()->create([
        'scientific_name' => 'Amorphophallus paeoniifolius',
        'family' => 'Araceae',
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'Araceae']));

    expect($result)->toContain('Amorphophallus paeoniifolius');
});

it('returns a helpful message when no species are found', function () {
    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'nonexistent_xyz']));

    expect($result)->toContain('No species found');
});

it('excludes non-scraped species from results', function () {
    Species::factory()->create([
        'scientific_name' => 'Pending Species',
        'scrape_status' => 'pending',
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'Pending']));

    expect($result)->toContain('No species found');
});

it('limits results to 10 species', function () {
    Species::factory()->scraped()->count(15)->create([
        'family' => 'Testaceae',
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'Testaceae']));

    expect(substr_count($result, '**'))->toBeLessThanOrEqual(20); // 2 ** per species, max 10
});

it('includes nutrition data when available', function () {
    Species::factory()->scraped()->create([
        'scientific_name' => 'Nutritious Plant',
        'nutrition' => [
            ['nutrient' => 'Protein', 'value_per_100g' => '5.0'],
        ],
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'Nutritious Plant']));

    expect($result)->toContain('Protein: 5.0/100g');
});

it('returns full species records for list-style queries', function () {
    Species::factory()->scraped()->count(6)->create([
        'family' => 'Listaceae',
        'use_types' => ['ພືດເປັນຢາ'],
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'List some plants']));

    expect($result)->toContain('**');
    expect($result)->toContain('Family: Listaceae');
    expect($result)->toContain('---');
});

it('includes image urls when available', function () {
    Species::factory()->scraped()->create([
        'scientific_name' => 'Amorphophallus paeoniifolius (Dennst.) Nicolson',
        'common_name_english' => 'Elephant Foot Yam',
        'image_urls' => [
            'https://species.phakhaolao.la/storage/upload_photos/test-1.jpg',
            'https://species.phakhaolao.la/storage/upload_photos/test-2.jpg',
        ],
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'Amorphophallus paeoniifolius (Dennst.) Nicolson']));

    expect($result)->toContain('Images:');
    expect($result)->toContain('![');
    expect($result)->toContain('test-1.jpg');
});

it('includes map urls when available', function () {
    Species::factory()->scraped()->create([
        'scientific_name' => 'Amorphophallus paeoniifolius (Dennst.) Nicolson',
        'map_urls' => [
            'https://species.phakhaolao.la/maps/topographic_la.jpg',
            'https://species.phakhaolao.la/maps/landscapes_la.jpg',
        ],
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'Amorphophallus paeoniifolius (Dennst.) Nicolson map_urls']));

    expect($result)->toContain('Maps:');
    expect($result)->toContain('[Map 1](');
    expect($result)->toContain('topographic_la.jpg');
});

it('includes extended species metadata columns when available', function () {
    Species::factory()->scraped()->create([
        'scientific_name' => 'Metadata Plant',
        'source_id' => 9999,
        'data_collection_level' => 'Detailed',
        'local_names' => ['Local A', 'Local B'],
        'synonyms' => ['Syn A'],
        'related_species' => ['Related A'],
        'lao_distribution' => 'Northern Laos',
        'management_info' => 'Managed by local communities',
        'threats' => 'Overharvesting',
        'nutrition_description' => 'High micronutrients',
        'references' => ['https://example.com/ref-1'],
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'Metadata Plant']));

    expect($result)->toContain('Source ID: 9999');
    expect($result)->toContain('Data level: Detailed');
    expect($result)->toContain('Local names: Local A, Local B');
    expect($result)->toContain('Synonyms: Syn A');
    expect($result)->toContain('Related species: Related A');
    expect($result)->toContain('Lao distribution: Northern Laos');
    expect($result)->toContain('Management: Managed by local communities');
    expect($result)->toContain('Threats: Overharvesting');
    expect($result)->toContain('Nutrition details: High micronutrients');
    expect($result)->toContain('References: https://example.com/ref-1');
});

it('includes canonical phakhaolao website link using source id', function () {
    Species::factory()->scraped()->create([
        'scientific_name' => 'Marsilea quadrifolia L.',
        'source_id' => 782,
    ]);

    $tool = new SearchSpecies;
    $result = $tool->handle(new Request(['query' => 'Marsilea quadrifolia']));

    expect($result)->toContain('Source ID: 782');
    expect($result)->toContain('https://species.phakhaolao.la/search/specie_details/782');
});
