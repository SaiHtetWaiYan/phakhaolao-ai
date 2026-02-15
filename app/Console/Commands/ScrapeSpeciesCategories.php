<?php

namespace App\Console\Commands;

use App\Models\Species;
use App\Services\SpeciesScraper;
use Illuminate\Console\Command;

class ScrapeSpeciesCategories extends Command
{
    protected $signature = 'species:scrape-categories
        {--limit=0 : Limit number of species to process (0 = all)}
        {--delay=1000 : Delay between requests in milliseconds}';

    protected $description = 'Backfill category, subcategory, species_type, and national conservation status for existing species';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $scraper = new SpeciesScraper(delayMs: (int) $this->option('delay'));

        $query = Species::query()
            ->where('scrape_status', 'scraped')
            ->whereNull('category')
            ->orderBy('source_id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $species = $query->get();

        if ($species->isEmpty()) {
            $this->info('No species need category backfill.');

            return self::SUCCESS;
        }

        $this->info("Backfilling categories for {$species->count()} species...");
        $bar = $this->output->createProgressBar($species->count());
        $bar->start();

        $updated = 0;
        $failed = 0;

        foreach ($species as $record) {
            try {
                $data = $scraper->scrapeSpeciesDetail($record->source_id);

                if ($data) {
                    $record->update(array_filter([
                        'category' => $data['category'] ?? null,
                        'subcategory' => $data['subcategory'] ?? null,
                        'species_type' => $data['species_type'] ?? null,
                        'national_conservation_status' => $data['national_conservation_status'] ?? null,
                    ], fn ($v) => $v !== null));
                    $updated++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error(" Error on species #{$record->source_id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Updated: {$updated}, Failed: {$failed}");

        return self::SUCCESS;
    }
}
