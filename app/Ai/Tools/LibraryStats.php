<?php

namespace App\Ai\Tools;

use App\Services\LibraryFilterCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LibraryStats implements Tool
{
    /** Question dimension mapped to the catalog key holding its counted options. */
    private const DIMENSIONS = [
        'language' => 'languages',
        'languages' => 'languages',
        'resource_language' => 'languages',
        'type' => 'types',
        'types' => 'types',
        'resource_type' => 'types',
        'topic' => 'topics',
        'topics' => 'topics',
    ];

    public function __construct(private readonly LibraryFilterCatalog $catalog = new LibraryFilterCatalog) {}

    public function description(): Stringable|string
    {
        return 'Return the EXACT published counts from the PhaKhaoLao library filter bar — how many '
            .'library resources exist per document language, per resource type, or per topic. These are '
            .'the website\'s own authoritative facet counts, so use this for "how many ... in the library" '
            .'questions instead of counting search results. Choose dimension (language, type, or topic) and '
            .'language ("en" for the English catalogue counts, "lo" for the Lao catalogue counts — they are '
            .'different collections). Optionally pass value to get one facet\'s count (e.g. value="Book"). '
            .'Note: these are single-facet totals; combined filters (e.g. Lao AND Book) are not available here '
            .'— use SearchLibrary for those.';
    }

    public function handle(Request $request): Stringable|string
    {
        $dimension = self::DIMENSIONS[mb_strtolower(trim((string) ($request['dimension'] ?? '')))] ?? null;

        if ($dimension === null) {
            return 'Specify dimension as "language", "type", or "topic".';
        }

        $language = trim((string) ($request['language'] ?? '')) === 'lo' ? 'lo' : 'en';
        $value = trim((string) ($request['value'] ?? ''));

        $catalog = $this->catalog->get($language);

        if ($catalog === null || ($catalog[$dimension] ?? []) === []) {
            return 'Library filter counts are not available yet. Run "php artisan library:import" to sync them.';
        }

        $scope = $language === 'lo' ? 'Lao catalogue' : 'English catalogue';

        $counts = collect($catalog[$dimension])
            ->map(fn (array $option): array => [
                'label' => $option['value'],
                'count' => $this->extractCount($option['label']),
            ]);

        if ($value !== '') {
            $match = $counts->first(fn (array $c) => mb_stripos($c['label'], $value) !== false);

            if ($match === null) {
                return "No \"{$value}\" {$this->dimensionNoun($dimension)} found in the {$scope}.";
            }

            return "{$match['label']}: {$match['count']} resource".($match['count'] === 1 ? '' : 's')." ({$scope}).";
        }

        $lines = $counts
            ->sortByDesc('count')
            ->map(fn (array $c) => "- {$c['label']}: {$c['count']}")
            ->implode("\n");

        return "Library resource counts by {$this->dimensionNoun($dimension)} ({$scope}):\n".$lines;
    }

    private function extractCount(string $label): int
    {
        return preg_match('/\((\d+)\)\s*$/', $label, $matches) === 1 ? (int) $matches[1] : 0;
    }

    private function dimensionNoun(string $dimension): string
    {
        return match ($dimension) {
            'languages' => 'language',
            'types' => 'type',
            default => 'topic',
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'dimension' => $schema->string()->description('What to count by: "language", "type", or "topic".'),
            'language' => $schema->string()->description('Catalogue language: "en" for English counts, "lo" for Lao counts.'),
            'value' => $schema->string()->description('Optional single facet to count, e.g. "Lao", "Book", or "ປຶ້ມ".'),
        ];
    }
}
