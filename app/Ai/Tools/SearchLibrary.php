<?php

namespace App\Ai\Tools;

use App\Models\LibraryResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchLibrary implements Tool
{
    private const LIMIT = 8;

    /**
     * ISO-style document-language codes mapped to the stored English label, so a
     * user can filter by "lo"/"my" as well as the full name. Records are scoped to
     * one website page (language), so values match within that page directly.
     *
     * @var array<string, string>
     */
    private const LANGUAGE_CODES = [
        'my' => 'Burmese',
        'en' => 'English',
        'fr' => 'French',
        'km' => 'Khmer',
        'lo' => 'Lao',
        'th' => 'Thai',
        'vi' => 'Vietnamese',
    ];

    public function description(): Stringable|string
    {
        return 'Search the PhaKhaoLao digital library — publications, books, research articles, reports, '
            .'guidelines and other resources about Lao agrobiodiversity. Combine any of these filters (all '
            .'optional, AND-combined): query (title/description keyword), topic, resource_type (Book, '
            .'Research article, Report, Thesis, Manual, Policy brief, Brochure, Conference paper), '
            .'resource_language (the document language: Burmese, English, French, Khmer, Lao, Thai, Vietnamese), '
            .'publication_year, author, and sort (newest, oldest, title_asc, title_desc). Returns the title, '
            .'author, type, language, topics and a link to read or download. Pass filter values in the user\'s '
            .'language so English values match English records and Lao match Lao.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));
        $topic = trim((string) ($request['topic'] ?? ''));
        $type = trim((string) ($request['resource_type'] ?? ''));
        $languageRaw = trim((string) ($request['resource_language'] ?? ''));
        $documentLanguage = $languageRaw === '' ? '' : $this->normalizeLanguage($languageRaw);
        $year = is_numeric($request['publication_year'] ?? null) ? (int) $request['publication_year'] : null;
        $author = trim((string) ($request['author'] ?? ''));
        $pageLanguage = trim((string) ($request['language'] ?? ''));
        $sort = strtolower(trim((string) ($request['sort'] ?? '')));

        if ($query === '' && $topic === '' && $type === '' && $languageRaw === '' && $year === null && $author === '' && $pageLanguage === '') {
            return 'Provide a keyword or at least one library filter (topic, resource_type, resource_language, publication_year, author, or language).';
        }

        $builder = LibraryResource::query()
            ->when($query !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($query): void {
                $this->whereLike($w, 'title', $query);
                $w->orWhereRaw('lower(description) like ?', ['%'.mb_strtolower($query).'%']);
            }))
            ->when($topic !== '', fn (Builder $q) => $q->whereRaw('lower(cast(topics as text)) like ?', ['%'.mb_strtolower($topic).'%']))
            ->when($type !== '', fn (Builder $q) => $this->whereLike($q, 'resource_type', $type))
            ->when($documentLanguage !== '', fn (Builder $q) => $this->whereLike($q, 'resource_language', $documentLanguage))
            ->when($year !== null, fn (Builder $q) => $q->where('publication_year', $year))
            ->when($author !== '', fn (Builder $q) => $this->whereLike($q, 'author', $author))
            ->when($pageLanguage !== '', fn (Builder $q) => $q->where('language', $pageLanguage));

        $this->applySort($builder, $sort);

        $total = (clone $builder)->count();
        $resources = $builder->limit(self::LIMIT)->get();

        if ($resources->isEmpty()) {
            return 'No library resources matched those filters. Try a broader keyword, a different topic, '
                .'or a type like Book, Research article, Report, Thesis, or Manual.';
        }

        $header = "Found {$total} library resource".($total === 1 ? '' : 's').':';

        return $header."\n".$resources->map(fn (LibraryResource $r) => $this->format($r))->implode("\n---\n");
    }

    /**
     * Case-insensitive LIKE that works on both PostgreSQL and SQLite.
     *
     * @param  Builder<LibraryResource>  $query
     */
    private function whereLike(Builder $query, string $column, string $value): Builder
    {
        return $query->whereRaw("lower({$column}) like ?", ['%'.mb_strtolower($value).'%']);
    }

    /**
     * @param  Builder<LibraryResource>  $builder
     */
    private function applySort(Builder $builder, string $sort): void
    {
        match ($sort) {
            'oldest' => $builder->orderBy('publication_year'),
            'newest' => $builder->orderByDesc('publication_year'),
            'title_asc' => $builder->orderBy('title'),
            'title_desc' => $builder->orderByDesc('title'),
            default => $builder->orderByDesc('featured')->orderBy('title'),
        };
    }

    private function normalizeLanguage(string $value): string
    {
        $value = trim($value);

        return self::LANGUAGE_CODES[mb_strtolower($value)] ?? $value;
    }

    private function format(LibraryResource $resource): string
    {
        $parts = ["**{$resource->title}**"];

        if ($resource->author) {
            $parts[] = "Author(s): {$resource->author}";
        }
        if ($resource->resource_type) {
            $parts[] = "Type: {$resource->resource_type}";
        }
        if ($resource->resource_language) {
            $parts[] = "Language: {$resource->resource_language}";
        }
        if ($resource->publication_year) {
            $parts[] = "Year: {$resource->publication_year}";
        }
        if (is_array($resource->topics) && $resource->topics !== []) {
            $parts[] = 'Topics: '.collect($resource->topics)->filter()->implode(', ');
        }
        if ($resource->description) {
            $parts[] = mb_strimwidth($resource->description, 0, 320, '...');
        }
        if ($resource->file_url) {
            $parts[] = "[Download PDF]({$resource->file_url})";
        }
        if ($resource->source_url) {
            $parts[] = "[Open resource page]({$resource->source_url})";
        }

        return implode("\n", $parts);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Keyword to match in the title or description.'),
            'topic' => $schema->string()->description('A topic, e.g. "Agroforestry and Ecosystem Management".'),
            'resource_type' => $schema->string()->description('Resource type: Book, Research article, Report, Thesis, Manual, Policy brief, Brochure, or Conference paper.'),
            'resource_language' => $schema->string()->description('Document language: Burmese, English, French, Khmer, Lao, Thai, or Vietnamese.'),
            'publication_year' => $schema->integer()->description('Publication year, e.g. 2025.'),
            'author' => $schema->string()->description('Author name.'),
            'language' => $schema->string()->description('Website record language: "en" or "lo".'),
            'sort' => $schema->string()->description('Sort order: newest, oldest, title_asc, or title_desc.'),
        ];
    }
}
