<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SourceDatabase
{
    /**
     * Whether the "pkl" species source database can be reached from this server.
     *
     * Scheduled species syncs call this before running so they stay dormant —
     * without erroring nightly — until the source database is co-located.
     */
    public static function reachable(): bool
    {
        try {
            DB::connection('pkl')->getPdo();

            return true;
        } catch (Throwable $e) {
            Log::info('Species source database not reachable; skipping scheduled species sync.', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
