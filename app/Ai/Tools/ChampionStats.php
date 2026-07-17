<?php

namespace App\Ai\Tools;

use App\Models\Champion;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ChampionStats implements Tool
{
    /**
     * Plain columns grouped directly; JSON columns are expanded per value.
     *
     * @var array<string, string>
     */
    private const DIMENSIONS = [
        'province' => 'province',
        'actor' => 'category_actor',
        'sector' => 'sectors',
        'topic' => 'topics',
    ];

    private const JSON_DIMENSIONS = ['sector', 'topic'];

    public function description(): Stringable|string
    {
        return 'Count PhaKhaoLao agrobiodiversity champions grouped by a dimension. Use this for '
            .'"how many champions per province", "champions by actor type / sector / topic", or the total. '
            .'group_by is one of: province, actor (private sector, academia, government, NGO, ...), sector, '
            .'topic. Omit group_by for the overall total. Pass language="en" or "lo".';
    }

    public function handle(Request $request): Stringable|string
    {
        $language = trim((string) ($request['language'] ?? '')) === 'lo' ? 'lo' : 'en';
        $groupBy = strtolower(trim((string) ($request['group_by'] ?? '')));

        $base = Champion::query()->where('language', $language);
        $total = (clone $base)->count();

        if ($total === 0) {
            return 'No champions have been imported yet. Run "php artisan champions:import".';
        }

        if ($groupBy === '' || ! isset(self::DIMENSIONS[$groupBy])) {
            return "There are {$total} champion profiles ({$language}).";
        }

        $column = self::DIMENSIONS[$groupBy];
        $counts = in_array($groupBy, self::JSON_DIMENSIONS, true)
            ? $this->countJson($base->pluck($column))
            : $this->countColumn($base->pluck($column));

        if ($counts === []) {
            return "No champions have a {$groupBy} recorded.";
        }

        arsort($counts);

        $lines = collect($counts)
            ->map(fn (int $count, string $label) => "- {$label}: {$count}")
            ->implode("\n");

        return "Champions by {$groupBy} (of {$total} total):\n{$lines}";
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $values
     * @return array<string, int>
     */
    private function countColumn($values): array
    {
        $counts = [];

        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $values
     * @return array<string, int>
     */
    private function countJson($values): array
    {
        $counts = [];

        foreach ($values as $items) {
            foreach ((array) $items as $item) {
                $item = trim((string) $item);

                if ($item !== '') {
                    $counts[$item] = ($counts[$item] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'group_by' => $schema->string()->description('One of: province, actor, sector, topic. Omit for the total.'),
            'language' => $schema->string()->description('Champion language: "en" for English, "lo" for Lao.'),
        ];
    }
}
