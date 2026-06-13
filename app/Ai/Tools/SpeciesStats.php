<?php

namespace App\Ai\Tools;

use App\Models\Species;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SpeciesStats implements Tool
{
    /**
     * Supported grouping dimensions mapped to their [lao column, english column].
     *
     * @var array<string, array{0: string, 1: ?string}>
     */
    private const DIMENSIONS = [
        'category' => ['category', 'category_en'],
        'subcategory' => ['subcategory', 'subcategory_en'],
        'species_type' => ['species_type', 'species_type_en'],
        'family' => ['family', null],
        'iucn_status' => ['iucn_status', 'iucn_status_en'],
        'national_conservation_status' => ['national_conservation_status', 'national_conservation_status_en'],
        'native_status' => ['native_status', 'native_status_en'],
        'invasiveness' => ['invasiveness', 'invasiveness_en'],
        'domestication' => ['domestication', 'domestication_en'],
        'status' => ['data_status', 'data_status_en'],
    ];

    /**
     * Natural-language aliases that resolve to a canonical dimension.
     *
     * @var array<string, string>
     */
    private const DIMENSION_ALIASES = [
        'iucn' => 'iucn_status',
        'iucn status' => 'iucn_status',
        'conservation' => 'iucn_status',
        'conservation status' => 'iucn_status',
        'threatened' => 'iucn_status',
        'endangered' => 'iucn_status',
        'national status' => 'national_conservation_status',
        'national conservation' => 'national_conservation_status',
        'protected' => 'national_conservation_status',
        'endemism' => 'native_status',
        'native' => 'native_status',
        'endemic' => 'native_status',
        'invasive' => 'invasiveness',
        'invasiveness' => 'invasiveness',
        'domestication' => 'domestication',
        'domesticated' => 'domestication',
        'wild' => 'domestication',
        'completeness' => 'status',
        'data status' => 'status',
        'family' => 'family',
        'subcategory' => 'subcategory',
        'sub category' => 'subcategory',
        'type' => 'species_type',
        'species type' => 'species_type',
    ];

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get exact species counts grouped by a dimension from the database. '
            .'Use this for any "how many" or "break down by" question, including counts by '
            .'category, subcategory, family, IUCN status (endangered/threatened), national conservation status, '
            .'native status (endemic/native), or invasiveness (invasive species). '
            .'Always use this instead of estimating numbers yourself.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $dimension = $this->resolveDimension((string) ($request['dimension'] ?? 'category'));

        if ($dimension === null) {
            return 'Unknown dimension. Supported dimensions: '.implode(', ', array_keys(self::DIMENSIONS)).'.';
        }

        [$laoColumn, $enColumn] = self::DIMENSIONS[$dimension];

        $categoryInput = trim((string) ($request['category'] ?? ''));
        $categoryFilter = $categoryInput !== '' ? $this->resolveCategory($categoryInput) : null;

        if ($categoryInput !== '' && $categoryFilter === null) {
            return "Unknown category '{$categoryInput}'. Valid categories are: ".$this->validCategories().'.';
        }

        $base = Species::query()
            ->where('scrape_status', 'scraped')
            ->when($categoryFilter, fn ($q) => $q->where('category', $categoryFilter));

        $total = (clone $base)->count();

        $selects = [$laoColumn];
        $groupBy = [$laoColumn];
        if ($enColumn !== null) {
            $selects[] = $enColumn;
            $groupBy[] = $enColumn;
        }

        $rows = (clone $base)
            ->whereNotNull($laoColumn)
            ->where($laoColumn, '!=', '')
            ->selectRaw(implode(', ', $selects).', count(*) as total')
            ->groupBy($groupBy)
            ->orderByDesc('total')
            ->limit($dimension === 'family' ? 30 : 100)
            ->get();

        $scope = $categoryFilter ? ' within '.$this->categoryLabel($categoryFilter) : '';
        $header = "Species count by {$dimension}{$scope} (total {$total} species, {$rows->count()} groups):";
        $lines = [$header];

        foreach ($rows as $row) {
            $english = $enColumn !== null ? ($row->{$enColumn} ?? null) : null;
            $lines[] = '- '.$this->label($english, $row->{$laoColumn}).': '.$row->total.' species';
        }

        return implode("\n", $lines);
    }

    private function resolveDimension(string $input): ?string
    {
        $input = mb_strtolower(trim($input));

        if (isset(self::DIMENSIONS[$input])) {
            return $input;
        }

        return self::DIMENSION_ALIASES[$input] ?? null;
    }

    private function categoryLabel(string $lao): string
    {
        $en = Species::query()->where('category', $lao)->value('category_en');

        return $this->label($en, $lao);
    }

    /**
     * Resolve a free-text category (English or Lao, singular or plural) to its
     * canonical Lao value using the categories that actually exist in the database.
     */
    private function resolveCategory(string $input): ?string
    {
        $input = mb_strtolower(trim($input));

        foreach ($this->categoryPairs() as $pair) {
            foreach ([$pair['lo'], $pair['en']] as $value) {
                $value = mb_strtolower(trim($value));

                if ($value !== '' && ($value === $input || $value === $input.'s' || $input === $value.'s')) {
                    return $pair['lo'];
                }
            }
        }

        return null;
    }

    private function validCategories(): string
    {
        $labels = collect($this->categoryPairs())
            ->map(fn (array $pair) => $pair['en'] !== '' ? $pair['en'] : $pair['lo'])
            ->filter()
            ->implode(', ');

        return $labels !== '' ? $labels : 'none available';
    }

    /**
     * @return list<array{lo: string, en: string}>
     */
    private function categoryPairs(): array
    {
        return Species::query()
            ->where('scrape_status', 'scraped')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->select('category', 'category_en')
            ->distinct()
            ->get()
            ->map(fn (Species $s): array => [
                'lo' => (string) $s->category,
                'en' => (string) ($s->category_en ?? ''),
            ])
            ->all();
    }

    private function label(?string $english, string $lao): string
    {
        $english = $english !== null ? trim($english) : null;

        return ($english !== null && $english !== '') ? "{$english} ({$lao})" : $lao;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'dimension' => $schema
                ->string()
                ->description('What to group by: category, subcategory, family, iucn_status, national_conservation_status, native_status, or invasiveness. Defaults to category.'),
            'category' => $schema
                ->string()
                ->description('Optional scope filter to one top-level category (e.g. animal, plant, or fungi).'),
        ];
    }
}
