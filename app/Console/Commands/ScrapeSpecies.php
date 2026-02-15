<?php

namespace App\Console\Commands;

use App\Models\Species;
use App\Services\SpeciesScraper;
use Illuminate\Console\Command;

class ScrapeSpecies extends Command
{
    protected $signature = 'species:scrape
        {--phase=all : Phase to run (index|detail|all)}
        {--limit=0 : Limit number of species to scrape (0 = all)}
        {--page-start=1 : Start page for index phase}
        {--page-end=81 : End page for index phase}
        {--delay=1000 : Delay between requests in milliseconds}
        {--retry-failed : Re-scrape species with failed status}';

    protected $description = 'Scrape species data from species.phakhaolao.la';

    public function handle(): int
    {
        $phase = $this->option('phase');
        $scraper = new SpeciesScraper(delayMs: (int) $this->option('delay'));

        if ($phase === 'index' || $phase === 'all') {
            $this->runIndexPhase($scraper);
        }

        if ($phase === 'detail' || $phase === 'all') {
            $this->runDetailPhase($scraper);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function runIndexPhase(SpeciesScraper $scraper): void
    {
        $startPage = (int) $this->option('page-start');
        $endPage = (int) $this->option('page-end');

        $this->info("Indexing species IDs from pages {$startPage} to {$endPage}...");
        $bar = $this->output->createProgressBar($endPage - $startPage + 1);
        $bar->start();

        $ids = $scraper->collectSpeciesIds($startPage, $endPage);

        $bar->finish();
        $this->newLine();

        $created = 0;
        foreach ($ids as $sourceId) {
            Species::firstOrCreate(
                ['source_id' => $sourceId],
                ['scrape_status' => 'pending'],
            );
            $created++;
        }

        $this->info("Indexed {$created} species IDs. Total pending: " . Species::query()->where('scrape_status', 'pending')->count());
    }

    private function runDetailPhase(SpeciesScraper $scraper): void
    {
        $limit = (int) $this->option('limit');
        $retryFailed = $this->option('retry-failed');

        $query = Species::query();

        if ($retryFailed) {
            $query->whereIn('scrape_status', ['pending', 'failed']);
        } else {
            $query->where('scrape_status', 'pending');
        }

        $query->orderBy('source_id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $species = $query->get();

        if ($species->isEmpty()) {
            $this->warn('No species to scrape. Run --phase=index first.');

            return;
        }

        $this->info("Scraping details for {$species->count()} species...");
        $bar = $this->output->createProgressBar($species->count());
        $bar->start();

        $scraped = 0;
        $failed = 0;

        foreach ($species as $record) {
            try {
                $data = $scraper->scrapeSpeciesDetail($record->source_id);

                if ($data) {
                    $record->update(array_merge($data, [
                        'scrape_status' => 'scraped',
                        'scrape_error' => null,
                        'scraped_at' => now(),
                    ]));
                    $scraped++;
                } else {
                    $record->update([
                        'scrape_status' => 'failed',
                        'scrape_error' => 'Empty response from server',
                    ]);
                    $failed++;
                }
            } catch (\Exception $e) {
                $record->update([
                    'scrape_status' => 'failed',
                    'scrape_error' => $e->getMessage(),
                ]);
                $failed++;
                $this->error(" Error on species #{$record->source_id}: {$e->getMessage()}");
            }

            $bar->advance();
            usleep((int) $this->option('delay') * 1000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Scraped: {$scraped}, Failed: {$failed}");
    }
}
