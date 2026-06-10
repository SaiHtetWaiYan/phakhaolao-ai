<?php

namespace App\Console\Commands;

use App\Services\StoryImporter;
use Illuminate\Console\Command;

class ImportStories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stories:import {--dry-run : Fetch and map without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync stories from the PhaKhaoLao WordPress REST API';

    public function handle(StoryImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Importing stories (dry run)...' : 'Importing stories...');

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

        return self::SUCCESS;
    }
}
