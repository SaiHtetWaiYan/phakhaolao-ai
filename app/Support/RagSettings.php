<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;

class RagSettings
{
    /**
     * @return array{min_similarity: float, semantic_limit: int, keyword_limit: int}
     */
    public static function all(): array
    {
        $defaults = [
            'min_similarity' => (float) config('ai.rag.min_similarity', 0.35),
            'semantic_limit' => (int) config('ai.rag.semantic_limit', 6),
            'keyword_limit' => (int) config('ai.rag.keyword_limit', 8),
        ];

        if (! self::tableExists()) {
            return $defaults;
        }

        $rows = AppSetting::query()
            ->whereIn('key', [
                'rag.min_similarity',
                'rag.semantic_limit',
                'rag.keyword_limit',
            ])
            ->pluck('value', 'key');

        return [
            'min_similarity' => self::toFloat($rows->get('rag.min_similarity'), $defaults['min_similarity']),
            'semantic_limit' => self::toInt($rows->get('rag.semantic_limit'), $defaults['semantic_limit']),
            'keyword_limit' => self::toInt($rows->get('rag.keyword_limit'), $defaults['keyword_limit']),
        ];
    }

    /**
     * @param  array{min_similarity: float, semantic_limit: int, keyword_limit: int}  $values
     */
    public static function save(array $values): void
    {
        if (! self::tableExists()) {
            return;
        }

        AppSetting::query()->upsert([
            ['key' => 'rag.min_similarity', 'value' => (string) $values['min_similarity']],
            ['key' => 'rag.semantic_limit', 'value' => (string) $values['semantic_limit']],
            ['key' => 'rag.keyword_limit', 'value' => (string) $values['keyword_limit']],
        ], ['key'], ['value', 'updated_at']);
    }

    private static function tableExists(): bool
    {
        return Schema::hasTable('app_settings');
    }

    private static function toFloat(mixed $value, float $fallback): float
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return (float) $value;
    }

    private static function toInt(mixed $value, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return (int) $value;
    }
}

