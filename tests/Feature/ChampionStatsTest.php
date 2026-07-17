<?php

use App\Ai\Tools\ChampionStats;
use App\Models\Champion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function makeStatsChampion(array $attributes = []): Champion
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

it('counts champions per province, most common first', function () {
    makeStatsChampion(['province' => 'Vientiane Capital']);
    makeStatsChampion(['province' => 'Vientiane Capital']);
    makeStatsChampion(['province' => 'Luang Prabang']);
    makeStatsChampion(['province' => 'Vientiane Capital', 'language' => 'lo']); // other language ignored

    $result = (string) (new ChampionStats)->handle(new Request(['group_by' => 'province', 'language' => 'en']));

    expect($result)->toContain('Champions by province (of 3 total)')
        ->toContain('Vientiane Capital: 2')
        ->toContain('Luang Prabang: 1');
});

it('counts champions per actor type', function () {
    makeStatsChampion(['category_actor' => 'Private Sector']);
    makeStatsChampion(['category_actor' => 'Academia']);

    $result = (string) (new ChampionStats)->handle(new Request(['group_by' => 'actor', 'language' => 'en']));

    expect($result)->toContain('Private Sector: 1')->toContain('Academia: 1');
});

it('expands json sectors when grouping by sector', function () {
    makeStatsChampion(['sectors' => ['Coffee', 'Farming']]);
    makeStatsChampion(['sectors' => ['Coffee']]);

    $result = (string) (new ChampionStats)->handle(new Request(['group_by' => 'sector', 'language' => 'en']));

    expect($result)->toContain('Coffee: 2')->toContain('Farming: 1');
});

it('returns the overall total when group_by is omitted', function () {
    makeStatsChampion();
    makeStatsChampion();

    $result = (string) (new ChampionStats)->handle(new Request(['language' => 'en']));

    expect($result)->toContain('There are 2 champion profiles');
});
