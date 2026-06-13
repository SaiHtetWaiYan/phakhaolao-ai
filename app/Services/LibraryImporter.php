<?php

namespace App\Services;

use App\Models\LibraryResource;
use App\Services\Concerns\MapsWordPressPosts;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class LibraryImporter
{
    use MapsWordPressPosts;

    /** Languages to sync (WPML stores each as a separate post). */
    private const LANGUAGES = ['en', 'lo'];

    /** Minimum posts expected before a sync may prune, to guard against a broken API response. */
    private const SANITY_MIN = 10;

    public function __construct(
        private readonly WordPressClient $client = new WordPressClient,
        private readonly LibraryFilterCatalog $filterCatalog = new LibraryFilterCatalog,
    ) {}

    /**
     * @return array{imported: int, changed: int, archived: int, filters_synced: int, filters_failed: int}
     */
    public function import(bool $dryRun = false): array
    {
        $imported = 0;
        $changed = 0;
        $archived = 0;
        $seenSourceIds = [];

        foreach (self::LANGUAGES as $lang) {
            foreach ($this->client->fetchAll('resource', $lang) as $post) {
                $sourceId = (int) ($post['id'] ?? 0);
                if ($sourceId === 0) {
                    continue;
                }

                $seenSourceIds[] = $sourceId;
                $data = $this->mapPost($post, $lang);
                $hash = md5(json_encode(Arr::except($data, ['source_modified_at']), JSON_UNESCAPED_UNICODE) ?: '');

                if ($dryRun) {
                    $imported++;

                    continue;
                }

                $existing = LibraryResource::query()->where('source_id', $sourceId)->first();

                if (! $existing || $existing->content_hash !== $hash) {
                    $changed++;
                }

                $data['content_hash'] = $hash;
                LibraryResource::query()->updateOrCreate(['source_id' => $sourceId], $data);
                $imported++;
            }
        }

        if (! $dryRun && count($seenSourceIds) >= self::SANITY_MIN) {
            $archived = LibraryResource::query()->whereNotIn('source_id', $seenSourceIds)->delete();
        }

        $filters = $dryRun
            ? ['synced' => 0, 'failed' => 0]
            : $this->filterCatalog->syncAll();

        return [
            'imported' => $imported,
            'changed' => $changed,
            'archived' => $archived,
            'filters_synced' => $filters['synced'],
            'filters_failed' => $filters['failed'],
        ];
    }

    /**
     * Enrich resources by scraping their detail pages for the author and PDF
     * download link (these are not exposed by the REST API).
     *
     * @return array{updated: int, failed: int, total: int}
     */
    public function enrich(?callable $onProgress = null, int $delayMs = 300): array
    {
        $resources = LibraryResource::query()->whereNotNull('source_url')->get();
        $updated = 0;
        $failed = 0;

        foreach ($resources as $resource) {
            try {
                $html = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->timeout(20)
                    ->get($resource->source_url)
                    ->body();

                $changes = array_filter([
                    'file_url' => $this->parsePdfLink($html),
                    'author' => $this->parseAuthor($html, $resource->title),
                    'publication_year' => $this->parsePublicationYear($html),
                ], fn ($v) => $v !== null);

                if ($changes !== []) {
                    $resource->update($changes);
                    $updated++;
                }
            } catch (\Throwable) {
                $failed++;
            }

            if ($onProgress) {
                $onProgress();
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        return ['updated' => $updated, 'failed' => $failed, 'total' => $resources->count()];
    }

    private function parsePdfLink(string $html): ?string
    {
        if (preg_match('#href="(https://phakhaolao\.la/wp-content/uploads/[^"]+\.(?:pdf|docx?|xlsx?|pptx?|zip))"#i', $html, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function parsePublicationYear(string $html): ?int
    {
        if (preg_match('/published\s+in\s+((?:19|20)\d{2})/i', $html, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    private function parseAuthor(string $html, string $title): ?string
    {
        if (preg_match_all('#class="elementor-heading-title[^"]*"[^>]*>(.*?)</div>#s', $html, $m) !== 1 && empty($m[1])) {
            return null;
        }

        $headings = [];
        foreach ($m[1] as $raw) {
            $text = $this->htmlText($raw);
            if ($text !== null) {
                $headings[] = $text;
            }
        }

        $titleLower = mb_strtolower($title);

        foreach ($headings as $i => $heading) {
            if (str_contains(mb_strtolower($heading), $titleLower) && isset($headings[$i + 1])) {
                return $headings[$i + 1];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    private function mapPost(array $post, string $lang): array
    {
        $terms = $this->extractTerms($post);
        $author = $this->clean(Arr::get($post, 'acf.pkl_resource_author'));

        $data = [
            'language' => $lang,
            'slug' => $this->clean($post['slug'] ?? null),
            'title' => $this->htmlText(Arr::get($post, 'title.rendered')) ?? 'Untitled',
            'publication_year' => $this->publicationYear(Arr::get($post, 'acf.pkl_resource_year')),
            'description' => $this->htmlText(Arr::get($post, 'content.rendered')),
            'resource_type' => Arr::first($terms['resource-type'] ?? []),
            'resource_language' => Arr::first($terms['language'] ?? []),
            'access_right' => Arr::first($terms['access-right'] ?? []),
            'featured' => strtolower((string) Arr::first($terms['featured'] ?? [])) === 'yes',
            'topics' => array_values($terms['topic'] ?? []),
            'provinces' => array_values($terms['province'] ?? []),
            'featured_image' => $this->clean(Arr::get($post, '_embedded.wp:featuredmedia.0.source_url')),
            'source_url' => $this->clean($post['link'] ?? null),
            'source_modified_at' => $this->clean($post['modified'] ?? null),
        ];

        // Preserve an author found by detail-page enrichment when the REST API
        // does not expose the ACF author field.
        if ($author !== null) {
            $data['author'] = $author;
        }

        return $data;
    }

    private function publicationYear(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        return preg_match('/\b(19|20)\d{2}\b/', (string) $value, $matches) === 1
            ? (int) $matches[0]
            : null;
    }
}
