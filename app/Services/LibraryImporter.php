<?php

namespace App\Services;

use App\Models\LibraryResource;
use App\Services\Concerns\MapsWordPressPosts;
use Illuminate\Support\Arr;

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
            $posts = $this->client->fetchAll('resource', $lang);

            // Resolve every resource's PDF (an attachment ID in acf) to its URL
            // in one batched request per language.
            $mediaUrls = $dryRun ? [] : $this->client->fetchMediaUrls(
                $posts->map(fn (array $post): mixed => Arr::get($post, 'acf.pkl_resource_file'))->all()
            );

            foreach ($posts as $post) {
                $sourceId = (int) ($post['id'] ?? 0);
                if ($sourceId === 0) {
                    continue;
                }

                $seenSourceIds[] = $sourceId;
                $data = $this->mapPost($post, $lang, $mediaUrls);
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
     * @param  array<string, mixed>  $post
     * @param  array<int, string>  $mediaUrls  Resolved attachment id => file URL
     * @return array<string, mixed>
     */
    private function mapPost(array $post, string $lang, array $mediaUrls = []): array
    {
        $terms = $this->extractTerms($post);
        $fileId = (int) Arr::get($post, 'acf.pkl_resource_file');

        return [
            'language' => $lang,
            'slug' => $this->clean($post['slug'] ?? null),
            'title' => $this->htmlText(Arr::get($post, 'title.rendered')) ?? 'Untitled',
            'publication_year' => $this->publicationYear(Arr::get($post, 'acf.pkl_resource_year')),
            'author' => $this->clean(Arr::get($post, 'acf.pkl_resource_author')),
            'file_url' => $fileId > 0 ? ($mediaUrls[$fileId] ?? null) : null,
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
