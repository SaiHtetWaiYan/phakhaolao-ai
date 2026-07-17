<?php

namespace App\Ai\Tools;

use App\Models\LibraryChunk;
use App\Support\RagSettings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class SearchLibraryContent implements Tool
{
    private const LIMIT = 6;

    /** Excerpts returned when narrowed to a single document (for detailed answers). */
    private const SCOPED_LIMIT = 12;

    public function description(): Stringable|string
    {
        return 'Search the full text INSIDE the PhaKhaoLao library documents (the actual PDF contents, not '
            .'just titles). Use this when the user asks about what a publication SAYS — facts, findings, '
            .'methods, study sites, or details contained in the library reports, books, and guides. When the '
            .'question is about ONE specific document, pass its title so the answer pulls fuller context from '
            .'that document (in document order). Returns the most relevant excerpts with the source title and '
            .'a link. For finding documents by title/type/author, use SearchLibrary instead.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));
        $title = trim((string) ($request['title'] ?? ''));

        if ($query === '') {
            return 'Please provide a question or keyword to search the library document contents.';
        }

        if (! $this->isAvailable()) {
            return 'Library document full-text search is not available yet (the PDFs have not been indexed). '
                .'Use SearchLibrary to find resources by title, type, or author.';
        }

        $rag = RagSettings::all();
        $scoped = $title !== '';
        // When narrowed to a document, relax the threshold so adjacent context
        // (e.g. the rest of a split section) is included.
        $minSimilarity = $scoped ? 0.15 : (float) $rag['min_similarity'];

        try {
            $chunks = LibraryChunk::query()
                ->whereVectorSimilarTo('embedding', $query, minSimilarity: $minSimilarity)
                ->when($scoped, fn (Builder $q) => $q->whereHas('resource', function (Builder $r) use ($title): void {
                    foreach ($this->titleWords($title) as $word) {
                        $r->whereRaw('lower(title) like ?', ['%'.$word.'%']);
                    }
                }))
                ->with('resource')
                ->limit($scoped ? self::SCOPED_LIMIT : self::LIMIT)
                ->get();
        } catch (Throwable) {
            return 'Library document full-text search is temporarily unavailable.';
        }

        if ($chunks->isEmpty()) {
            return "No library document contents matched '{$query}'. Try different wording, or use "
                .'SearchLibrary to browse by title or type.';
        }

        // Within a single document, present excerpts in reading order.
        if ($scoped) {
            $chunks = $chunks->sortBy([['library_resource_id', 'asc'], ['chunk_index', 'asc']])->values();
        }

        // Show fuller passages when narrowed to one document so detail questions
        // (study site, methods) aren't cut off mid-section.
        $excerptChars = $scoped ? 2000 : 600;

        return "Relevant excerpts from the library documents:\n"
            .$chunks->map(fn (LibraryChunk $chunk) => $this->format($chunk, $excerptChars))->implode("\n---\n");
    }

    /**
     * @return list<string>
     */
    private function titleWords(string $title): array
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', mb_strtolower(trim($title))) ?: [],
            fn (string $word): bool => mb_strlen($word) >= 3
        ));

        return $words === [] ? [mb_strtolower(trim($title))] : $words;
    }

    private function format(LibraryChunk $chunk, int $maxChars = 600): string
    {
        $resource = $chunk->resource;
        $parts = [];

        $parts[] = '**'.($resource?->title ?? 'Library document').'**';
        $parts[] = mb_strimwidth($chunk->content, 0, $maxChars, '...');

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
            'title' => $schema
                ->string()
                ->description('Optional. The title (or distinctive words of it) of a specific document, to focus the search on that document and return fuller context for a detailed answer.'),
        ];
    }
}
