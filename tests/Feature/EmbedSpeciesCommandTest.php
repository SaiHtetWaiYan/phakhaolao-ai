<?php

use App\Models\Species;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Embeddings;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('backfills embeddings for scraped species', function () {
    Schema::table('species', function ($table) {
        $table->json('embedding')->nullable();
    });

    Embeddings::fake();

    $scraped = Species::factory()->scraped()->create();
    $pending = Species::factory()->create(['scrape_status' => 'pending']);

    $this->artisan('species:embed', [
        '--chunk' => 10,
        '--limit' => 10,
        '--dimensions' => 16,
    ])->assertSuccessful();

    expect($scraped->fresh()->embedding)->toBeArray();
    expect($pending->fresh()->embedding)->toBeNull();
});

it('fails when the embedding column does not exist', function () {
    $this->artisan('species:embed')
        ->assertFailed();
});
