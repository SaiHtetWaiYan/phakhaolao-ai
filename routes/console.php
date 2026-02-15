<?php

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Species;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

use function Laravel\Ai\agent;

Schedule::command('exports:cleanup')->hourly();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('chat:cleanup-legacy-guests {--dry-run : Show counts only, no deletion}', function () {
    $conversationIds = AgentConversation::query()
        ->whereNull('user_id')
        ->whereNull('guest_token')
        ->pluck('id');

    $conversationsCount = $conversationIds->count();
    $messagesCount = $conversationsCount > 0
        ? AgentConversationMessage::query()->whereIn('conversation_id', $conversationIds)->count()
        : 0;

    $this->info("Legacy guest conversations found: {$conversationsCount}");
    $this->info("Legacy guest messages found: {$messagesCount}");

    if ($this->option('dry-run')) {
        $this->comment('Dry run complete. No data deleted.');

        return self::SUCCESS;
    }

    if ($conversationsCount === 0) {
        $this->comment('Nothing to delete.');

        return self::SUCCESS;
    }

    DB::transaction(function () use ($conversationIds): void {
        AgentConversationMessage::query()
            ->whereIn('conversation_id', $conversationIds)
            ->delete();

        AgentConversation::query()
            ->whereIn('id', $conversationIds)
            ->delete();
    });

    $this->info("Deleted {$conversationsCount} conversations and {$messagesCount} messages.");

    return self::SUCCESS;
})->purpose('Delete legacy shared guest conversations created before guest_token support');

Artisan::command('species:export-scientific-names {--path= : Optional output path}', function () {
    $relativePath = $this->option('path');
    if (! is_string($relativePath) || trim($relativePath) === '') {
        $relativePath = 'exports/species_scientific_names_'.now()->format('Ymd_His').'.csv';
    }

    $relativePath = trim($relativePath);

    $absolutePath = storage_path('app/'.$relativePath);
    $absoluteDirectory = dirname($absolutePath);
    if (! is_dir($absoluteDirectory) && ! mkdir($absoluteDirectory, 0775, true) && ! is_dir($absoluteDirectory)) {
        $this->error("Cannot create directory: {$absoluteDirectory}");

        return self::FAILURE;
    }

    $handle = fopen($absolutePath, 'wb');
    if ($handle === false) {
        $this->error("Cannot write file: {$absolutePath}");

        return self::FAILURE;
    }

    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, ['No', 'Source ID', 'Scientific Name']);

    $counter = 0;
    Species::query()
        ->select(['source_id', 'scientific_name'])
        ->whereNotNull('scientific_name')
        ->orderBy('scientific_name')
        ->chunk(500, function ($rows) use (&$counter, $handle): void {
            foreach ($rows as $row) {
                $counter++;
                fputcsv($handle, [$counter, $row->source_id, $row->scientific_name]);
            }
        });

    fclose($handle);
    $this->info("Exported {$counter} rows to {$absolutePath}");

    return self::SUCCESS;
})->purpose('Export all species scientific names to an Excel-compatible CSV file');

Artisan::command('species:export-all {--path= : Optional output path}', function () {
    $relativePath = $this->option('path');
    if (! is_string($relativePath) || trim($relativePath) === '') {
        $relativePath = 'exports/species_full_export_'.now()->format('Ymd_His').'.csv';
    }

    $relativePath = trim($relativePath);
    $absolutePath = storage_path('app/'.$relativePath);
    $absoluteDirectory = dirname($absolutePath);

    if (! is_dir($absoluteDirectory) && ! mkdir($absoluteDirectory, 0775, true) && ! is_dir($absoluteDirectory)) {
        $this->error("Cannot create directory: {$absoluteDirectory}");

        return self::FAILURE;
    }

    $handle = fopen($absolutePath, 'wb');

    if ($handle === false) {
        $this->error("Cannot write file: {$absolutePath}");

        return self::FAILURE;
    }

    $columns = [
        'source_id',
        'scientific_name',
        'common_name_lao',
        'common_name_english',
        'family',
        'iucn_status',
        'native_status',
        'invasiveness',
        'data_collection_level',
        'harvest_season',
        'local_names',
        'synonyms',
        'related_species',
        'habitat_types',
        'use_types',
        'nutrition',
        'botanical_description',
        'global_distribution',
        'lao_distribution',
        'use_description',
        'cultivation_info',
        'market_data',
        'management_info',
        'threats',
        'nutrition_description',
        'scrape_status',
        'scrape_error',
        'scraped_at',
        'created_at',
        'updated_at',
    ];

    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, array_map(
        fn (string $column) => str($column)->replace('_', ' ')->title()->value(),
        $columns
    ));

    $counter = 0;
    Species::query()
        ->select($columns)
        ->orderBy('source_id')
        ->chunk(500, function ($rows) use (&$counter, $handle, $columns): void {
            foreach ($rows as $row) {
                $line = [];

                foreach ($columns as $column) {
                    $value = $row->{$column};
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                    } elseif ($value instanceof \DateTimeInterface) {
                        $value = $value->format('Y-m-d H:i:s');
                    } elseif ($value === null) {
                        $value = '';
                    }

                    $line[] = (string) $value;
                }

                fputcsv($handle, $line);
                $counter++;
            }
        });

    fclose($handle);
    $this->info("Exported {$counter} rows to {$absolutePath}");

    return self::SUCCESS;
})->purpose('Export full species data to an Excel-compatible CSV file');

