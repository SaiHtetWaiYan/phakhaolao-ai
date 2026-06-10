<?php

use App\Models\Story;
use App\Services\StoryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeStoryPost(int $id, string $title): array
{
    return [
        'id' => $id,
        'slug' => 'story-'.$id,
        'link' => 'https://phakhaolao.la/en/story/story-'.$id.'/',
        'modified' => '2026-05-12T15:58:05',
        'title' => ['rendered' => $title],
        'content' => ['rendered' => '<p>A field <strong>story</strong> about home gardens.</p>'],
        'acf' => [
            'pkl_story_headline' => 'Roots for good nutrition',
            'pkl_story_authors' => 'Bouavone Thipphavanh',
            'pkl_story_image_credits' => 'PKL',
        ],
        '_embedded' => [
            'wp:term' => [[
                ['taxonomy' => 'story-type', 'name' => 'Farming'],
                ['taxonomy' => 'story-type', 'name' => 'Health'],
            ]],
        ],
    ];
}

it('imports stories from the WordPress REST API', function () {
    Http::fake(function ($request) {
        $id = str_contains($request->url(), 'lang=lo') ? 200 : 100;

        return Http::response([fakeStoryPost($id, 'Home gardens')], 200, ['X-WP-TotalPages' => '1']);
    });

    $result = app(StoryImporter::class)->import();

    expect($result['imported'])->toBe(2);

    $story = Story::where('source_id', 100)->first();
    expect($story->title)->toBe('Home gardens');
    expect($story->authors)->toBe('Bouavone Thipphavanh');
    expect($story->summary)->toBe('Roots for good nutrition');
    expect($story->story)->toBe('A field story about home gardens.');
    expect($story->story_types)->toBe(['Farming', 'Health']);
});

it('is idempotent and does not duplicate on re-import', function () {
    Http::fake(function ($request) {
        $id = str_contains($request->url(), 'lang=lo') ? 200 : 100;

        return Http::response([fakeStoryPost($id, 'Story')], 200, ['X-WP-TotalPages' => '1']);
    });

    app(StoryImporter::class)->import();
    $second = app(StoryImporter::class)->import();

    expect(Story::count())->toBe(2);
    expect($second['changed'])->toBe(0);
});
