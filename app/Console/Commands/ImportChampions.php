<?php

namespace App\Console\Commands;

use App\Services\ChampionImporter;
use Illuminate\Console\Command;

class ImportChampions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'champions:import {--dry-run : Fetch and map without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync agrobiodiversity champions from the PhaKhaoLao WordPress REST API';

    public function handle(ChampionImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Importing champions (dry run)...' : 'Importing champions...');

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
