<?php

namespace App\Console\Commands;

use App\Models\LibraryResource;
use App\Services\LibraryPdfIndexer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class IndexLibraryPdfs extends Command
{
    protected $signature = 'library:index-pdfs
        {--limit=0 : Maximum number of PDFs to process (0 = no limit)}
        {--all : Reprocess resources already marked done}
        {--retry-failed : Also retry resources previously marked failed/no_text}
        {--delay=1500 : Delay between PDFs in milliseconds (be polite to the host)}
        {--chunk-size=1200 : Characters per text chunk}
        {--dimensions=1536 : Embedding vector dimensions}
        {--no-store : Do not save PDFs to disk (download to memory and discard)}';

    protected $description = 'Download library PDFs, extract their text, and embed it for full-text search';

    public function handle(): int
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql' || ! Schema::hasColumn('library_chunks', 'embedding')) {
            $this->error('library_chunks.embedding (pgvector) was not found. Run the migration on PostgreSQL first.');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $delayMs = max(0, (int) $this->option('delay'));
        $indexer = new LibraryPdfIndexer(
            chunkSize: max(200, (int) $this->option('chunk-size')),
            dimensions: max(1, (int) $this->option('dimensions')),
            store: ! $this->option('no-store'),
        );

        $query = LibraryResource::query()
            ->whereNotNull('file_url')
            ->where('file_url', 'like', '%phakhaolao.la%')
            ->where('file_url', 'like', '%.pdf')
            ->orderBy('id');

        if (! $this->option('all')) {
            $statuses = $this->option('retry-failed') ? ['done'] : ['done', 'failed', 'no_text'];
            $query->where(function ($q) use ($statuses) {
                $q->whereNull('pdf_status')->orWhereNotIn('pdf_status', $statuses);
            });
        }

        $total = $limit > 0 ? min($limit, (clone $query)->count()) : (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to index — all matching PDFs are already processed.');

            return self::SUCCESS;
        }

        $this->info("Indexing {$total} PDF(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $counts = ['done' => 0, 'skipped' => 0, 'no_text' => 0, 'failed' => 0];
        $processed = 0;

        foreach ($query->cursor() as $resource) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $status = $indexer->index($resource, force: (bool) $this->option('all'));
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $processed++;
            $bar->advance();

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->table(
            ['Indexed', 'Skipped', 'No text (scanned)', 'Failed'],
            [[$counts['done'], $counts['skipped'], $counts['no_text'], $counts['failed']]]
        );

        return self::SUCCESS;
    }
}
