<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExports extends Command
{
    protected $signature = 'exports:cleanup {--hours=1 : Delete files older than this many hours}';

    protected $description = 'Delete generated export files older than the specified age';

    public function handle(): int
    {
        $maxAgeHours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($maxAgeHours)->getTimestamp();
        $disk = Storage::disk('local');
        $directory = 'exports/generated';

        if (! $disk->exists($directory)) {
            $this->info('No exports directory found. Nothing to clean.');

            return self::SUCCESS;
        }

        $files = $disk->files($directory);
        $deleted = 0;

        foreach ($files as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} expired export file(s) older than {$maxAgeHours} hour(s).");

        return self::SUCCESS;
    }
}
