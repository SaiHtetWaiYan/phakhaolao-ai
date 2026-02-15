<?php

use App\Models\Species;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('indexes species IDs from search pages', function () {
    $searchHtml = file_get_contents(base_path('tests/Fixtures/search_page_1.html'));

    Http::fake([
        'species.phakhaolao.la/search?page=1' => Http::response($searchHtml, 200),
    ]);

    $this->artisan('species:scrape', [
        '--phase' => 'index',
        '--page-start' => 1,
        '--page-end' => 1,
        '--delay' => 0,
    ])->assertSuccessful();

    expect(Species::count())->toBe(3);
    expect(Species::where('source_id', 56)->exists())->toBeTrue();
    expect(Species::where('source_id', 45)->exists())->toBeTrue();
    expect(Species::where('source_id', 787)->exists())->toBeTrue();
    expect(Species::where('scrape_status', 'pending')->count())->toBe(3);
});

it('scrapes detail pages for pending species', function () {
    $detailHtml = file_get_contents(base_path('tests/Fixtures/species_detail_56.html'));

    Species::factory()->create(['source_id' => 56, 'scrape_status' => 'pending']);

    Http::fake([
        'species.phakhaolao.la/search/specie_details/56' => Http::response($detailHtml, 200),
    ]);

    $this->artisan('species:scrape', [
        '--phase' => 'detail',
        '--limit' => 1,
        '--delay' => 0,
    ])->assertSuccessful();

    $species = Species::where('source_id', 56)->first();
    expect($species->scrape_status)->toBe('scraped');
    expect($species->scientific_name)->toBe('Amorphophallus paeoniifolius (Dennst.) Nicolson');
    expect($species->family)->toBe('Araceae');
    expect($species->scraped_at)->not->toBeNull();
});

it('marks species as failed when the server returns an error', function () {
    Species::factory()->create(['source_id' => 999, 'scrape_status' => 'pending']);

    Http::fake([
        'species.phakhaolao.la/search/specie_details/999' => Http::response('', 500),
    ]);

    $this->artisan('species:scrape', [
        '--phase' => 'detail',
        '--limit' => 1,
        '--delay' => 0,
    ])->assertSuccessful();

    $species = Species::where('source_id', 999)->first();
    expect($species->scrape_status)->toBe('failed');
    expect($species->scrape_error)->not->toBeNull();
});

it('retries failed species with --retry-failed', function () {
    $detailHtml = file_get_contents(base_path('tests/Fixtures/species_detail_56.html'));

    Species::factory()->failed()->create(['source_id' => 56]);

    Http::fake([
        'species.phakhaolao.la/search/specie_details/56' => Http::response($detailHtml, 200),
    ]);

    $this->artisan('species:scrape', [
        '--phase' => 'detail',
        '--retry-failed' => true,
        '--delay' => 0,
    ])->assertSuccessful();

    $species = Species::where('source_id', 56)->first();
    expect($species->scrape_status)->toBe('scraped');
});

it('does not scrape already-scraped species', function () {
    Species::factory()->scraped()->create(['source_id' => 56]);

    Http::fake();

    $this->artisan('species:scrape', [
        '--phase' => 'detail',
        '--delay' => 0,
    ])->assertSuccessful();

    Http::assertNothingSent();
});

it('is idempotent when indexing the same page twice', function () {
    $searchHtml = file_get_contents(base_path('tests/Fixtures/search_page_1.html'));

    Http::fake([
        'species.phakhaolao.la/search?page=1' => Http::response($searchHtml, 200),
    ]);

    $this->artisan('species:scrape', [
        '--phase' => 'index',
        '--page-start' => 1,
        '--page-end' => 1,
        '--delay' => 0,
    ])->assertSuccessful();

    $this->artisan('species:scrape', [
        '--phase' => 'index',
        '--page-start' => 1,
        '--page-end' => 1,
        '--delay' => 0,
    ])->assertSuccessful();

    expect(Species::count())->toBe(3);
});
