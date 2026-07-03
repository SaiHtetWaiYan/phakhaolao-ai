<?php

namespace App\Console\Commands;

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
        {--dry-run : Fetch and map without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync library resources from the PhaKhaoLao WordPress REST API';

    public function handle(LibraryImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');

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
            ['Filter pages synced', $result['filters_synced']],
            ['Filter pages failed', $result['filters_failed']],
        ]);

        return self::SUCCESS;
    }
}
