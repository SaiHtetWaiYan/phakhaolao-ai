<?php

use App\Models\LibraryResource;
use App\Services\LibraryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeResourcePost(int $id, string $title): array
{
    return [
        'id' => $id,
        'slug' => 'resource-'.$id,
        'link' => 'https://phakhaolao.la/en/resource/resource-'.$id.'/',
        'modified' => '2026-05-25T08:40:54',
        'title' => ['rendered' => $title],
        'content' => ['rendered' => '<p>An important <strong>NTFP</strong> reference.</p>'],
        'acf' => [
            'pkl_resource_author' => 'Rachele Arcese',
            'pkl_resource_year' => '2025.',
        ],
        '_embedded' => [
            'wp:term' => [
                [['taxonomy' => 'resource-type', 'name' => 'Book, book chapter']],
                [['taxonomy' => 'language', 'name' => 'English']],
                [['taxonomy' => 'featured', 'name' => 'Yes']],
                [
                    ['taxonomy' => 'topic', 'name' => 'Conservation'],
                    ['taxonomy' => 'topic', 'name' => 'NTFPs'],
                ],
            ],
        ],
    ];
}

it('imports library resources from the WordPress REST API', function () {
    Http::fake(function ($request) {
        $id = str_contains($request->url(), 'lang=lo') ? 200 : 100;

        return Http::response([fakeResourcePost($id, 'Herbarium of NTFPs')], 200, ['X-WP-TotalPages' => '1']);
    });

    $result = app(LibraryImporter::class)->import();

    expect($result['imported'])->toBe(2);
    expect($result['filters_failed'])->toBe(2);
    expect(LibraryResource::where('language', 'en')->count())->toBe(1);

    $resource = LibraryResource::where('source_id', 100)->first();
    expect($resource->title)->toBe('Herbarium of NTFPs');
    expect($resource->author)->toBe('Rachele Arcese');
    expect($resource->publication_year)->toBe(2025);
    expect($resource->resource_type)->toBe('Book, book chapter');
    expect($resource->resource_language)->toBe('English');
    expect($resource->featured)->toBeTrue();
    expect($resource->topics)->toBe(['Conservation', 'NTFPs']);
    expect($resource->description)->toBe('An important NTFP reference.');
});

it('enriches resources with author and PDF link from the detail page', function () {
    LibraryResource::create([
        'source_id' => 100,
        'language' => 'en',
        'title' => 'Herbarium of NTFPs',
        'source_url' => 'https://phakhaolao.la/en/resource/herbarium/',
    ]);

    $html = '<div class="elementor-heading-title elementor-size-default">Herbarium of NTFPs</div>'
        .'<div class="elementor-heading-title elementor-size-default">Rachele Arcese and Sisovath Phandanouvong</div>'
        .'<a class="elementor-button" href="https://phakhaolao.la/wp-content/uploads/2026/05/NTFP-Book.pdf" target="_blank">Download</a>';

    Http::fake(['phakhaolao.la/en/resource/*' => Http::response($html, 200)]);

    $result = app(LibraryImporter::class)->enrich(delayMs: 0);

    expect($result['updated'])->toBe(1);

    $resource = LibraryResource::where('source_id', 100)->first();
    expect($resource->author)->toBe('Rachele Arcese and Sisovath Phandanouvong');
    expect($resource->file_url)->toBe('https://phakhaolao.la/wp-content/uploads/2026/05/NTFP-Book.pdf');
});

it('is idempotent and does not duplicate on re-import', function () {
    Http::fake(function ($request) {
        $id = str_contains($request->url(), 'lang=lo') ? 200 : 100;

        return Http::response([fakeResourcePost($id, 'Resource')], 200, ['X-WP-TotalPages' => '1']);
    });

    app(LibraryImporter::class)->import();
    $second = app(LibraryImporter::class)->import();

    expect(LibraryResource::count())->toBe(2);
    expect($second['changed'])->toBe(0);
});
