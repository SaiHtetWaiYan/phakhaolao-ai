<?php

use App\Ai\Tools\SearchStories;
use App\Ai\Tools\StoryStats;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function createStory(array $attributes = []): Story
{
    static $sourceId = 5000;

    return Story::create(array_merge([
        'source_id' => $sourceId++,
        'language' => 'en',
        'title' => 'Rice Farming in the Highlands',
        'authors' => 'Pha Khao Lao',
        'summary' => 'A field story about upland rice.',
        'story' => 'Full story body about rice farming practices.',
        'story_types' => ['Farming'],
        'source_url' => 'https://phakhaolao.la/en/story/rice-farming/',
    ], $attributes));
}

it('filters stories by story_type without a keyword', function () {
    createStory(['title' => 'Upland Rice', 'story_types' => ['Farming']]);
    createStory(['title' => 'Village Clinic', 'story_types' => ['Health']]);

    $result = (new SearchStories)->handle(new Request([
        'story_type' => 'Farming',
    ]));

    expect($result)
        ->toContain('Upland Rice')
        ->not->toContain('Village Clinic');
});

it('still searches stories by keyword', function () {
    createStory(['title' => 'Mushroom Foraging', 'story_types' => ['Farming']]);
    createStory(['title' => 'Village Clinic', 'story_types' => ['Health']]);

    $result = (new SearchStories)->handle(new Request([
        'query' => 'mushroom',
    ]));

    expect($result)
        ->toContain('Mushroom Foraging')
        ->not->toContain('Village Clinic');
});

it('requires a keyword or story_type', function () {
    $result = (new SearchStories)->handle(new Request([]));

    expect($result)->toContain('provide a search term or a story_type');
});

it('counts stories per category', function () {
    createStory(['story_types' => ['Farming']]);
    createStory(['story_types' => ['Farming']]);
    createStory(['story_types' => ['Health']]);

    $result = (new StoryStats)->handle(new Request(['language' => 'en']));

    expect($result)
        ->toContain('Farming: 2')
        ->toContain('Health: 1');
});

it('counts a single story category', function () {
    createStory(['story_types' => ['Farming']]);
    createStory(['story_types' => ['Farming']]);

    $result = (new StoryStats)->handle(new Request([
        'language' => 'en',
        'value' => 'Farming',
    ]));

    expect($result)->toContain('Farming: 2 stories');
});

it('counts the Lao category set separately', function () {
    createStory(['language' => 'lo', 'story_types' => ['ການກະເສດ']]);

    $result = (new StoryStats)->handle(new Request(['language' => 'lo']));

    expect($result)->toContain('ການກະເສດ: 1');
});
