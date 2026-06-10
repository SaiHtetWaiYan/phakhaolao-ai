<?php

namespace App\Ai\Tools;

use App\Models\Champion;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchChampions implements Tool
{
    /**
     * @var list<string>
     */
    private const TEXT_COLUMNS = [
        'name',
        'summary',
        'story',
        'authors',
        'category_actor',
        'province',
    ];

    /**
     * @var list<string>
     */
    private const JSON_COLUMNS = [
        'sectors',
        'topics',
        'scales',
    ];

    private const LIMIT = 6;

    public function description(): Stringable|string
    {
        return 'Search the PhaKhaoLao agrobiodiversity champions — people and organizations recognised for '
            .'their work in Lao agrobiodiversity. Search by name, sector (e.g. farming, coffee, trade), topic, '
            .'province, actor type (private sector, cooperative, researcher), or any keyword. '
            .'Returns champion profiles with their story.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));
        $language = strtolower(trim((string) ($request['language'] ?? '')));

        if ($query === '') {
            return 'Please provide a search term (a name, sector, province, or topic).';
        }

        $champions = Champion::query()
            ->when(in_array($language, ['en', 'lo'], true), fn (Builder $q) => $q->where('language', $language))
            ->where(function (Builder $outer) use ($query): void {
                foreach (self::TEXT_COLUMNS as $column) {
                    $outer->orWhere($column, 'like', "%{$query}%");
                }
                foreach (self::JSON_COLUMNS as $column) {
                    $outer->orWhereRaw("{$column}::text ilike ?", ["%{$query}%"]);
                }
            })
            ->orderByRaw('CASE WHEN name ilike ? THEN 0 ELSE 1 END', ["%{$query}%"])
            ->limit(self::LIMIT)
            ->get();

        if ($champions->isEmpty()) {
            return "No champions found matching '{$query}'. Try a sector (farming, coffee), a province, "
                .'an actor type (private sector, cooperative, researcher), or a name.';
        }

        return $champions->map(fn (Champion $c) => $this->format($c))->implode("\n---\n");
    }

    private function format(Champion $champion): string
    {
        $parts = ["**{$champion->name}**"];

        if ($champion->category_actor) {
            $parts[] = "Type: {$champion->category_actor}";
        }
        if ($champion->province) {
            $parts[] = "Province: {$champion->province}";
        }
        if ($sectors = $this->joinList($champion->sectors)) {
            $parts[] = "Sectors: {$sectors}";
        }
        if ($topics = $this->joinList($champion->topics)) {
            $parts[] = "Topics: {$topics}";
        }
        if ($champion->authors) {
            $parts[] = "Authors: {$champion->authors}";
        }
        if ($champion->story) {
            $parts[] = 'Story: '.mb_strimwidth($champion->story, 0, 600, '...');
        }
        if ($champion->source_url) {
            $parts[] = "Read more: {$champion->source_url}";
        }

        return implode("\n", $parts);
    }

    private function joinList(mixed $value): ?string
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return collect($value)->filter()->implode(', ') ?: null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('Search term: champion name, sector, topic, province, actor type, or keyword.')
                ->required(),
            'language' => $schema
                ->string()
                ->description('Optional language to restrict results to: "en" or "lo". Match the user\'s language.'),
        ];
    }
}