Artisan::command('species:export-ai
    {--path= : Optional output path}
    {--limit=0 : Max rows to export (0 = all)}
    {--offset=0 : Rows to skip before exporting}
    {--provider= : AI provider name (defaults to ai.default)}
    {--model= : AI model name}
    {--batch=20 : Species per AI call}
    {--delay=0 : Delay between AI batch calls in milliseconds}
    {--retries=2 : Retries per AI batch call if it fails}', function () {
    $relativePath = $this->option('path');
    if (! is_string($relativePath) || trim($relativePath) === '') {
        $relativePath = 'exports/species_ai_export_'.now()->format('Ymd_His').'.csv';
    }

    $relativePath = trim($relativePath);
    $absolutePath = storage_path('app/'.$relativePath);
    $absoluteDirectory = dirname($absolutePath);

    if (! is_dir($absoluteDirectory) && ! mkdir($absoluteDirectory, 0775, true) && ! is_dir($absoluteDirectory)) {
        $this->error("Cannot create directory: {$absoluteDirectory}");

        return self::FAILURE;
    }

    $handle = fopen($absolutePath, 'wb');
    if ($handle === false) {
        $this->error("Cannot write file: {$absolutePath}");

        return self::FAILURE;
    }

    $limit = max(0, (int) $this->option('limit'));
    $offset = max(0, (int) $this->option('offset'));
    $batchSize = max(1, (int) $this->option('batch'));
    $delayMs = max(0, (int) $this->option('delay'));
    $retries = max(0, (int) $this->option('retries'));
    $provider = $this->option('provider');
    $provider = is_string($provider) && trim($provider) !== '' ? trim($provider) : null;
    $model = $this->option('model');
    $model = is_string($model) && trim($model) !== '' ? trim($model) : null;

    $columns = [
        'source_id',
        'scientific_name',
        'common_name_lao',
        'common_name_english',
        'family',
        'iucn_status',
        'native_status',
        'invasiveness',
        'data_collection_level',
        'harvest_season',
        'local_names',
        'synonyms',
        'related_species',
        'habitat_types',
        'use_types',
        'nutrition',
        'botanical_description',
        'global_distribution',
        'lao_distribution',
        'use_description',
        'cultivation_info',
        'market_data',
        'management_info',
        'threats',
        'nutrition_description',
        'scrape_status',
        'scraped_at',
    ];

    $query = Species::query()->select($columns)->orderBy('source_id');
    $total = (clone $query)->count();

    if ($offset > 0) {
        $query->skip($offset);
    }

    if ($limit > 0) {
        $query->take($limit);
    }

    $targetTotal = $limit > 0 ? min($limit, max(0, $total - $offset)) : max(0, $total - $offset);
    if ($targetTotal <= 0) {
        fclose($handle);
        $this->warn('No species matched the selected offset/limit.');

        return self::SUCCESS;
    }

    fwrite($handle, "\xEF\xBB\xBF");
    $headers = array_merge(
        array_map(fn (string $column) => str($column)->replace('_', ' ')->title()->value(), $columns),
        ['AI Summary', 'AI Category', 'AI Primary Use', 'AI Risk Level', 'AI Keywords', 'AI Confidence', 'AI Error']
    );
    fputcsv($handle, $headers);

    $this->info("Exporting {$targetTotal} species with AI enrichment...");
    $bar = $this->output->createProgressBar($targetTotal);
    $bar->start();

    $processed = 0;
    $failed = 0;

    $query->chunk($batchSize, function ($rows) use (
        &$processed,
        &$failed,
        $targetTotal,
        $batchSize,
        $columns,
        $handle,
        $provider,
        $model,
        $retries,
        $delayMs,
        $bar
    ) {
        $remaining = $targetTotal - $processed;
        if ($remaining <= 0) {
            return false;
        }

        $batchRows = $rows->take(min($batchSize, $remaining))->values();
        $payloads = $batchRows->map(fn ($row) => [
            'source_id' => (int) $row->source_id,
            'scientific_name' => (string) ($row->scientific_name ?? ''),
            'common_name_lao' => (string) ($row->common_name_lao ?? ''),
            'common_name_english' => (string) ($row->common_name_english ?? ''),
            'family' => (string) ($row->family ?? ''),
            'iucn_status' => (string) ($row->iucn_status ?? ''),
            'native_status' => (string) ($row->native_status ?? ''),
            'invasiveness' => (string) ($row->invasiveness ?? ''),
            'use_types' => is_array($row->use_types) ? $row->use_types : [],
            'habitat_types' => is_array($row->habitat_types) ? $row->habitat_types : [],
            'botanical_description' => mb_substr((string) ($row->botanical_description ?? ''), 0, 900),
            'use_description' => mb_substr((string) ($row->use_description ?? ''), 0, 900),
            'threats' => mb_substr((string) ($row->threats ?? ''), 0, 500),
        ])->all();

        $aiBySourceId = [];
        $batchError = '';
        $attempt = 0;

        while ($attempt <= $retries) {
            try {
                $attempt++;
                $response = agent(
                    instructions: 'You are a biodiversity data analyst. Return concise factual outputs only.',
                    schema: fn ($schema) => [
                        'items' => $schema->array()->items(
                            $schema->object([
                                'source_id' => $schema->integer()->required(),
                                'summary' => $schema->string()->required(),
                                'category' => $schema->string()->enum(['plant', 'animal', 'fungus', 'other', 'unknown'])->required(),
                                'primary_use' => $schema->string()->enum(['food', 'medicine', 'timber', 'ornamental', 'ecological', 'multiple', 'other', 'unknown'])->required(),
                                'risk_level' => $schema->string()->enum(['low', 'medium', 'high', 'unknown'])->required(),
                                'keywords' => $schema->array()->items($schema->string())->max(8)->required(),
                                'confidence' => $schema->number()->min(0)->max(1)->required(),
                            ])->withoutAdditionalProperties()
                        )->required(),
                    ]
                )->prompt(
                    'Analyze each species record and return one structured result per record. '.
                    'Rules: summary max 45 words; keywords 3-8 short terms; no markdown; if uncertain use unknown. '.
                    'Input JSON: '.json_encode($payloads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    provider: $provider,
                    model: $model,
                );

                $structured = $response->toArray();
                $items = $structured['items'] ?? [];
                if (! is_array($items)) {
                    $items = [];
                }

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $id = isset($item['source_id']) ? (string) $item['source_id'] : '';
                    if ($id === '') {
                        continue;
                    }

                    $keywords = $item['keywords'] ?? [];
                    if (! is_array($keywords)) {
                        $keywords = [];
                    }

                    $aiBySourceId[$id] = [
                        'summary' => trim((string) ($item['summary'] ?? '')),
                        'category' => trim((string) ($item['category'] ?? 'unknown')),
                        'primary_use' => trim((string) ($item['primary_use'] ?? 'unknown')),
                        'risk_level' => trim((string) ($item['risk_level'] ?? 'unknown')),
                        'keywords' => array_values(array_filter(array_map(fn ($k) => trim((string) $k), $keywords), fn ($k) => $k !== '')),
                        'confidence' => (string) ($item['confidence'] ?? ''),
                        'error' => '',
                    ];
                }

                break;
            } catch (\Throwable $e) {
                $batchError = $e->getMessage();

                if ($attempt > $retries) {
                    break;
                }

                usleep(500000);
            }
        }

        foreach ($batchRows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $value = $row->{$column};
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                } elseif ($value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                } elseif ($value === null) {
                    $value = '';
                }
                $line[] = (string) $value;
            }

            $default = [
                'summary' => '',
                'category' => 'unknown',
                'primary_use' => 'unknown',
                'risk_level' => 'unknown',
                'keywords' => [],
                'confidence' => '',
                'error' => $batchError !== '' ? $batchError : 'No AI response for this row',
            ];

            $ai = $aiBySourceId[(string) $row->source_id] ?? $default;
            if (($ai['error'] ?? '') !== '') {
                $failed++;
            }

            $line[] = $ai['summary'] ?? '';
            $line[] = $ai['category'] ?? 'unknown';
            $line[] = $ai['primary_use'] ?? 'unknown';
            $line[] = $ai['risk_level'] ?? 'unknown';
            $line[] = implode(' | ', is_array($ai['keywords'] ?? null) ? $ai['keywords'] : []);
            $line[] = $ai['confidence'] ?? '';
            $line[] = $ai['error'] ?? '';

            fputcsv($handle, $line);
            $processed++;
            $bar->advance();
        }

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        return $processed < $targetTotal;
    });

    $bar->finish();
    fclose($handle);
    $this->newLine();
    $this->info("Done. Exported {$processed} rows. Failed AI rows: {$failed}");
    $this->info("File: {$absolutePath}");

    return self::SUCCESS;
})->purpose('Export full species data with AI summary and AI classification columns');
