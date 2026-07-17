<?php

use App\Ai\Tools\SearchLibraryContent;
use App\Models\LibraryChunk;
use App\Models\LibraryResource;
use App\Services\LibraryPdfIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

it('splits text into word-bounded chunks under the size limit', function () {
    $text = str_repeat('word ', 1000); // 5000 chars

    $chunks = LibraryPdfIndexer::chunkText($text, 1000);

    expect($chunks)->not->toBeEmpty();
    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk))->toBeLessThanOrEqual(1000);
        expect($chunk)->not->toContain('  '); // no split mid-word / double spaces
    }
    // Round-trips back to the same words.
    expect(implode(' ', $chunks))->toBe(trim($text));
});

it('returns an empty array for blank text', function () {
    expect(LibraryPdfIndexer::chunkText('   ', 1000))->toBe([]);
});

it('relates chunks to their library resource', function () {
    $resource = LibraryResource::create([
        'source_id' => 7001,
        'language' => 'en',
        'title' => 'Rice Report',
    ]);

    $resource->chunks()->create([
        'chunk_index' => 0,
        'content' => 'Rice agrobiodiversity in Laos.',
        'content_hash' => md5('Rice agrobiodiversity in Laos.'),
    ]);

    expect($resource->chunks)->toHaveCount(1);
    expect(LibraryChunk::first()->resource->title)->toBe('Rice Report');
});

it('tells the user when full-text search is not available yet', function () {
    // On SQLite (tests) the pgvector index does not exist, so the tool degrades gracefully.
    $result = (new SearchLibraryContent)->handle(new Request(['query' => 'rice farming']));

    expect($result)->toContain('not available yet');
});

it('requires a query', function () {
    $result = (new SearchLibraryContent)->handle(new Request([]));

    expect($result)->toContain('provide a question or keyword');
});
