<?php

namespace App\Console\Commands;

use App\Models\LibraryResource;
use App\Services\LibraryImporter;
use Illuminate\Console\Command;

class ImportLibrary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'library:import
        {--dry-run : Fetch and map without writing to the database}
        {--enrich : After importing, scrape each detail page for the author and PDF link}
        {--enrich-only : Skip the REST import and only run the detail-page enrichment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync library resources from the PhaKhaoLao WordPress REST API';

    public function handle(LibraryImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $enrichOnly = (bool) $this->option('enrich-only');

        if (! $enrichOnly) {
            $this->info($dryRun ? 'Importing library resources (dry run)...' : 'Importing library resources...');

            try {
                $result = $importer->import($dryRun);
            } catch (\Throwable $e) {
                $this->error('Import failed: '.$e->getMessage());

                return self::FAILURE;
            }

            $this->table(['Metric', 'Count'], [
                ['Imported (en + lo)', $result['imported']],
                ['Changed', $result['changed']],
                ['Archived (removed from source)', $result['archived']],
            ]);
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        if ($enrichOnly || $this->option('enrich')) {
            $this->newLine();
            $this->info('Enriching resources from detail pages (author + PDF link)...');

            $bar = $this->output->createProgressBar(LibraryResource::whereNotNull('source_url')->count());
            $bar->start();

            $enriched = $importer->enrich(fn () => $bar->advance());

            $bar->finish();
            $this->newLine();
            $this->table(['Metric', 'Count'], [
                ['Updated (author/file)', $enriched['updated']],
                ['Failed', $enriched['failed']],
                ['Total scraped', $enriched['total']],
            ]);
        }

        return self::SUCCESS;
    }
}
