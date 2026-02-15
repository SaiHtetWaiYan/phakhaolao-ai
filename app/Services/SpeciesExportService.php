<?php

namespace App\Services;

use App\Models\Species;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpeciesExportService
{
    /**
     * @var list<string>
     */
    public const SPECIES_EXPORT_COLUMNS = [
        'source_id',
        'scientific_name',
        'common_name_lao',
        'common_name_english',
        'family',
        'iucn_status',
        'native_status',
        'invasiveness',
        'data_collection_level',
        'harvest_season',
        'local_names',
        'synonyms',
        'related_species',
        'habitat_types',
        'use_types',
        'nutrition',
        'botanical_description',
        'global_distribution',
        'lao_distribution',
        'use_description',
        'cultivation_info',
        'market_data',
        'management_info',
        'threats',
        'nutrition_description',
        'scrape_status',
        'scrape_error',
        'scraped_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const EXPORT_COLUMN_ALIASES = [
        'source_id' => ['source id', 'sourceid', 'id'],
        'scientific_name' => ['scientific name', 'scientific names'],
        'common_name_lao' => ['lao name', 'lao names', 'laos name', 'laos names', 'local lao name'],
        'common_name_english' => ['english name', 'english names', 'eng name', 'eng names'],
        'iucn_status' => ['iucn', 'iucn status'],
    ];

    /**
     * @var list<string>
     */
    private const EXPORT_SEARCHABLE_COLUMNS = [
        'scientific_name',
        'common_name_lao',
        'common_name_english',
        'family',
        'local_names',
        'synonyms',
        'habitat_types',
        'use_types',
    ];

    /**
     * @var array<string, string>
     */
    private const CATEGORY_MAP = [
        'animal' => 'ສັດ',
        'animals' => 'ສັດ',
        'plant' => 'ພືດ',
        'plants' => 'ພືດ',
        'fungi' => 'ເຊື້ອເຫັດ',
        'fungus' => 'ເຊື້ອເຫັດ',
        'mushroom' => 'ເຊື້ອເຫັດ',
        'mushrooms' => 'ເຊື້ອເຫັດ',
        'ສັດ' => 'ສັດ',
        'ພືດ' => 'ພືດ',
        'ເຊື້ອເຫັດ' => 'ເຊື້ອເຫັດ',
    ];

    /**
     * @var array<string, string>
     */
    private const SUBCATEGORY_MAP = [
        'fish' => 'ປາ',
        'fishes' => 'ປາ',
        'bird' => 'ສັດປີກ',
        'birds' => 'ສັດປີກ',
        'mammal' => 'ສັດລ້ຽງລູກດ້ວຍນົມ',
        'mammals' => 'ສັດລ້ຽງລູກດ້ວຍນົມ',
        'reptile' => 'ສັດເລືອຄານ',
        'reptiles' => 'ສັດເລືອຄານ',
        'amphibian' => 'ສັດເຄິ່ງບົກເຄິ່ງນ້ຳ',
        'amphibians' => 'ສັດເຄິ່ງບົກເຄິ່ງນ້ຳ',
        'insect' => 'ແມງໄມ້',
        'insects' => 'ແມງໄມ້',
        'tree' => 'ໄມ້ຢືນຕົ້ນ',
        'trees' => 'ໄມ້ຢືນຕົ້ນ',
    ];

    /**
     * Generate an Excel export and return [token, count, type].
     *
     * @param  array{query:string,category:string,subcategory:string,columns:string}  $params
     * @return array{0:string,1:int,2:string}
     */
    public function generateExport(array $params): array
    {
        $intent = $this->buildIntentFromParams($params);
        $rowsQuery = $this->buildExportRowsQuery($intent);

        $rows = $rowsQuery->limit(10000)->get();
        $count = $rows->count();

        $token = (string) Str::uuid();
        $relativePath = 'exports/generated/species_'.$token.'.xlsx';
        $metaPath = 'exports/generated/species_'.$token.'.json';
        $absolutePath = storage_path('app/'.$relativePath);
        $absoluteDirectory = dirname($absolutePath);

        if (! is_dir($absoluteDirectory) && ! mkdir($absoluteDirectory, 0775, true) && ! is_dir($absoluteDirectory)) {
            throw new \RuntimeException('Unable to create export directory.');
        }

        $this->writeXlsxFile($absolutePath, $intent['columns'], $rows);
        \Illuminate\Support\Facades\Storage::disk('local')->put(
            $metaPath,
            json_encode($intent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return [$token, $count, $intent['type']];
    }

    /**
     * @param  array{type:string,label:string,columns:list<string>,query:string,non_empty_column:string|null,category:string|null,subcategory:string|null}  $intent
     */
    public function buildExportRowsQuery(array $intent): Builder
    {
        $rowsQuery = Species::query()->select($intent['columns'])->orderBy('source_id');
        $search = trim((string) ($intent['query'] ?? ''));

        if (($intent['non_empty_column'] ?? null) !== null) {
            $column = (string) $intent['non_empty_column'];
            $rowsQuery->whereNotNull($column)->where($column, '!=', '');
        }

        if (($intent['category'] ?? null) !== null && $intent['category'] !== '') {
            $rowsQuery->where('category', $intent['category']);
        }

        if (($intent['subcategory'] ?? null) !== null && $intent['subcategory'] !== '') {
            $rowsQuery->where('subcategory', $intent['subcategory']);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $rowsQuery->where(function (Builder $query) use ($like): void {
                $query->whereRaw('CAST(source_id AS TEXT) LIKE ?', [$like]);
                foreach (self::EXPORT_SEARCHABLE_COLUMNS as $column) {
                    $query->orWhere($column, 'like', $like);
                }
            });
        }

        return $rowsQuery;
    }

    /**
     * @param  list<string>  $columns
     * @param  iterable<int, mixed>  $rows
     */
    public function writeXlsxFile(string $absolutePath, array $columns, iterable $rows): void
    {
        $spreadsheet = $this->buildSpreadsheet($columns, $rows);
        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * @param  list<string>  $columns
     * @param  iterable<int, mixed>  $rows
     */
    public function buildSpreadsheet(array $columns, iterable $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $columnIndex = 1;
        foreach ($columns as $column) {
            $sheet->setCellValue([$columnIndex, 1], Str::of($column)->replace('_', ' ')->title()->value());
            $columnIndex++;
        }

        $rowIndex = 2;
        foreach ($rows as $row) {
            $columnIndex = 1;
            foreach ($columns as $column) {
                $sheet->setCellValue([$columnIndex, $rowIndex], $this->normalizeExportValue($row->{$column}));
                $columnIndex++;
            }
            $rowIndex++;
        }

        return $spreadsheet;
    }

    public function normalizeExportValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $intent
     * @return array{type:string,label:string,columns:list<string>,query:string,non_empty_column:string|null,category:string|null,subcategory:string|null}
     */
    public function sanitizeExportIntent(array $intent): array
    {
        $columns = array_values(array_filter(
            array_map(fn ($column) => (string) $column, $intent['columns'] ?? []),
            fn (string $column) => in_array($column, self::SPECIES_EXPORT_COLUMNS, true)
        ));

        if ($columns === []) {
            $columns = self::SPECIES_EXPORT_COLUMNS;
        }

        $nonEmptyColumn = $intent['non_empty_column'] ?? null;
        if (! is_string($nonEmptyColumn) || ! in_array($nonEmptyColumn, self::SPECIES_EXPORT_COLUMNS, true)) {
            $nonEmptyColumn = null;
        }

        return [
            'type' => in_array((string) ($intent['type'] ?? ''), ['lao_names', 'english_names', 'scientific_names', 'custom', 'full'], true)
                ? (string) $intent['type']
                : 'custom',
            'label' => (string) ($intent['label'] ?? 'Custom columns export'),
            'columns' => $columns,
            'query' => trim((string) ($intent['query'] ?? '')),
            'non_empty_column' => $nonEmptyColumn,
            'category' => isset($intent['category']) && is_string($intent['category']) ? $intent['category'] : null,
            'subcategory' => isset($intent['subcategory']) && is_string($intent['subcategory']) ? $intent['subcategory'] : null,
        ];
    }

    /**
     * @param  array{query:string,category:string,subcategory:string,columns:string}  $params
     * @return array{type:string,label:string,columns:list<string>,query:string,non_empty_column:string|null,category:string|null,subcategory:string|null}
     */
    private function buildIntentFromParams(array $params): array
    {
        $query = trim((string) ($params['query'] ?? ''));
        $category = $this->resolveCategory(trim((string) ($params['category'] ?? '')));
        $subcategory = $this->resolveSubcategory(trim((string) ($params['subcategory'] ?? '')));
        $columns = $this->resolveColumns(trim((string) ($params['columns'] ?? '')));

        $type = 'full';
        $label = 'Full species export';

        if ($columns !== self::SPECIES_EXPORT_COLUMNS) {
            $type = 'custom';
            $label = 'Custom columns export';
        }

        return [
            'type' => $type,
            'label' => $label,
            'columns' => $columns,
            'query' => $query,
            'non_empty_column' => null,
            'category' => $category,
            'subcategory' => $subcategory,
        ];
    }

    private function resolveCategory(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $lower = mb_strtolower($value);

        return self::CATEGORY_MAP[$lower] ?? null;
    }

    private function resolveSubcategory(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $lower = mb_strtolower($value);

        return self::SUBCATEGORY_MAP[$lower] ?? null;
    }

    /**
     * @return list<string>
     */
    private function resolveColumns(string $columnsParam): array
    {
        if ($columnsParam === '') {
            return self::SPECIES_EXPORT_COLUMNS;
        }

        $requested = array_filter(array_map('trim', explode(',', $columnsParam)));
        $resolved = [];

        foreach ($requested as $candidate) {
            $key = mb_strtolower(str_replace(' ', '_', $candidate));
            if (in_array($key, self::SPECIES_EXPORT_COLUMNS, true)) {
                $resolved[] = $key;
            }
        }

        return $resolved !== [] ? array_values(array_unique($resolved)) : self::SPECIES_EXPORT_COLUMNS;
    }
}
