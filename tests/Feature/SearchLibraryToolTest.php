<?php

use App\Ai\Tools\SearchLibrary;
use App\Models\LibraryResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function createLibraryResource(array $attributes = []): LibraryResource
{
    static $sourceId = 1000;

    return LibraryResource::create(array_merge([
        'source_id' => $sourceId++,
        'language' => 'en',
        'title' => 'Agroforestry Guide',
        'author' => 'Pha Khao Lao',
        'publication_year' => 2025,
        'description' => 'A practical guide to sustainable farming.',
        'resource_type' => 'Manual, handbook, guideline',
        'resource_language' => 'English',
        'topics' => ['Agroforestry and Ecosystem Management'],
        'source_url' => 'https://phakhaolao.la/en/resource/agroforestry-guide/',
    ], $attributes));
}

it('filters library resources without requiring a keyword', function () {
    createLibraryResource();
    createLibraryResource([
        'title' => 'Livestock Research',
        'publication_year' => 2024,
        'resource_type' => 'Research article',
        'topics' => ['Livestock Diversity and Indigenous Breeds'],
    ]);

    $result = (new SearchLibrary)->handle(new Request([
        'topic' => 'Agroforestry',
        'resource_type' => 'Manual',
        'resource_language' => 'English',
        'publication_year' => 2025,
        'author' => 'Pha Khao',
    ]));

    expect($result)
        ->toContain('Found 1 library resource')
        ->toContain('Agroforestry Guide')
        ->not->toContain('Livestock Research');
});

it('combines keyword and page language filters', function () {
    createLibraryResource(['title' => 'Rice Farming Handbook']);
    createLibraryResource([
        'language' => 'lo',
        'title' => 'Rice Farming in Lao',
        'source_url' => 'https://phakhaolao.la/resource/rice-farming/',
    ]);

    $result = (new SearchLibrary)->handle(new Request([
        'query' => 'rice',
        'language' => 'lo',
    ]));

    expect($result)
        ->toContain('Rice Farming in Lao')
        ->not->toContain('Rice Farming Handbook');
});

it('sorts filtered resources by publication year', function () {
    createLibraryResource(['title' => 'Older Resource', 'publication_year' => 2020]);
    createLibraryResource(['title' => 'Newer Resource', 'publication_year' => 2026]);

    $result = (string) (new SearchLibrary)->handle(new Request([
        'resource_language' => 'en',
        'sort' => 'oldest',
    ]));

    expect(strpos($result, 'Older Resource'))->toBeLessThan(strpos($result, 'Newer Resource'));
});

it('normalizes every website document language code', function (string $code, string $language) {
    createLibraryResource([
        'title' => "{$language} Resource",
        'resource_language' => $language,
    ]);

    $result = (new SearchLibrary)->handle(new Request([
        'resource_language' => $code,
    ]));

    expect($result)->toContain("{$language} Resource");
})->with([
    ['my', 'Burmese'],
    ['en', 'English'],
    ['fr', 'French'],
    ['km', 'Khmer'],
    ['lo', 'Lao'],
    ['th', 'Thai'],
    ['vi', 'Vietnamese'],
]);

it('ignores a zero publication_year filter from the model', function () {
    createLibraryResource(['title' => 'Strobilanthes Checklist', 'publication_year' => 2021]);

    // The model often passes 0 for an unspecified integer; it must not filter to year=0.
    $result = (new SearchLibrary)->handle(new Request([
        'query' => 'Strobilanthes',
        'publication_year' => 0,
    ]));

    expect($result)->toContain('Strobilanthes Checklist');
});

it('requires a keyword or filter', function () {
    $result = (new SearchLibrary)->handle(new Request([]));

    expect($result)->toContain('Provide a keyword or at least one library filter');
});
