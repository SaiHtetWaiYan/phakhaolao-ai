<?php

namespace App\Ai\Tools;

use App\Models\Species;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FilterSpecies implements Tool
{
    /**
     * Filterable attributes: type and the column(s) that hold the value.
     * Each is also an optional input parameter; provided ones are AND-combined.
     *
     * @var array<string, array{type: string, lao: string, en: ?string}>
     */
    private const ATTRIBUTES = [
        'category' => ['type' => 'scalar', 'lao' => 'category', 'en' => 'category_en'],
        'subcategory' => ['type' => 'scalar', 'lao' => 'subcategory', 'en' => 'subcategory_en'],
        'species_type' => ['type' => 'scalar', 'lao' => 'species_type', 'en' => 'species_type_en'],
        'family' => ['type' => 'scalar', 'lao' => 'family', 'en' => null],
        'invasiveness' => ['type' => 'scalar', 'lao' => 'invasiveness', 'en' => 'invasiveness_en'],
        'iucn_status' => ['type' => 'scalar', 'lao' => 'iucn_status', 'en' => 'iucn_status_en'],
        'national_conservation_status' => ['type' => 'scalar', 'lao' => 'national_conservation_status', 'en' => 'national_conservation_status_en'],
        'native_status' => ['type' => 'scalar', 'lao' => 'native_status', 'en' => 'native_status_en'],
        'domestication' => ['type' => 'scalar', 'lao' => 'domestication', 'en' => 'domestication_en'],
        'status' => ['type' => 'scalar', 'lao' => 'data_status', 'en' => 'data_status_en'],
        'use_type' => ['type' => 'json', 'lao' => 'use_types', 'en' => null],
        'ntfp' => ['type' => 'json_bilingual', 'lao' => 'ntfp_lists', 'en' => null],
        'timber' => ['type' => 'json_bilingual', 'lao' => 'timber_lists', 'en' => null],
        'habitat' => ['type' => 'json_bilingual', 'lao' => 'landscape_units', 'en' => null],
        'distribution' => ['type' => 'json_bilingual', 'lao' => 'distribution_units', 'en' => null],
        'province' => ['type' => 'json_object', 'lao' => 'provinces', 'en' => null],
    ];

    private const RESULT_LIMIT = 25;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List the actual species matching one or more exact attribute filters (combined with AND). '
            .'Use this when the user wants to SEE or LIST species by property, e.g. "show me the invasive species", '
            .'"list endangered birds", "birds in upland fields", "medicinal plants in Champasak". '
            .'Provide any combination of: category (Animals/Plants/Fungi), subcategory (Birds, Mammals, Fish...), '
            .'species_type, family, invasiveness, iucn_status, national_conservation_status, native_status, '
            .'use_type, habitat (landscape incl. sub-units like "Evergreen", "Upland fields", "Cliffs"), '
            .'distribution (distribution-in-Laos zone like "North Laos Highlands", "Vientiane Plain"), province. '
            .'Values may be English or Lao. Use SpeciesStats instead when the user only wants a count.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $filters = [];

        foreach (self::ATTRIBUTES as $attribute => $config) {
            $value = trim((string) ($request[$attribute] ?? ''));

            if ($value === '') {
                continue;
            }

            // Landscape units use a "Parent (Sub)" notation (e.g. "Forest (Evergreen)").
            if ($attribute === 'habitat' && preg_match('/^.+?\s*\((.+)\)\s*$/u', $value, $m)) {
                $value = trim($m[1]);
            }

            $filters[$attribute] = $value;
        }

        if ($filters === []) {
            return 'Provide at least one filter. Supported attributes: '.implode(', ', array_keys(self::ATTRIBUTES)).'.';
        }

        $query = Species::query()->where('scrape_status', 'scraped');

        foreach ($filters as $attribute => $value) {
            $this->applyFilter($query, self::ATTRIBUTES[$attribute], $value);
        }

        $total = (clone $query)->count();
        $describe = collect($filters)->map(fn ($v, $k) => "{$k} = '{$v}'")->implode(', ');

        if ($total === 0) {
            $hint = count($filters) === 1
                ? ' '.$this->availableValuesMessage(array_key_first($filters))
                : '';

            return "No species match: {$describe}.{$hint}";
        }

        $species = $query->orderBy('scientific_name')->limit(self::RESULT_LIMIT)->get();

        $header = "Found {$total} species matching {$describe}"
            .($total > self::RESULT_LIMIT ? ' (showing first '.self::RESULT_LIMIT.')' : '').':';

        $lines = [$header];

        foreach ($species as $s) {
            $name = $s->scientific_name ?: ($s->common_name_english ?: $s->common_name_lao ?: 'Unknown');
            $extra = array_filter([$s->common_name_english, $s->common_name_lao]);
            $suffix = $extra !== [] ? ' ('.implode(' / ', $extra).')' : '';
            $lines[] = "- {$name}{$suffix} — Source ID: {$s->source_id}"
                ."\n  https://species.phakhaolao.la/search/specie_details/{$s->source_id}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Builder<Species>  $query
     * @param  array{type: string, lao: string, en: ?string}  $config
     */
    private function applyFilter(Builder $query, array $config, string $value): void
    {
        $lower = mb_strtolower($value);

        match ($config['type']) {
            'scalar' => $query->where(function (Builder $q) use ($config, $lower): void {
                $q->whereRaw('LOWER('.$config['lao'].') = ?', [$lower]);
                if ($config['en'] !== null) {
                    $q->orWhereRaw('LOWER('.$config['en'].') = ?', [$lower]);
                }
            }),
            // JSON array of strings (use_types) — jsonb containment ignores \uXXXX escaping.
            'json' => $query->whereRaw($config['lao'].'::jsonb @> ?::jsonb', [
                json_encode([$value], JSON_UNESCAPED_UNICODE),
            ]),
            // JSON array of objects keyed by "name" (provinces).
            'json_object' => $query->whereRaw($config['lao'].'::jsonb @> ?::jsonb', [
                json_encode([['name' => $value]], JSON_UNESCAPED_UNICODE),
            ]),
            // JSON array of {lao, en} objects — match either language, case-insensitive.
            'json_bilingual' => $query->whereRaw(
                "EXISTS (SELECT 1 FROM jsonb_array_elements({$config['lao']}::jsonb) e"
                ." WHERE LOWER(e->>'lao') = ? OR LOWER(e->>'en') = ?)",
                [$lower, $lower]
            ),
            default => null,
        };
    }

    private function availableValuesMessage(string $attribute): string
    {
        $config = self::ATTRIBUTES[$attribute];

        if ($config['type'] !== 'scalar') {
            return '';
        }

        $column = $config['en'] ?? $config['lao'];

        $values = Species::query()
            ->where('scrape_status', 'scraped')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->limit(30)
            ->pluck($column);

        return $values->isEmpty() ? '' : 'Available values: '.$values->implode(', ').'.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->description('Top-level category: Animals, Plants, Fungi, or Algae.'),
            'subcategory' => $schema->string()->description('Subcategory, e.g. Birds, Mammals, Fish, Herpetofauna, Arthropods, Woody plants.'),
            'species_type' => $schema->string()->description('Finer species type, e.g. Trees and palms, Insects, Reptiles.'),
            'family' => $schema->string()->description('Taxonomic family, e.g. Rutaceae.'),
            'invasiveness' => $schema->string()->description('Invasive or Not invasive.'),
            'iucn_status' => $schema->string()->description('IUCN status, e.g. Endangered, Vulnerable, Least concern.'),
            'national_conservation_status' => $schema->string()->description('Lao national conservation status (List I/II/III).'),
            'native_status' => $schema->string()->description('Native status, e.g. Endemic, Native, Non-native.'),
            'domestication' => $schema->string()->description('Domestication: Domesticated, Wild, or Both.'),
            'status' => $schema->string()->description('Data completeness status: Complete, Near complete, Partial, or Basic.'),
            'use_type' => $schema->string()->description('A use/utilisation type (value as stored, often Lao).'),
            'ntfp' => $schema->string()->description('NTFP list classification, e.g. "List I", "List II", "List III".'),
            'timber' => $schema->string()->description('Timber list classification, e.g. "List I", "List II", "List III".'),
            'habitat' => $schema->string()->description('Landscape/habitat unit or sub-unit, e.g. Forest, Evergreen, Upland fields, Cliffs.'),
            'distribution' => $schema->string()->description('Distribution-in-Laos zone, e.g. North Laos Highlands, Vientiane Plain, Central Annamites, Boloven Plateau.'),
            'province' => $schema->string()->description('A Lao province name where the species is recorded.'),
        ];
    }
}
