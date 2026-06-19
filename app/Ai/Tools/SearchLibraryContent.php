<?php

namespace App\Ai\Tools;

use App\Models\LibraryChunk;
use App\Support\RagSettings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class SearchLibraryContent implements Tool
{
    private const LIMIT = 6;

    public function description(): Stringable|string
    {
        return 'Search the full text INSIDE the PhaKhaoLao library documents (the actual PDF contents, not '
            .'just titles). Use this when the user asks about what a publication SAYS — facts, findings, '
            .'methods, or details contained in the library reports, books, and guides. Returns the most '
            .'relevant excerpts with the source title and a link. For finding documents by title/type/author, '
            .'use SearchLibrary instead.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));

        if ($query === '') {
            return 'Please provide a question or keyword to search the library document contents.';
        }

        if (! $this->isAvailable()) {
            return 'Library document full-text search is not available yet (the PDFs have not been indexed). '
                .'Use SearchLibrary to find resources by title, type, or author.';
        }

        $rag = RagSettings::all();

        try {
            $chunks = LibraryChunk::query()
                ->whereVectorSimilarTo('embedding', $query, minSimilarity: (float) $rag['min_similarity'])
                ->with('resource')
                ->limit(self::LIMIT)
                ->get();
        } catch (Throwable) {
            return 'Library document full-text search is temporarily unavailable.';
        }

        if ($chunks->isEmpty()) {
            return "No library document contents matched '{$query}'. Try different wording, or use "
                .'SearchLibrary to browse by title or type.';
        }

        return "Relevant excerpts from the library documents:\n"
            .$chunks->map(fn (LibraryChunk $chunk) => $this->format($chunk))->implode("\n---\n");
    }

    private function format(LibraryChunk $chunk): string
    {
        $resource = $chunk->resource;
        $parts = [];

        $parts[] = '**'.($resource?->title ?? 'Library document').'**';
        $parts[] = mb_strimwidth($chunk->content, 0, 600, '...');

        if ($resource?->file_url) {
            $parts[] = "[Download PDF]({$resource->file_url})";
        } elseif ($resource?->source_url) {
            $parts[] = "[Open resource page]({$resource->source_url})";
        }

        return implode("\n", $parts);
    }

    private function isAvailable(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql'
            && Schema::hasTable('library_chunks')
            && Schema::hasColumn('library_chunks', 'embedding');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The question or keyword to look up inside the library document contents.')
                ->required(),
        ];
    }
}
