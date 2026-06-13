<?php

namespace App\Ai\Tools;

use App\Models\Story;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class StoryStats implements Tool
{
    public function description(): Stringable|string
    {
        return 'Return how many PhaKhaoLao stories exist per story type/category (Farming, Health, '
            .'Enterprise, Culture and local knowledge, Sustainability, Research and Education, Policy, ...). '
            .'These match the website\'s "Types" filter counts. Use this for "how many farming stories" or '
            .'"list the story categories with counts". Pass language="en" for English categories, "lo" for '
            .'Lao categories. Optionally pass value to count one category (e.g. value="Farming").';
    }

    public function handle(Request $request): Stringable|string
    {
        $language = trim((string) ($request['language'] ?? '')) === 'lo' ? 'lo' : 'en';
        $value = trim((string) ($request['value'] ?? ''));

        $counts = [];

        foreach (Story::query()->where('language', $language)->pluck('story_types') as $types) {
            foreach ((array) $types as $type) {
                $type = trim((string) $type);

                if ($type !== '') {
                    $counts[$type] = ($counts[$type] ?? 0) + 1;
                }
            }
        }

        if ($counts === []) {
            return 'No stories have been imported yet. Run "php artisan stories:import".';
        }

        arsort($counts);

        if ($value !== '') {
            foreach ($counts as $type => $count) {
                if (mb_stripos($type, $value) !== false) {
                    return "{$type}: {$count} stor".($count === 1 ? 'y' : 'ies').'.';
                }
            }

            return "No \"{$value}\" story category found.";
        }

        $lines = collect($counts)
            ->map(fn (int $count, string $type) => "- {$type}: {$count}")
            ->implode("\n");

        return "Story counts by category:\n".$lines;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'language' => $schema->string()->description('Category language: "en" for English, "lo" for Lao.'),
            'value' => $schema->string()->description('Optional single category to count, e.g. "Farming" or "ການກະເສດ".'),
        ];
    }
}
