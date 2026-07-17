<?php

use App\Models\AppSetting;
use App\Services\LibraryFilterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeLibraryFilterPage(): string
{
    return <<<'HTML'
    <form class="searchandfilter">
        <select name="_sft_topic[]">
            <option value="">All Topics</option>
            <option value="agroforestry">Agroforestry and Ecosystem Management (54)</option>
            <option value="seed-banks">Community Seed Banks and Local Seed Networks (0)</option>
        </select>
        <select name="_sft_resource-type[]">
            <option value="">All Types</option>
            <option value="research-article">Research article (119)</option>
            <option value="legal-brief">Legal brief (0)</option>
        </select>
        <select name="_sft_language[]">
            <option value="">All Languages</option>
            <option value="burmese">Burmese (0)</option>
            <option value="english">English (171)</option>
            <option value="french">French (0)</option>
            <option value="khmer">Khmer (0)</option>
            <option value="lao">Lao (9)</option>
            <option value="thai">Thai (0)</option>
            <option value="vietnamese">Vietnamese (0)</option>
        </select>
        <select name="_sfm_pkl_resource_year[]">
            <option value="">All Years</option>
            <option value="2026">2026</option>
            <option value="2023.">2023.</option>
            <option value="2023">2023</option>
        </select>
        <select name="_sfm_pkl_resource_author[]">
            <option value="">All Authors</option>
            <option value=" Example Author">Example Author</option>
        </select>
        <select name="_sf_sort_order[]">
            <option value="">Sort By</option>
            <option value="title+asc">Title A-Z</option>
            <option value="title+desc">Title Z-A</option>
            <option value="date+asc">Old-New</option>
            <option value="date+desc">New-Old</option>
        </select>
    </form>
    HTML;
}

it('imports filter options from the live library form', function () {
    Http::fake([
        'phakhaolao.la/en/discover/library/' => Http::response(fakeLibraryFilterPage()),
    ]);

    expect(app(LibraryFilterCatalog::class)->sync('en'))->toBeTrue();

    $catalog = app(LibraryFilterCatalog::class)->get('en');

    expect($catalog['topics'])->toContain([
        'value' => 'Community Seed Banks and Local Seed Networks',
        'label' => 'Community Seed Banks and Local Seed Networks (0)',
    ]);
    expect($catalog['types'])->toContain([
        'value' => 'Legal brief',
        'label' => 'Legal brief (0)',
    ]);
    expect($catalog['languages'])->toBe([
        ['value' => 'Burmese', 'label' => 'Burmese (0)'],
        ['value' => 'English', 'label' => 'English (171)'],
        ['value' => 'French', 'label' => 'French (0)'],
        ['value' => 'Khmer', 'label' => 'Khmer (0)'],
        ['value' => 'Lao', 'label' => 'Lao (9)'],
        ['value' => 'Thai', 'label' => 'Thai (0)'],
        ['value' => 'Vietnamese', 'label' => 'Vietnamese (0)'],
    ]);
    expect($catalog['years'])->toBe([2026, 2023]);
    expect($catalog['authors'])->toBe(['Example Author']);
    expect($catalog['sorts'])->toBe([
        'title_asc' => 'Title A-Z',
        'title_desc' => 'Title Z-A',
        'oldest' => 'Old-New',
        'newest' => 'New-Old',
    ]);
});

it('reads a persisted website catalog', function () {
    AppSetting::create([
        'key' => 'library.filters.en',
        'value' => json_encode([
            'topics' => [['value' => 'Website Topic', 'label' => 'Website Topic (8)']],
            'types' => [['value' => 'Website Type', 'label' => 'Website Type (3)']],
            'languages' => [['value' => 'Website Language', 'label' => 'Website Language (2)']],
            'years' => [2026],
            'authors' => ['Website Author'],
            'sorts' => ['newest' => 'New-Old'],
        ]),
    ]);

    $filters = app(LibraryFilterCatalog::class)->get('en');

    expect($filters['topics'])->toBe([['value' => 'Website Topic', 'label' => 'Website Topic (8)']]);
    expect($filters['types'])->toBe([['value' => 'Website Type', 'label' => 'Website Type (3)']]);
    expect($filters['authors'])->toBe(['Website Author']);
});
