<?php

namespace App\Console\Commands;

use App\Services\SpeciesImporter;
use Illuminate\Console\Command;

class ImportSpecies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'species:import
        {--dry-run : Map and count records without writing to the database}
        {--limit=0 : Limit the number of species to import (0 = all)}
        {--connection=pkl : Source database connection name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync species from the source (pkl) database into the flat species table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $importer = new SpeciesImporter(
            connection: (string) $this->option('connection'),
            mediaBaseUrl: (string) config('species.source_base_url', 'https://species.phakhaolao.la'),
        );

        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info($dryRun ? 'Running species import (dry run)...' : 'Running species import...');

        try {
            $result = $importer->import($dryRun, $limit);
        } catch (\Throwable $e) {
            $this->error('Import aborted: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Source published', $result['source']],
            ['Imported', $result['imported']],
            ['Changed (re-embed)', $result['changed']],
            ['Archived (missing from source)', $result['archived']],
        ]);

        if ($dryRun) {
            $this->warn('Dry run: no changes were written.');
        } else {
            $this->info('Done. Run "php artisan species:embed" to update embeddings for changed rows.');
        }

        return self::SUCCESS;
    }
}
