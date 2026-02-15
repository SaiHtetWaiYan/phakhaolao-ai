<?php

namespace App\Ai\Tools;

use App\Models\Species;
use App\Support\RagSettings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchSpecies implements Tool
{
    /**
     * @var list<string>
     */
    private const SEARCH_COLUMNS = [
        'source_id',
        'scientific_name',
        'common_name_lao',
        'common_name_english',
        'family',
        'category',
        'subcategory',
        'species_type',
        'data_collection_level',
        'botanical_description',
        'lao_distribution',
        'global_distribution',
        'local_names',
        'synonyms',
        'related_species',
        'use_description',
        'use_types',
        'habitat_types',
        'iucn_status',
        'national_conservation_status',
        'management_info',
        'threats',
        'nutrition_description',
    ];

    /**
     * English/Lao category mappings.
     *
     * @var array<string, string>
     */
    private const CATEGORY_MAP = [
        'animal' => 'ສັດ',
        'animals' => 'ສັດ',
        'plant' => 'ພືດ',
        'plants' => 'ພືດ',
        'fungi' => 'ເຫັດ',
        'fungus' => 'ເຫັດ',
        'ສັດ' => 'ສັດ',
        'ພືດ' => 'ພືດ',
        'ເຫັດ' => 'ເຫັດ',
    ];

    /**
     * English/Lao subcategory mappings.
     *
     * @var array<string, string>
     */
    private const SUBCATEGORY_MAP = [
        'fish' => 'ປາ',
        'fishes' => 'ປາ',
        'mammal' => 'ສັດລ້ຽງລູກດ້ວຍນົມ',
        'mammals' => 'ສັດລ້ຽງລູກດ້ວຍນົມ',
        'bird' => 'ນົກ',
        'birds' => 'ນົກ',
        'reptile' => 'ສັດເລືອຄານ',
        'reptiles' => 'ສັດເລືອຄານ',
        'amphibian' => 'ສັດເຄິ່ງບົກເຄິ່ງນ້ຳ',
        'amphibians' => 'ສັດເຄິ່ງບົກເຄິ່ງນ້ຳ',
        'insect' => 'ແມງໄມ້',
        'insects' => 'ແມງໄມ້',
        'tree' => 'ໄມ້ຢືນຕົ້ນ',
        'trees' => 'ໄມ້ຢືນຕົ້ນ',
        'ປາ' => 'ປາ',
        'ນົກ' => 'ນົກ',
        'ສັດລ້ຽງລູກດ້ວຍນົມ' => 'ສັດລ້ຽງລູກດ້ວຍນົມ',
        'ສັດເລືອຄານ' => 'ສັດເລືອຄານ',
        'ສັດເຄິ່ງບົກເຄິ່ງນ້ຳ' => 'ສັດເຄິ່ງບົກເຄິ່ງນ້ຳ',
        'ແມງໄມ້' => 'ແມງໄມ້',
        'ໄມ້ຢືນຕົ້ນ' => 'ໄມ້ຢືນຕົ້ນ',
    ];

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search the Lao species database by name (scientific, English, or Lao), family, category (animal/plant/fungi), subcategory (fish/bird/mammal/reptile/tree/insect), use type, habitat, IUCN status, or national conservation status. Returns detailed species information.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) $request['query']);
        $rag = RagSettings::all();
        $limit = max(1, (int) $rag['keyword_limit']);
        $semanticLimit = max(1, (int) $rag['semantic_limit']);
        $concise = $this->isListQuery($query);

        if ($query === '') {
            return 'Please provide a search term.';
        }

        $categoryFilter = $this->detectCategory($query);
        $subcategoryFilter = $this->detectSubcategory($query);

        // For category/subcategory-only queries, browse by category instead of keyword search
        if ($categoryFilter || $subcategoryFilter) {
            $species = $this->runCategorySearch($limit, $categoryFilter, $subcategoryFilter);

            if ($species->isNotEmpty()) {
                return $species->map(fn (Species $s) => $this->formatSpecies($s, $concise))->implode("\n---\n");
            }
        }

        $keywords = $this->extractKeywords($query);
        $searchTerms = array_values(array_unique(array_filter([$query, ...$keywords])));

        $keywordResults = $this->runKeywordSearch($searchTerms, $limit * 2);
        $semanticResults = $this->shouldUseSemanticSearch($query)
            ? $this->runSemanticSearch($query, $semanticLimit)
            : collect();
        $species = $this->mergeAndRankSpecies($query, $searchTerms, $keywordResults, $semanticResults, $limit);

        if ($species->isEmpty()) {
            return "No species found matching '{$query}'. Try searching with a different term — you can use scientific names, English names, Lao names, family names, or use types.";
        }

        return $species->map(fn (Species $s) => $this->formatSpecies($s, $concise))->implode("\n---\n");
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('Search term: scientific name, English name, Lao name, family, use type, habitat, or IUCN status.')
                ->required(),
        ];
    }

    /**
     * @param  list<string>  $terms
     */
    private function runCategorySearch(int $limit, ?string $categoryFilter, ?string $subcategoryFilter): Collection
    {
        return Species::query()
            ->where('scrape_status', 'scraped')
            ->when($categoryFilter, fn ($q) => $q->where('category', $categoryFilter))
            ->when($subcategoryFilter, fn ($q) => $q->where('subcategory', $subcategoryFilter))
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<string>  $terms
     */
    private function runKeywordSearch(array $terms, int $limit): Collection
    {
        return Species::query()
            ->where('scrape_status', 'scraped')
            ->where(function ($outer) use ($terms) {
                foreach ($terms as $term) {
                    $outer->orWhere(function ($inner) use ($term) {
                        foreach (self::SEARCH_COLUMNS as $column) {
                            $inner->orWhere($column, 'like', "%{$term}%");
                        }
                    });
                }
            })
            ->limit($limit)
            ->get();
    }

    private function runSemanticSearch(string $query, int $limit): Collection
    {
        $rag = RagSettings::all();

        return Species::query()
            ->where('scrape_status', 'scraped')
            ->whereNotNull('embedding')
            ->whereVectorSimilarTo(
                'embedding',
                $query,
                minSimilarity: (float) $rag['min_similarity']
            )
            ->limit($limit)
            ->get();
    }

    /**
     * Detect category filter only when the query is primarily about the category
     * (no other meaningful non-stopword keywords remain).
     */
    private function detectCategory(string $query): ?string
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [];
        $categoryWord = null;

        foreach ($words as $word) {
            if (isset(self::CATEGORY_MAP[$word])) {
                $categoryWord = $word;
                break;
            }
        }

        if ($categoryWord === null) {
            return null;
        }

        // Only filter by category when remaining words are all stopwords/category terms
        if (! $this->isCategoryOnlyQuery($words)) {
            return null;
        }

        return self::CATEGORY_MAP[$categoryWord];
    }

    /**
     * Detect subcategory filter only when the query is primarily about the subcategory.
     */
    private function detectSubcategory(string $query): ?string
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [];
        $subcategoryWord = null;

        foreach ($words as $word) {
            if (isset(self::SUBCATEGORY_MAP[$word])) {
                $subcategoryWord = $word;
                break;
            }
        }

        if ($subcategoryWord === null) {
            return null;
        }

        if (! $this->isCategoryOnlyQuery($words)) {
            return null;
        }

        return self::SUBCATEGORY_MAP[$subcategoryWord];
    }

    /**
     * Check if a query consists only of category/subcategory terms and stopwords.
     *
     * @param  list<string>  $words
     */
    private function isCategoryOnlyQuery(array $words): bool
    {
        $stopwords = [
            'tell', 'me', 'about', 'what', 'is', 'the', 'a', 'an', 'for', 'in', 'on', 'and', 'of',
            'show', 'find', 'search', 'species', 'please', 'list', 'some', 'all', 'lao', 'laos',
        ];

        foreach ($words as $word) {
            if (isset(self::CATEGORY_MAP[$word]) || isset(self::SUBCATEGORY_MAP[$word])) {
                continue;
            }
            if (in_array($word, $stopwords, true)) {
                continue;
            }
            if (mb_strlen($word) < 2) {
                continue;
            }

            // A meaningful keyword exists — don't filter by category
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function extractKeywords(string $query): array
    {
        $normalized = mb_strtolower($query);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
        $parts = preg_split('/\s+/u', trim($normalized)) ?: [];

        $stopwords = [
            'tell', 'me', 'about', 'what', 'is', 'the', 'a', 'an', 'for', 'in', 'on', 'and', 'of',
            'show', 'find', 'search', 'species', 'please',
        ];

        $keywords = array_values(array_unique(array_filter(
            $parts,
            fn (string $part) => mb_strlen($part) >= 2 && ! in_array($part, $stopwords, true)
        )));

        $expanded = [];
        $joined = ' '.implode(' ', $keywords).' ';

        $expansions = [
            'medicinal' => ['medicine', 'herb', 'herbal', 'ພືດເປັນຢາ'],
            'edible' => ['food', 'ອາຫານ'],
            'endangered' => ['threatened', 'iucn', 'ໃກ້ຖືກຄຸກຄາມ'],
            'invasive' => ['ຮຸກຮານ'],
            'plant' => ['ພືດ'],
            'plants' => ['ພືດ'],
            'animal' => ['ສັດ'],
            'animals' => ['ສັດ'],
            'fish' => ['ປາ'],
            'bird' => ['ນົກ'],
            'birds' => ['ນົກ'],
            'tree' => ['ໄມ້ຢືນຕົ້ນ'],
            'trees' => ['ໄມ້ຢືນຕົ້ນ'],
        ];

        foreach ($expansions as $seed => $aliases) {
            if (str_contains($joined, ' '.$seed.' ')) {
                $expanded = [...$expanded, $seed, ...$aliases];
            }
        }

        return array_values(array_unique([...$keywords, ...$expanded]));
    }

    private function shouldUseSemanticSearch(string $query): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return false;
        }

        if (! Schema::hasTable('species') || ! Schema::hasColumn('species', 'embedding')) {
            return false;
        }

        $wordCount = count(preg_split('/\s+/u', trim($query)) ?: []);

        return $wordCount >= 2;
    }

    private function isListQuery(string $query): bool
    {
        $normalized = mb_strtolower($query);
        $markers = ['list', 'show', 'some', 'examples', 'recommend', 'ລາຍຊື່', 'ແນະນຳ'];

        foreach ($markers as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function mergeAndRankSpecies(
        string $query,
        array $terms,
        Collection $keywordResults,
        Collection $semanticResults,
        int $limit
    ): Collection {
        $normalizedQuery = mb_strtolower($query);
        $semanticIds = $semanticResults->pluck('id')->flip();

        return $keywordResults
            ->concat($semanticResults)
            ->unique('id')
            ->sortByDesc(function (Species $species) use ($normalizedQuery, $semanticIds, $terms): int {
                $score = 0;

                if (isset($semanticIds[$species->id])) {
                    $score += 100;
                }

                $scientific = mb_strtolower($species->scientific_name ?? '');
                $english = mb_strtolower($species->common_name_english ?? '');
                $lao = mb_strtolower($species->common_name_lao ?? '');
                $family = mb_strtolower($species->family ?? '');

                if ($scientific === $normalizedQuery || $english === $normalizedQuery || $lao === $normalizedQuery) {
                    $score += 80;
                } elseif (
                    str_contains($scientific, $normalizedQuery) ||
                    str_contains($english, $normalizedQuery) ||
                    str_contains($lao, $normalizedQuery)
                ) {
                    $score += 40;
                } elseif (str_contains($family, $normalizedQuery)) {
                    $score += 20;
                }

                $useTypes = mb_strtolower(implode(' ', array_filter($species->use_types ?? [])));
                $corpus = implode(' ', [
                    $scientific,
                    $english,
                    $lao,
                    $family,
                    mb_strtolower((string) ($species->botanical_description ?? '')),
                    mb_strtolower((string) ($species->use_description ?? '')),
                    $useTypes,
                    mb_strtolower((string) ($species->iucn_status ?? '')),
                ]);

                foreach ($terms as $term) {
                    $term = mb_strtolower($term);
                    if (mb_strlen($term) < 2) {
                        continue;
                    }

                    if (str_contains($useTypes, $term)) {
                        $score += 14;
                    } elseif (str_contains($corpus, $term)) {
                        $score += 6;
                    }
                }

                return $score;
            })
            ->take($limit)
            ->values();
    }

    private function formatSpecies(Species $species, bool $concise = false): string
    {
        $parts = [];
        $scientificName = $this->cleanText($species->scientific_name) ?? 'Unknown scientific name';
        $parts[] = "**{$scientificName}**";

        if ($commonNameLao = $this->cleanText($species->common_name_lao)) {
            $parts[] = "Lao name: {$commonNameLao}";
        }
        if ($commonNameEnglish = $this->cleanText($species->common_name_english)) {
            $parts[] = "English name: {$commonNameEnglish}";
        }
        if ($family = $this->cleanText($species->family)) {
            $parts[] = "Family: {$family}";
        }
        if ($category = $this->cleanText($species->category)) {
            $parts[] = "Category: {$category}";
        }
        if ($subcategory = $this->cleanText($species->subcategory)) {
            $parts[] = "Subcategory: {$subcategory}";
        }
        if ($speciesType = $this->cleanText($species->species_type)) {
            $parts[] = "Species type: {$speciesType}";
        }
        if ($sourceId = $species->source_id) {
            $parts[] = "Source ID: {$sourceId}";
            $parts[] = "Website: [PhaKhaoLao species record](https://species.phakhaolao.la/search/specie_details/{$sourceId})";
        }
        if ($dataCollectionLevel = $this->cleanText($species->data_collection_level)) {
            $parts[] = "Data level: {$dataCollectionLevel}";
        }
        if ($iucnStatus = $this->cleanText($species->iucn_status)) {
            $parts[] = "IUCN status: {$iucnStatus}";
        }
        if ($nationalStatus = $this->cleanText($species->national_conservation_status)) {
            $parts[] = "National conservation status: {$nationalStatus}";
        }
        if ($nativeStatus = $this->cleanText($species->native_status)) {
            $parts[] = "Native status: {$nativeStatus}";
        }
        if ($invasiveness = $this->cleanText($species->invasiveness)) {
            $parts[] = "Invasiveness: {$invasiveness}";
        }
        if ($botanicalDescription = $this->cleanText($species->botanical_description)) {
            $parts[] = 'Description: '.$this->truncateText($botanicalDescription, $concise ? 260 : 420);
        }
        if (! empty($species->use_types)) {
            $cleanUseTypes = array_values(array_filter(
                array_map(fn ($v) => $this->cleanText(is_string($v) ? $v : null), $species->use_types)
            ));
            if ($cleanUseTypes !== []) {
                $parts[] = 'Use types: '.implode(', ', $cleanUseTypes);
            }
        }
        if ($useDescription = $this->cleanText($species->use_description)) {
            $parts[] = 'Use details: '.$this->truncateText($useDescription, $concise ? 220 : 380);
        }
        if ($localNames = $this->formatStringArray($species->local_names ?? null, $concise ? 5 : 10)) {
            $parts[] = "Local names: {$localNames}";
        }
        if ($synonyms = $this->formatStringArray($species->synonyms ?? null, $concise ? 5 : 10)) {
            $parts[] = "Synonyms: {$synonyms}";
        }
        if ($relatedSpecies = $this->formatStringArray($species->related_species ?? null, $concise ? 5 : 10)) {
            $parts[] = "Related species: {$relatedSpecies}";
        }
        if (! empty($species->habitat_types)) {
            $cleanHabitats = array_values(array_filter(
                array_map(fn ($v) => $this->cleanText(is_string($v) ? $v : null), $species->habitat_types)
            ));
            if ($cleanHabitats !== []) {
                $parts[] = 'Habitats: '.implode(', ', $cleanHabitats);
            }
        }
        if ($laoDistribution = $this->cleanText($species->lao_distribution)) {
            $parts[] = 'Lao distribution: '.$this->truncateText($laoDistribution, 220);
        }
        if ($globalDistribution = $this->cleanText($species->global_distribution)) {
            $parts[] = 'Distribution: '.$this->truncateText($globalDistribution, 220);
        }
        if ($harvestSeason = $this->cleanText($species->harvest_season)) {
            $parts[] = "Harvest season: {$harvestSeason}";
        }
        if (! empty($species->image_urls) && is_array($species->image_urls)) {
            $imageUrls = collect($species->image_urls)
                ->map(fn ($url) => is_string($url) ? trim($url) : null)
                ->filter(fn ($url) => is_string($url) && $url !== '')
                ->take($concise ? 3 : 6)
                ->values()
                ->all();
            if ($imageUrls !== []) {
                $imageLines = collect($imageUrls)
                    ->values()
                    ->map(fn (string $url, int $index) => "![{$scientificName} image ".($index + 1)."]({$url})")
                    ->implode("\n");
                $parts[] = "Images:\n{$imageLines}";
            }
        }
        if (! empty($species->map_urls) && is_array($species->map_urls)) {
            $mapUrls = collect($species->map_urls)
                ->map(fn ($url) => is_string($url) ? trim($url) : null)
                ->filter(fn ($url) => is_string($url) && $url !== '')
                ->take($concise ? 3 : 6)
                ->values();

            if ($mapUrls->isNotEmpty()) {
                $mapLinks = $mapUrls
                    ->values()
                    ->map(fn (string $url, int $index) => '- [Map '.($index + 1)."]({$url})")
                    ->implode("\n");

                $mapPreviews = $mapUrls
                    ->filter(fn (string $url) => (bool) preg_match('/\.(png|jpe?g|gif|webp)(\?.*)?$/i', $url))
                    ->values()
                    ->map(fn (string $url, int $index) => "![{$scientificName} map ".($index + 1)."]({$url})")
                    ->implode("\n");

                $parts[] = $mapPreviews !== ''
                    ? "Maps:\n{$mapLinks}\n{$mapPreviews}"
                    : "Maps:\n{$mapLinks}";
            }
        }
        if (! empty($species->nutrition)) {
            $nutritionStr = collect($species->nutrition)
                ->filter(fn ($n) => isset($n['nutrient']))
                ->map(function ($n) {
                    $nutrient = $this->cleanText((string) $n['nutrient']);
                    if (! $nutrient) {
                        return null;
                    }

                    $value = $this->cleanText((string) ($n['value_per_100g'] ?? $n['value'] ?? '?')) ?? '?';

                    return "{$nutrient}: {$value}/100g";
                })
                ->filter()
                ->filter(fn (string $line) => mb_strlen($line) <= 140 && str_contains($line, ':'))
                ->take($concise ? 4 : 6)
                ->implode(', ');
            if ($nutritionStr) {
                $parts[] = "Nutrition: {$nutritionStr}";
            }
        }
        if ($cultivationInfo = $this->cleanText($species->cultivation_info)) {
            $parts[] = 'Cultivation: '.$this->truncateText($cultivationInfo, 220);
        }
        if ($managementInfo = $this->cleanText($species->management_info)) {
            $parts[] = 'Management: '.$this->truncateText($managementInfo, 220);
        }
        if ($threats = $this->cleanText($species->threats)) {
            $parts[] = 'Threats: '.$this->truncateText($threats, 220);
        }
        if ($nutritionDescription = $this->cleanText($species->nutrition_description)) {
            $parts[] = 'Nutrition details: '.$this->truncateText($nutritionDescription, 200);
        }
        if ($marketData = $this->cleanText($species->market_data)) {
            $parts[] = 'Market: '.$this->truncateText($marketData, 180);
        }
        if ($references = $this->formatStringArray($species->references ?? null, $concise ? 4 : 8)) {
            $parts[] = "References: {$references}";
        }

        return implode("\n", $parts);
    }

    private function formatStringArray(mixed $value, int $limit = 10): ?string
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        $parts = collect($value)
            ->map(fn (mixed $item) => $this->cleanText(is_scalar($item) ? (string) $item : null))
            ->filter()
            ->take(max(1, $limit))
            ->values()
            ->all();

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function truncateText(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 3)).'...';
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            $converted = function_exists('iconv')
                ? @iconv('UTF-8', 'UTF-8//IGNORE', $value)
                : false;
            $value = $converted !== false ? $converted : mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
