<?php

namespace App\Ai\Tools;

use App\Models\Story;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchStories implements Tool
{
    /**
     * @var list<string>
     */
    private const TEXT_COLUMNS = [
        'title',
        'authors',
        'summary',
        'story',
    ];

    private const LIMIT = 5;

    public function description(): Stringable|string
    {
        return 'Search the PhaKhaoLao stories — articles and field stories about Lao agrobiodiversity, '
            .'farming, health, nutrition, culture, and communities. Search by title, author, story type '
            .'(e.g. farming, health), or any keyword. Returns the story with a summary and a link to read it.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));
        $language = strtolower(trim((string) ($request['language'] ?? '')));

        if ($query === '') {
            return 'Please provide a search term (a topic, title, author, or story type).';
        }

        $stories = Story::query()
            ->when(in_array($language, ['en', 'lo'], true), fn (Builder $q) => $q->where('language', $language))
            ->where(function (Builder $outer) use ($query): void {
                foreach (self::TEXT_COLUMNS as $column) {
                    $outer->orWhere($column, 'like', "%{$query}%");
                }
                $outer->orWhereRaw('story_types::text ilike ?', ["%{$query}%"]);
            })
            ->orderByRaw('CASE WHEN title ilike ? THEN 0 ELSE 1 END', ["%{$query}%"])
            ->limit(self::LIMIT)
            ->get();

        if ($stories->isEmpty()) {
            return "No stories found matching '{$query}'. Try a topic, an author, or a story type "
                .'(e.g. farming, health, nutrition).';
        }

        // A single match means a specific story — return its full text so the
        // assistant can answer in detail. Broad searches return short snippets.
        $full = $stories->count() === 1;

        return $stories->map(fn (Story $s) => $this->format($s, $full))->implode("\n---\n");
    }

    private function format(Story $story, bool $full = false): string
    {
        $parts = ["**{$story->title}**"];

        if ($story->authors) {
            $parts[] = "Author(s): {$story->authors}";
        }
        if (is_array($story->story_types) && $story->story_types !== []) {
            $parts[] = 'Type: '.collect($story->story_types)->filter()->implode(', ');
        }

        if ($full && $story->story) {
            $parts[] = mb_strimwidth($story->story, 0, 9000, '...');
        } elseif ($body = ($story->summary ?: $story->story)) {
            $parts[] = mb_strimwidth($body, 0, 500, '...');
        }

        if ($story->source_url) {
            $parts[] = "Read the full story: {$story->source_url}";
        }

        return implode("\n", $parts);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('Search term: topic, title, author, story type, or keyword.')
                ->required(),
            'language' => $schema
                ->string()
                ->description('Optional language to restrict results to: "en" or "lo". Match the user\'s language.'),
        ];
    }
}
