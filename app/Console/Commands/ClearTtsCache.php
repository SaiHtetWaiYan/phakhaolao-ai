<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearTtsCache extends Command
{
    protected $signature = 'tts:clear-cache
        {--days=7 : Delete cached audio older than this many days (0 = delete all)}';

    protected $description = 'Delete cached text-to-speech audio files';

    public function handle(): int
    {
        $directory = storage_path('app/tts-cache');

        if (! is_dir($directory)) {
            $this->info('No TTS cache directory — nothing to clear.');

            return self::SUCCESS;
        }

        $days = max(0, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->getTimestamp();
        $deleted = 0;
        $bytes = 0;

        foreach (glob($directory.'/*.mp3') ?: [] as $file) {
            if ($days === 0 || filemtime($file) < $cutoff) {
                $bytes += (int) filesize($file);

                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        $this->info("Cleared {$deleted} cached file(s), freed ".round($bytes / 1048576, 2).' MB.');

        return self::SUCCESS;
    }
}
