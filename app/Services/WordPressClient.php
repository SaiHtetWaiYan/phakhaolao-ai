<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class WordPressClient
{
    public function __construct(
        private readonly string $baseUrl = 'https://phakhaolao.la/wp-json/wp/v2',
    ) {}

    /**
     * Fetch all posts of a custom post type, paginating through every page.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function fetchAll(string $postType, string $lang = 'en', ?string $modifiedAfter = null): Collection
    {
        $posts = collect();
        $page = 1;
        $totalPages = 1;

        do {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->retry(2, 1500)
                ->acceptJson()
                ->get("{$this->baseUrl}/{$postType}", array_filter([
                    'per_page' => 100,
                    'page' => $page,
                    'lang' => $lang,
                    '_embed' => 1,
                    'modified_after' => $modifiedAfter,
                ]));

            // WordPress returns 400 when requesting a page beyond the last one.
            if ($response->status() === 400) {
                break;
            }

            $response->throw();

            $totalPages = (int) ($response->header('X-WP-TotalPages') ?: 1);
            $posts = $posts->concat($response->json() ?? []);
            $page++;
        } while ($page <= $totalPages);

        return $posts;
    }

    /**
     * Resolve attachment (media) IDs to their file URLs.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, string> media id => source_url
     */
    public function fetchMediaUrls(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        $urls = [];

        foreach (array_chunk($ids, 100) as $chunk) {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->retry(2, 1500)
                ->acceptJson()
                ->get("{$this->baseUrl}/media", [
                    'include' => implode(',', $chunk),
                    'per_page' => 100,
                    '_fields' => 'id,source_url',
                ]);

            if (! $response->successful()) {
                continue;
            }

            foreach ($response->json() ?? [] as $media) {
                if (isset($media['id'], $media['source_url'])) {
                    $urls[(int) $media['id']] = (string) $media['source_url'];
                }
            }
        }

        return $urls;
    }
}
