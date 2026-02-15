<?php

namespace App\Ai\Tools;

use App\Services\SpeciesExportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ExportSpecies implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Export species data as an Excel file for download. Use this tool when the user asks to export, download, or get an Excel/CSV file of species data. '
            .'You can filter by search query, category (animal/plant/fungi), subcategory (fish/bird/mammal/reptile/amphibian/insect/tree), '
            .'and specify which columns to include. Returns a download link and the number of matching species.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $params = [
            'query' => trim((string) $request['query']),
            'category' => trim((string) $request['category']),
            'subcategory' => trim((string) $request['subcategory']),
            'columns' => trim((string) $request['columns']),
        ];

        $service = app(SpeciesExportService::class);

        try {
            [$token, $count, $type] = $service->generateExport($params);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ExportSpecies tool failed', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);

            return 'Export failed due to an internal error. Please try again or adjust your filters.';
        }

        $downloadUrl = route('species.export-generated', ['token' => $token, 'type' => $type]);

        return "Excel export generated successfully.\n"
            ."Matching species: {$count}\n"
            ."Download link: {$downloadUrl}";
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('Search filter text to narrow down species (e.g. a scientific name, family, or keyword). Use empty string for all species.')
                ->required(),
            'category' => $schema
                ->string()
                ->description('Category filter: "animal", "plant", "fungi", or empty string for all categories.')
                ->required(),
            'subcategory' => $schema
                ->string()
                ->description('Subcategory filter: "fish", "bird", "mammal", "reptile", "amphibian", "insect", "tree", or empty string for all.')
                ->required(),
            'columns' => $schema
                ->string()
                ->description('Comma-separated column names to include (e.g. "source_id,scientific_name,family"). Use empty string for all columns.')
                ->required(),
        ];
    }
}
