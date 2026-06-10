<?php

namespace App\Ai\Tools;

use App\Models\LibraryResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchLibrary implements Tool
{
    /**
     * @var list<string>
     */
    private const TEXT_COLUMNS = [
        'title',
        'author',
        'description',
        'resource_type',
    ];

    private const LIMIT = 6;

    public function description(): Stringable|string
    {
        return 'Search the PhaKhaoLao digital library — publications, books, research articles, reports, '
            .'guidelines, and other resources about Lao agrobiodiversity. Search by title, topic, resource '
            .'type (book, report, research article, guideline), or any keyword. Returns the resource title, '
            .'type, topics, and a link to the resource page (where the document can be downloaded).';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));
        $language = strtolower(trim((string) ($request['language'] ?? '')));

        if ($query === '') {
            return 'Please provide a search term (a topic, title, or resource type).';
        }

        $resources = LibraryResource::query()
            ->when(in_array($language, ['en', 'lo'], true), fn (Builder $q) => $q->where('language', $language))
            ->where(function (Builder $outer) use ($query): void {
                foreach (self::TEXT_COLUMNS as $column) {
                    $outer->orWhere($column, 'like', "%{$query}%");
                }
                $outer->orWhereRaw('topics::text ilike ?', ["%{$query}%"]);
            })
            ->orderByRaw('CASE WHEN title ilike ? THEN 0 ELSE 1 END', ["%{$query}%"])
            ->orderByDesc('featured')
            ->limit(self::LIMIT)
            ->get();

        if ($resources->isEmpty()) {
            return "No library resources found matching '{$query}'. Try a topic, a resource type "
                .'(book, report, research article, guideline), or a different keyword.';
        }

        return $resources->map(fn (LibraryResource $r) => $this->format($r))->implode("\n---\n");
    }

    private function format(LibraryResource $resource): string
    {
        $parts = ["**{$resource->title}**"];

        if ($resource->author) {
            $parts[] = "Author(s): {$resource->author}";
        }
        if ($resource->resource_type) {
            $parts[] = "Type: {$resource->resource_type}";
        }
        if ($resource->resource_language) {
            $parts[] = "Language: {$resource->resource_language}";
        }
        if (is_array($resource->topics) && $resource->topics !== []) {
            $parts[] = 'Topics: '.collect($resource->topics)->filter()->implode(', ');
        }
        if ($resource->description) {
            $parts[] = mb_strimwidth($resource->description, 0, 400, '...');
        }
        if ($resource->file_url) {
            $parts[] = "Download: {$resource->file_url}";
        }
        if ($resource->source_url) {
            $parts[] = "Resource page: {$resource->source_url}";
        }

        return implode("\n", $parts);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('Search term: topic, title, resource type (book, report, research article, guideline), or keyword.')
                ->required(),
            'language' => $schema
                ->string()
                ->description('Optional language to restrict results to: "en" or "lo". Match the user\'s language.'),
        ];
    }
}
