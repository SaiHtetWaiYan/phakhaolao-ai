<?php

use App\Models\Champion;
use App\Services\ChampionImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeChampionPost(int $id, string $title): array
{
    return [
        'id' => $id,
        'slug' => 'champion-'.$id,
        'link' => 'https://phakhaolao.la/en/champion/champion-'.$id.'/',
        'modified' => '2026-05-14T11:26:31',
        'title' => ['rendered' => $title],
        'content' => ['rendered' => '<p>A pioneering <strong>enterprise</strong>.</p>'],
        'excerpt' => ['rendered' => ''],
        'acf' => [
            'pkl_story_headline' => 'Promoting Khao Kai Noi',
            'pkl_story_authors' => 'Jane Doe',
            'pkl_story_image_credits' => 'PKL',
            'pkl_story_video' => '',
            'pkl_story_file' => null,
            'pkl_story_image' => '',
            'photo_gallery' => [
                'pkl_photo_gallery' => [[
                    ['id' => 1, 'full_image_url' => 'https://phakhaolao.la/img/1.jpg'],
                    ['id' => 2, 'full_image_url' => 'https://phakhaolao.la/img/2.jpg'],
                ]],
            ],
        ],
        '_embedded' => [
            'wp:term' => [
                [['taxonomy' => 'category-actor', 'name' => 'Private Sector']],
                [['taxonomy' => 'province', 'name' => 'Houaphan']],
                [
                    ['taxonomy' => 'sector', 'name' => 'Farming'],
                    ['taxonomy' => 'sector', 'name' => 'Trade'],
                ],
            ],
        ],
    ];
}

it('imports champions from the WordPress REST API per language', function () {
    Http::fake(function ($request) {
        $lang = str_contains($request->url(), 'lang=lo') ? 'lo' : 'en';
        $id = $lang === 'lo' ? 200 : 100;

        return Http::response([fakeChampionPost($id, 'Yordxam Company')], 200, ['X-WP-TotalPages' => '1']);
    });

    $result = app(ChampionImporter::class)->import();

    expect($result['imported'])->toBe(2);
    expect(Champion::where('language', 'en')->count())->toBe(1);
    expect(Champion::where('language', 'lo')->count())->toBe(1);

    $champion = Champion::where('source_id', 100)->first();
    expect($champion->name)->toBe('Yordxam Company');
    expect($champion->authors)->toBe('Jane Doe');
    expect($champion->category_actor)->toBe('Private Sector');
    expect($champion->province)->toBe('Houaphan');
    expect($champion->sectors)->toBe(['Farming', 'Trade']);
    expect($champion->story)->toBe('A pioneering enterprise.');
    expect($champion->gallery)->toHaveCount(2);
    expect($champion->featured_image)->toBe('https://phakhaolao.la/img/1.jpg');
});

it('is idempotent and does not duplicate on re-import', function () {
    Http::fake(function ($request) {
        $id = str_contains($request->url(), 'lang=lo') ? 200 : 100;

        return Http::response([fakeChampionPost($id, 'Champion')], 200, ['X-WP-TotalPages' => '1']);
    });

    app(ChampionImporter::class)->import();
    $second = app(ChampionImporter::class)->import();

    expect(Champion::count())->toBe(2);
    expect($second['changed'])->toBe(0);
});
