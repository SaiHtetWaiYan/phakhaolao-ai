<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpeciesScraper
{
    private const BASE_URL = 'https://species.phakhaolao.la';

    /**
     * Lao labels to database column mapping.
     *
     * @var array<string, string>
     */
    private const LABEL_MAP = [
        'ຊື່ທ້ອງຖີ່ນ' => 'local_names',
        'ຊື່ພ້ອງ' => 'synonyms',
        'ຊື່ສະກຸນ' => 'family',
        'ຊະນິດໃກ້ຄຽງ' => 'related_species',
        'ບັນຍາຍລັກສະນະທາງພືດສາດ' => 'botanical_description',
        'ເຂດກະຈາຍພັນທົ່ວໂລກ' => 'global_distribution',
        'ເຂດກະຈາຍພັນໃນລາວ' => 'lao_distribution',
        'ເຂດກະຈາຍພັນຕາມພູມສັນຖານ' => 'habitat_types',
        'ສະເພາະຖິ່ນໃນລາວ' => 'native_status',
        'ຮຸກຮານ' => 'invasiveness',
        'ສະຖານະພາບການອະນູຮັກ IUCN' => 'iucn_status',
        'ສະຖານະພາບການອະນຸຮັກແຫ່ງຊາດລາວ' => 'national_conservation_status',
        'ປະເພດການນຳໃຊ້' => 'use_types',
        'ບັນຍາຍການນຳໃຊ້' => 'use_description',
        'ການປູກ ການລ້ຽງ' => 'cultivation_info',
        'ລະດູການເກັບກູ້' => 'harvest_season',
        'ການຕະຫຼາດ ແລະ ຕ່ອງໂສ້ມູນຄ່າ' => 'market_data',
        'ຄຸນຄ່າທາງໂພຊະນາການ' => 'nutrition_description',
        'ບັນຍາຍຄຸນຄ່າທາງໂພຊະນາການ' => 'nutrition_description',
        'ເຄດິດຮູບພາບ' => '_photo_credits',
        'ອ້າງອິງ' => '_references',
    ];

    /**
     * List columns that should be parsed as lists (split by <br>).
     *
     * @var list<string>
     */
    private const LIST_COLUMNS = [
        'local_names',
        'synonyms',
        'related_species',
        'habitat_types',
        'use_types',
    ];

    public function __construct(
        public int $delayMs = 1000,
    ) {}

    /**
     * Crawl paginated search results and collect all species IDs.
     *
     * @return list<int>
     */
    public function collectSpeciesIds(int $startPage = 1, int $endPage = 81): array
    {
        $ids = [];

        for ($page = $startPage; $page <= $endPage; $page++) {
            $url = self::BASE_URL . '/search?page=' . $page;

            try {
                $response = $this->fetch($url);

                if (! $response) {
                    Log::warning("Failed to fetch search page {$page}");

                    continue;
                }

                preg_match_all('/specie_details\/(\d+)/', $response, $matches);

                foreach (array_unique($matches[1]) as $id) {
                    $ids[] = (int) $id;
                }

                Log::info("Page {$page}: found " . count(array_unique($matches[1])) . ' species IDs');
            } catch (\Exception $e) {
                Log::error("Error fetching search page {$page}: " . $e->getMessage());
            }

            $this->delay();
        }

        return array_values(array_unique($ids));
    }

    /**
     * Scrape a species detail page and return parsed data.
     *
     * @return array<string, mixed>|null
     */
    public function scrapeSpeciesDetail(int $sourceId): ?array
    {
        $url = self::BASE_URL . '/search/specie_details/' . $sourceId;
        $html = $this->fetch($url);

        if (! $html) {
            return null;
        }

        return $this->parseDetailPage($html, $sourceId);
    }

    /**
     * Parse a detail page HTML into a structured array.
     *
     * @return array<string, mixed>
     */
    public function parseDetailPage(string $html, int $sourceId): array
    {
        $data = [
            'source_id' => $sourceId,
            'scientific_name' => null,
            'common_name_lao' => null,
            'common_name_english' => null,
            'family' => null,
        'category' => null,
            'subcategory' => null,
            'species_type' => null,
            'iucn_status' => null,
            'national_conservation_status' => null,
            'native_status' => null,
            'invasiveness' => null,
            'data_collection_level' => null,
            'harvest_season' => null,
            'local_names' => null,
            'synonyms' => null,
            'related_species' => null,
            'habitat_types' => null,
            'use_types' => null,
            'nutrition' => null,
            'image_urls' => null,
            'map_urls' => null,
            'references' => null,
            'botanical_description' => null,
            'global_distribution' => null,
            'lao_distribution' => null,
            'use_description' => null,
            'cultivation_info' => null,
            'market_data' => null,
            'management_info' => null,
            'threats' => null,
            'nutrition_description' => null,
        ];

        $doc = $this->loadHtml($html);

        if (! $doc) {
            return $data;
        }

        $xpath = new DOMXPath($doc);

        $this->parseHeader($xpath, $data);
        $this->parseCategory($xpath, $data);
        $this->parseImages($xpath, $data);
        $this->parseMaps($xpath, $data);
        $this->parseLabeledSections($xpath, $data);
        $this->parseManagementSection($xpath, $data);
        $this->parseNutritionTable($xpath, $data);
        $this->parseReferences($xpath, $data);

        return $data;
    }

    private function parseHeader(DOMXPath $xpath, array &$data): void
    {
        // Data collection level: "ລະດັບການຮວບຮວມຂໍ້ມູນ: ..."
        $nodes = $xpath->query('//div[contains(text(), "ລະດັບການຮວບຮວມຂໍ້ມູນ")]');
        if ($nodes && $nodes->length > 0) {
            $text = trim($nodes->item(0)->textContent);
            if (preg_match('/ລະດັບການຮວບຮວມຂໍ້ມູນ:\s*(.+)/', $text, $m)) {
                $data['data_collection_level'] = trim($m[1]);
            }
        }

        // Title block: h3 contains Lao name, English name, and scientific name
        $h3Nodes = $xpath->query('//div[contains(@style, "background-color:#aac557")]//h3');
        if ($h3Nodes && $h3Nodes->length > 0) {
            $h3 = $h3Nodes->item(0);
            $fullText = $h3->textContent;
            $lines = array_values(array_filter(array_map('trim', preg_split('/\n/', $fullText))));

            // The h3 contains: bold(Lao name \n English name) \n Scientific name
            $boldNodes = $xpath->query('.//b', $h3);
            if ($boldNodes && $boldNodes->length > 0) {
                $boldText = trim($boldNodes->item(0)->textContent);
                $boldLines = array_values(array_filter(array_map('trim', preg_split('/\n/', $boldText))));

                if (count($boldLines) >= 2) {
                    $data['common_name_lao'] = $boldLines[0];
                    $data['common_name_english'] = $boldLines[1];
                } elseif (count($boldLines) === 1) {
                    $data['common_name_lao'] = $boldLines[0];
                }
            }

            // Scientific name is the non-bold part of h3
            $lastLine = end($lines);
            if ($lastLine && $lastLine !== ($data['common_name_english'] ?? '') && $lastLine !== ($data['common_name_lao'] ?? '')) {
                $data['scientific_name'] = $lastLine;
            }
        }
    }

    /**
     * Parse the category bar (green bar with category, subcategory, species type).
     *
     * @param  array<string, mixed>  $data
     */
    private function parseCategory(DOMXPath $xpath, array &$data): void
    {
        // The category bar is a row with background-color:#aac557 and min-height: 40px
        // It contains three h5 elements: category (col-md-3), subcategory (col-md-4), species_type (col-md-3)
        $rows = $xpath->query('//div[contains(@style, "min-height: 40px") and contains(@style, "#aac557")]');

        if (! $rows || $rows->length === 0) {
            return;
        }

        $row = $rows->item(0);
        $h5Nodes = $xpath->query('.//h5', $row);

        if (! $h5Nodes || $h5Nodes->length === 0) {
            return;
        }

        $values = [];
        for ($i = 0; $i < $h5Nodes->length; $i++) {
            $text = trim($h5Nodes->item($i)->textContent);
            $values[] = $text !== '' ? $text : null;
        }

        if (isset($values[0]) && $values[0] !== null) {
            $data['category'] = $values[0];
        }

        if (isset($values[1]) && $values[1] !== null) {
            $data['subcategory'] = $values[1];
        }

        if (isset($values[2]) && $values[2] !== null) {
            $data['species_type'] = $values[2];
        }
    }

    private function parseImages(DOMXPath $xpath, array &$data): void
    {
        $imageNodes = $xpath->query('//div[contains(@class, "mySlides")]//img');

        if (! $imageNodes || $imageNodes->length === 0) {
            return;
        }

        $urls = [];
        for ($i = 0; $i < $imageNodes->length; $i++) {
            $src = $imageNodes->item($i)->getAttribute('src');
            if ($src) {
                $urls[] = $this->absoluteUrl($src);
            }
        }

        if ($urls) {
            $data['image_urls'] = $urls;
        }
    }

    private function parseMaps(DOMXPath $xpath, array &$data): void
    {
        $mapNodes = $xpath->query('//img[contains(@src, "/maps/")]');

        if (! $mapNodes || $mapNodes->length === 0) {
            return;
        }

        $urls = [];
        for ($i = 0; $i < $mapNodes->length; $i++) {
            $src = $mapNodes->item($i)->getAttribute('src');
            if ($src) {
                $urls[] = $this->absoluteUrl($src);
            }
        }

        if ($urls) {
            $data['map_urls'] = $urls;
        }
    }

    private function parseLabeledSections(DOMXPath $xpath, array &$data): void
    {
        // Pattern: div.row > div.col-md-3 (label) + div.col-md-9 or div.col-md-3 (value)
        $rows = $xpath->query('//div[contains(@class, "row")]');

        if (! $rows) {
            return;
        }

        for ($i = 0; $i < $rows->length; $i++) {
            $row = $rows->item($i);

            // Find the label div (col-md-3 text-right)
            $labelDivs = $xpath->query('.//div[contains(@class, "col-md-3") and contains(@class, "text-right")]', $row);
            if (! $labelDivs || $labelDivs->length === 0) {
                continue;
            }

            $labelText = $this->cleanLabel(trim($labelDivs->item(0)->textContent));

            // Match label to column
            $column = $this->matchLabel($labelText);
            if (! $column) {
                continue;
            }

            // Find the value div (col-md-9 or next col-md-3 sibling)
            $valueDivs = $xpath->query('.//div[contains(@class, "col-md-9")]', $row);
            if (! $valueDivs || $valueDivs->length === 0) {
                // For some sections like habitat/distribution, the value is in col-md-3 (next one)
                $allCols = $xpath->query('.//div[contains(@class, "col-md-3")]', $row);
                if ($allCols && $allCols->length >= 2) {
                    $valueNode = $allCols->item(1);
                } else {
                    continue;
                }
            } else {
                $valueNode = $valueDivs->item(0);
            }

            $value = $this->extractValue($valueNode, $column);

            if ($value !== null && $value !== '' && $value !== []) {
                // Handle special columns
                if ($column === '_photo_credits' || $column === '_references') {
                    $data['references'] = $data['references'] ?? [];
                    $refText = is_array($value) ? implode("\n", $value) : $value;
                    if ($refText) {
                        $data['references'][] = [
                            'type' => $column === '_photo_credits' ? 'photo_credit' : 'bibliography',
                            'content' => $refText,
                        ];
                    }
                } else {
                    $data[$column] = $value;
                }
            }
        }
    }

    private function parseManagementSection(DOMXPath $xpath, array &$data): void
    {
        // Management section follows the "ການຄຸ້ມຄອງຈັດການ" header
        $headers = $xpath->query('//h5[b[contains(text(), "ການຄຸ້ມຄອງຈັດການ")]]');
        if (! $headers || $headers->length === 0) {
            return;
        }

        $headerRow = $headers->item(0)->parentNode->parentNode;
        $nextRow = $headerRow->nextSibling;

        // Skip whitespace text nodes
        while ($nextRow && $nextRow->nodeType === XML_TEXT_NODE) {
            $nextRow = $nextRow->nextSibling;
        }

        if ($nextRow) {
            $valueDivs = (new DOMXPath($nextRow->ownerDocument))->query('.//div[contains(@class, "col-md-9")]', $nextRow);
            if ($valueDivs && $valueDivs->length > 0) {
                $text = trim($valueDivs->item(0)->textContent);
                if ($text) {
                    $data['management_info'] = $text;
                }
            }
        }
    }

    private function parseNutritionTable(DOMXPath $xpath, array &$data): void
    {
        $tables = $xpath->query('//table[contains(@class, "table")]//tbody/tr');
        if (! $tables || $tables->length === 0) {
            return;
        }

        $nutrition = [];
        for ($i = 0; $i < $tables->length; $i++) {
            $cells = $xpath->query('.//td', $tables->item($i));
            if ($cells && $cells->length >= 3) {
                $nutrient = trim($cells->item(0)->textContent);
                $valuePer100g = trim($cells->item(1)->textContent);
                $note = trim($cells->item(2)->textContent);

                if ($nutrient) {
                    $nutrition[] = [
                        'nutrient' => $nutrient,
                        'value_per_100g' => $valuePer100g,
                        'note' => $note !== 'N/A' ? $note : null,
                    ];
                }
            }
        }

        if ($nutrition) {
            $data['nutrition'] = $nutrition;
        }
    }

    private function parseReferences(DOMXPath $xpath, array &$data): void
    {
        // References are already partially handled in parseLabeledSections
        // This catches any that were missed via the specific header approach
    }

    /**
     * Clean a label by removing tooltip icons, extra whitespace, colons.
     */
    private function cleanLabel(string $label): string
    {
        // Remove content after info icon tooltips
        $label = preg_replace('/\s*:?\s*$/', '', $label);

        // Remove common suffixes
        $label = preg_replace('/[:\s]+$/', '', $label);

        // Collapse whitespace
        $label = preg_replace('/\s+/', ' ', $label);

        return trim($label);
    }

    /**
     * Match a label to a database column name.
     */
    private function matchLabel(string $label): ?string
    {
        foreach (self::LABEL_MAP as $laoLabel => $column) {
            if (mb_strpos($label, $laoLabel) !== false) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Extract value from a DOM node, handling lists vs. plain text.
     */
    private function extractValue(\DOMNode $node, string $column): mixed
    {
        if (in_array($column, self::LIST_COLUMNS)) {
            return $this->extractList($node);
        }

        // For harvest_season, extract as comma-separated string from br-separated items
        if ($column === 'harvest_season') {
            $items = $this->extractList($node);

            return $items ? implode(', ', $items) : null;
        }

        return $this->extractText($node);
    }

    /**
     * Extract a list of items from a node (split by <br> tags).
     *
     * @return list<string>
     */
    private function extractList(\DOMNode $node): array
    {
        $result = [];
        $currentText = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'br') {
                $clean = $this->cleanListItem($currentText);
                if ($clean !== '') {
                    $result[] = $clean;
                }
                $currentText = '';
            } else {
                $currentText .= $child->textContent;
            }
        }

        // Don't forget the last segment
        $clean = $this->cleanListItem($currentText);
        if ($clean !== '') {
            $result[] = $clean;
        }

        return $result;
    }

    private function cleanListItem(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^[\s,\-–—]+|[\s,\-–—]+$/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        if ($text === '' || $text === 'N/A') {
            return '';
        }

        // Ensure valid UTF-8
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return $text;
    }

    /**
     * Extract plain text from a node.
     */
    private function extractText(\DOMNode $node): ?string
    {
        $text = trim($node->textContent);

        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return $text !== '' && $text !== 'N/A' ? $text : null;
    }

    /**
     * Convert a relative URL to an absolute URL.
     */
    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return self::BASE_URL . '/' . ltrim($url, '/');
    }

    /**
     * Fetch a URL and return the HTML body.
     */
    private function fetch(string $url): ?string
    {
        $response = Http::timeout(30)
            ->retry(3, 500)
            ->withHeaders([
                'User-Agent' => 'PhakhaoLaoAI/1.0 (Species Data Import; admin@phakhaolao.la)',
            ])
            ->get($url);

        if (! $response->successful()) {
            Log::warning("HTTP {$response->status()} for {$url}");

            return null;
        }

        return $response->body();
    }

    /**
     * Load HTML into a DOMDocument, suppressing warnings for malformed HTML.
     */
    private function loadHtml(string $html): ?DOMDocument
    {
        $doc = new DOMDocument;

        // Wrap in proper HTML with UTF-8 charset declaration
        $wrappedHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';

        libxml_use_internal_errors(true);
        $doc->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return $doc;
    }

    private function delay(): void
    {
        usleep($this->delayMs * 1000);
    }
}
