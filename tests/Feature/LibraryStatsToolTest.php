<?php

use App\Ai\Tools\LibraryStats;
use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function seedLibraryCatalog(): void
{
    AppSetting::create([
        'key' => 'library.filters.en',
        'value' => json_encode([
            'topics' => [['value' => 'Wild Foods', 'label' => 'Wild Foods (12)']],
            'types' => [
                ['value' => 'Book, book chapter', 'label' => 'Book, book chapter (34)'],
                ['value' => 'Research article', 'label' => 'Research article (119)'],
            ],
            'languages' => [
                ['value' => 'English', 'label' => 'English (171)'],
                ['value' => 'Lao', 'label' => 'Lao (9)'],
            ],
            'years' => [], 'authors' => [], 'sorts' => [],
        ], JSON_UNESCAPED_UNICODE),
    ]);

    AppSetting::create([
        'key' => 'library.filters.lo',
        'value' => json_encode([
            'topics' => [],
            'types' => [['value' => 'ປຶ້ມ', 'label' => 'ປຶ້ມ (30)']],
            'languages' => [['value' => 'ລາວ', 'label' => 'ລາວ (298)']],
            'years' => [], 'authors' => [], 'sorts' => [],
        ], JSON_UNESCAPED_UNICODE),
    ]);
}

it('returns the website language counts for the English catalogue', function () {
    seedLibraryCatalog();

    $result = (new LibraryStats)->handle(new Request([
        'dimension' => 'language',
        'language' => 'en',
    ]));

    expect($result)
        ->toContain('English: 171')
        ->toContain('Lao: 9');
});

it('returns a single facet count', function () {
    seedLibraryCatalog();

    $result = (new LibraryStats)->handle(new Request([
        'dimension' => 'type',
        'language' => 'en',
        'value' => 'Book',
    ]));

    expect($result)->toContain('Book, book chapter: 34 resources');
});

it('uses the Lao catalogue when language is lo', function () {
    seedLibraryCatalog();

    $result = (new LibraryStats)->handle(new Request([
        'dimension' => 'language',
        'language' => 'lo',
    ]));

    expect($result)->toContain('ລາວ: 298');
});

it('rejects an unknown dimension', function () {
    seedLibraryCatalog();

    $result = (new LibraryStats)->handle(new Request([
        'dimension' => 'author',
    ]));

    expect($result)->toContain('Specify dimension');
});

it('reports when the catalogue has not been synced', function () {
    $result = (new LibraryStats)->handle(new Request([
        'dimension' => 'type',
        'language' => 'en',
    ]));

    expect($result)->toContain('not available yet');
});
