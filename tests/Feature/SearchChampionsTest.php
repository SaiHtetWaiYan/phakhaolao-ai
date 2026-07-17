<?php

use App\Ai\Tools\SearchChampions;
use App\Models\Champion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function makeChampion(array $attributes = []): Champion
{
    static $id = 0;
    $id++;

    return Champion::create(array_merge([
        'source_id' => $id,
        'language' => 'en',
        'slug' => 'champion-'.$id,
        'name' => 'Champion '.$id,
        'sectors' => [],
        'topics' => [],
        'scales' => [],
    ], $attributes));
}

it('returns the exact total and full roster when the query is empty', function () {
    makeChampion(['name' => 'Bounma', 'language' => 'en']);
    makeChampion(['name' => 'Alinsa', 'language' => 'en']);
    makeChampion(['name' => 'Lao-only', 'language' => 'lo']);

    $result = (string) (new SearchChampions)->handle(new Request(['language' => 'en']));

    expect($result)->toContain('There are 2 champion profiles')
        ->toContain('Alinsa, Bounma'); // alphabetical, English only
});

// The keyword search uses Postgres-only SQL (jsonb ::text ilike); the app runs on Postgres.
it('reports the match total when a keyword returns more than the page size', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('SearchChampions keyword filter requires Postgres.');
    }

    foreach (range(1, 8) as $i) {
        makeChampion(['name' => "Coffee Farmer {$i}", 'sectors' => ['Coffee']]);
    }

    $result = (string) (new SearchChampions)->handle(new Request(['query' => 'coffee', 'language' => 'en']));

    expect($result)->toContain('8 champions match')
        ->toContain('Showing the first 6');
});

it('still explains when nothing matches a keyword', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('SearchChampions keyword filter requires Postgres.');
    }

    makeChampion(['name' => 'Bounma']);

    $result = (string) (new SearchChampions)->handle(new Request(['query' => 'zzzznope', 'language' => 'en']));

    expect($result)->toContain('No champions found');
});
