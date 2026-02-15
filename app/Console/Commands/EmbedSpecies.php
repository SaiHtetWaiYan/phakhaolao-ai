<?php

namespace App\Console\Commands;

use App\Models\Species;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Embeddings;
use Throwable;

class EmbedSpecies extends Command
{
    protected $signature = 'species:embed
        {--chunk=25 : Number of species to process per batch}
        {--limit=0 : Maximum number of species to process (0 = no limit)}
        {--all : Process species even when embedding already exists}
        {--include-unscraped : Include records that are not marked as scraped}
        {--provider= : Embeddings provider name (defaults to ai.default_for_embeddings)}
        {--model= : Embeddings model name}
        {--dimensions=1536 : Embedding vector dimensions}
        {--max-chars=6000 : Max characters per species document}
        {--delay=0 : Delay between batches in milliseconds}
        {--dry-run : Build payloads without calling the embeddings API}';

    protected $description = 'Generate and backfill vector embeddings for species records';

    public function handle(): int
    {
        if (! Schema::hasTable('species') || ! Schema::hasColumn('species', 'embedding')) {
            $this->error('The species.embedding column was not found. Run your PostgreSQL vector migration first.');

            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));
        $maxChars = max(500, (int) $this->option('max-chars'));
        $delayMs = max(0, (int) $this->option('delay'));
        $dimensions = max(1, (int) $this->option('dimensions'));
        $provider = $this->normalizeStringOption('provider');
        $model = $this->normalizeStringOption('model');
        $dryRun = (bool) $this->option('dry-run');

        $query = Species::query()->orderBy('id');

        if (! $this->option('include-unscraped')) {
            $query->where('scrape_status', 'scraped');
        }

        if (! $this->option('all')) {
            $query->whereNull('embedding');
        }

        $totalCandidates = (clone $query)->count();
        $targetTotal = $limit > 0 ? min($limit, $totalCandidates) : $totalCandidates;

        if ($targetTotal === 0) {
            $this->warn('No species matched the current filters.');

            return self::SUCCESS;
        }

        $this->info("Processing {$targetTotal} species (chunk={$chunk}, dry-run=".($dryRun ? 'yes' : 'no').')...');

        $bar = $this->output->createProgressBar($targetTotal);
        $bar->start();

        $processed = 0;
        $updated = 0;
        $failed = 0;

        $query->chunkById($chunk, function (Collection $speciesBatch) use (
            &$processed,
            &$updated,
            &$failed,
            $targetTotal,
            $dimensions,
            $provider,
            $model,
            $dryRun,
            $maxChars,
            $delayMs,
            $bar
        ) {
            $remaining = $targetTotal - $processed;
            if ($remaining <= 0) {
                return false;
            }

            $batch = $speciesBatch->take($remaining)->values();
            $documents = $batch
                ->map(fn (Species $species): string => $this->buildEmbeddingDocument($species, $maxChars))
                ->all();

            $embeddings = $dryRun
                ? array_fill(0, count($documents), [])
                : $this->generateBatchEmbeddings($documents, $dimensions, $provider, $model);

            foreach ($batch as $index => $species) {
                $embedding = $embeddings[$index] ?? null;

                if (! $dryRun && ! is_array($embedding)) {
                    $failed++;
                } elseif (! $dryRun) {
                    $species->forceFill(['embedding' => $embedding])->save();
                    $updated++;
                } else {
                    $updated++;
                }

                $processed++;
                $bar->advance();
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            return $processed < $targetTotal;
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Processed: {$processed}, Updated: {$updated}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array<float>|null>
     */
    private function generateBatchEmbeddings(
        array $documents,
        int $dimensions,
        ?string $provider,
        ?string $model
    ): array {
        if ($documents === []) {
            return [];
        }

        try {
            $pending = Embeddings::for($documents)->dimensions($dimensions);
            $response = $pending->generate($provider, $model);

            return $response->embeddings;
        } catch (Throwable $e) {
            $this->warn('Batch embedding failed; retrying one-by-one: '.$e->getMessage());
        }

        $results = [];

        foreach ($documents as $document) {
            try {
                $pending = Embeddings::for([$document])->dimensions($dimensions);
                $response = $pending->generate($provider, $model);
                $results[] = $response->first();
            } catch (Throwable $e) {
                $results[] = null;
            }
        }

        return $results;
    }

    private function buildEmbeddingDocument(Species $species, int $maxChars): string
    {
        $lines = [];

        $this->addLine($lines, 'Scientific name', $species->scientific_name);
        $this->addLine($lines, 'Lao name', $species->common_name_lao);
        $this->addLine($lines, 'English name', $species->common_name_english);
        $this->addLine($lines, 'Family', $species->family);
        $this->addLine($lines, 'IUCN status', $species->iucn_status);
        $this->addLine($lines, 'Native status', $species->native_status);
        $this->addLine($lines, 'Invasiveness', $species->invasiveness);
        $this->addLine($lines, 'Use description', $species->use_description);
        $this->addLine($lines, 'Botanical description', $species->botanical_description);
        $this->addLine($lines, 'Global distribution', $species->global_distribution);
        $this->addLine($lines, 'Lao distribution', $species->lao_distribution);
        $this->addLine($lines, 'Cultivation info', $species->cultivation_info);
        $this->addLine($lines, 'Market data', $species->market_data);
        $this->addLine($lines, 'Management info', $species->management_info);
        $this->addLine($lines, 'Threats', $species->threats);
        $this->addLine($lines, 'Harvest season', $species->harvest_season);

        $this->addArrayLine($lines, 'Local names', $species->local_names);
        $this->addArrayLine($lines, 'Synonyms', $species->synonyms);
        $this->addArrayLine($lines, 'Related species', $species->related_species);
        $this->addArrayLine($lines, 'Habitat types', $species->habitat_types);
        $this->addArrayLine($lines, 'Use types', $species->use_types);

        if (is_array($species->nutrition) && $species->nutrition !== []) {
            $nutrition = collect($species->nutrition)
                ->map(function (mixed $item): ?string {
                    if (! is_array($item)) {
                        return null;
                    }

                    $nutrient = $this->sanitizeText($item['nutrient'] ?? null);
                    $value = $this->sanitizeText((string) ($item['value_per_100g'] ?? $item['value'] ?? ''));

                    if (! $nutrient && ! $value) {
                        return null;
                    }

                    return trim($nutrient.' '.$value);
                })
                ->filter()
                ->implode(', ');

            $this->addLine($lines, 'Nutrition', $nutrition);
        }

        $document = implode("\n", $lines);
        $document = mb_substr($document, 0, $maxChars);

        return $document !== '' ? $document : 'Species record';
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function addLine(array &$lines, string $label, mixed $value): void
    {
        $text = $this->sanitizeText($value);

        if ($text !== null) {
            $lines[] = "{$label}: {$text}";
        }
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function addArrayLine(array &$lines, string $label, mixed $value): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        $parts = collect($value)
            ->map(fn (mixed $item) => $this->sanitizeText(is_scalar($item) ? (string) $item : null))
            ->filter()
            ->values()
            ->all();

        if ($parts !== []) {
            $lines[] = "{$label}: ".implode(', ', $parts);
        }
    }

    private function sanitizeText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (! mb_check_encoding($text, 'UTF-8')) {
            $converted = function_exists('iconv')
                ? @iconv('UTF-8', 'UTF-8//IGNORE', $text)
                : false;
            $text = $converted !== false ? $converted : mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return $text !== '' ? $text : null;
    }

    private function normalizeStringOption(string $option): ?string
    {
        $value = trim((string) $this->option($option));

        return $value !== '' ? $value : null;
    }
}
