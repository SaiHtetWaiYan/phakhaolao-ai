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
}
