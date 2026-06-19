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
            .'farming, health, nutrition, culture, and communities. Filter by query (title/author/keyword) '
            .'and/or story_type (the category: Farming, Health, Enterprise, Culture and local knowledge, '
            .'Sustainability, Research and Education, Policy, ...). At least one of query or story_type is '
            .'required. Pass language="en" or "lo" to match the user\'s language. Returns the story with a '
            .'summary and a link to read it.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));
        $type = trim((string) ($request['story_type'] ?? ''));
        $language = strtolower(trim((string) ($request['language'] ?? '')));

        if ($query === '' && $type === '') {
            return 'Please provide a search term or a story_type (e.g. Farming, Health, Enterprise).';
        }

        $lowerQuery = mb_strtolower($query);

        $stories = Story::query()
            ->when(in_array($language, ['en', 'lo'], true), fn (Builder $q) => $q->where('language', $language))
            ->when($query !== '', function (Builder $q) use ($query): void {
                // Match every word (AND) across the text columns so multi-word
                // queries still match when the words are not contiguous.
                foreach ($this->queryWords($query) as $word) {
                    $q->where(function (Builder $outer) use ($word): void {
                        foreach (self::TEXT_COLUMNS as $column) {
                            $outer->orWhereRaw("lower({$column}) like ?", ['%'.$word.'%']);
                        }
                        $outer->orWhereRaw('lower(cast(story_types as text)) like ?', ['%'.$word.'%']);
                    });
                }
            })
            ->when($type !== '', fn (Builder $q) => $q->whereRaw('lower(cast(story_types as text)) like ?', ['%'.mb_strtolower($type).'%']))
            ->when($query !== '', fn (Builder $q) => $q->orderByRaw('CASE WHEN lower(title) like ? THEN 0 ELSE 1 END', ["%{$lowerQuery}%"]))
            ->limit(self::LIMIT)
            ->get();

        if ($stories->isEmpty()) {
            $criteria = $query !== '' ? "'{$query}'" : "type '{$type}'";

            return "No stories found matching {$criteria}. Try a topic, an author, or a story type "
                .'(e.g. Farming, Health, Enterprise).';
        }

        // A single match means a specific story — return its full text so the
        // assistant can answer in detail. Broad searches return short snippets.
        $full = $stories->count() === 1;

        return $stories->map(fn (Story $s) => $this->format($s, $full))->implode("\n---\n");
    }

    /**
     * @return list<string>
     */
    private function queryWords(string $query): array
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [],
            fn (string $word): bool => mb_strlen($word) >= 2
        ));

        return $words === [] ? [mb_strtolower(trim($query))] : $words;
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
            $parts[] = "[Read the full story]({$story->source_url})";
        }

        return implode("\n", $parts);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('Search term: topic, title, author, or keyword. Optional if story_type is given.'),
            'story_type' => $schema
                ->string()
                ->description('Story category, e.g. Farming, Health, Enterprise, Culture and local knowledge, Sustainability.'),
            'language' => $schema
                ->string()
                ->description('Optional language to restrict results to: "en" or "lo". Match the user\'s language.'),
        ];
    }
}
