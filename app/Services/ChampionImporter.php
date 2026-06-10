<?php

namespace App\Services;

use App\Models\Champion;
use App\Services\Concerns\MapsWordPressPosts;
use Illuminate\Support\Arr;

class ChampionImporter
{
    use MapsWordPressPosts;

    /** Languages to sync (WPML stores each as a separate post). */
    private const LANGUAGES = ['en', 'lo'];

    /** Minimum posts expected before a sync may prune, to guard against a broken API response. */
    private const SANITY_MIN = 5;

    public function __construct(
        private readonly WordPressClient $client = new WordPressClient,
    ) {}

    /**
     * @return array{imported: int, changed: int, archived: int}
     */
    public function import(bool $dryRun = false): array
    {
        $imported = 0;
        $changed = 0;
        $archived = 0;
        $seenSourceIds = [];

        foreach (self::LANGUAGES as $lang) {
            $posts = $this->client->fetchAll('champion', $lang);

            foreach ($posts as $post) {
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

                $existing = Champion::query()->where('source_id', $sourceId)->first();

                if (! $existing || $existing->content_hash !== $hash) {
                    $changed++;
                }

                $data['content_hash'] = $hash;
                Champion::query()->updateOrCreate(['source_id' => $sourceId], $data);
                $imported++;
            }
        }

        if (! $dryRun && count($seenSourceIds) >= self::SANITY_MIN) {
            $archived = Champion::query()->whereNotIn('source_id', $seenSourceIds)->delete();
        }

        return ['imported' => $imported, 'changed' => $changed, 'archived' => $archived];
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    private function mapPost(array $post, string $lang): array
    {
        $acf = is_array($post['acf'] ?? null) ? $post['acf'] : [];
        $terms = $this->extractTerms($post);
        $gallery = $this->extractGallery($acf);

        return [
            'language' => $lang,
            'slug' => $this->clean($post['slug'] ?? null),
            'name' => $this->htmlText(Arr::get($post, 'title.rendered')) ?? 'Untitled',
            'summary' => $this->clean($acf['pkl_story_headline'] ?? null)
                ?? $this->htmlText(Arr::get($post, 'excerpt.rendered')),
            'story' => $this->htmlText(Arr::get($post, 'content.rendered')),
            'authors' => $this->clean($acf['pkl_story_authors'] ?? null),
            'image_credits' => $this->clean($acf['pkl_story_image_credits'] ?? null),
            'featured_image' => $this->featuredImage($post, $acf, $gallery),
            'video_url' => $this->urlValue($acf['pkl_story_video'] ?? null),
            'file_url' => $this->urlValue($acf['pkl_story_file'] ?? null),
            'gallery' => $gallery,
            'category_actor' => Arr::first($terms['category-actor'] ?? []),
            'province' => Arr::first($terms['province'] ?? []),
            'sectors' => array_values($terms['sector'] ?? []),
            'topics' => array_values($terms['topic'] ?? []),
            'scales' => array_values($terms['scale'] ?? []),
            'source_url' => $this->clean($post['link'] ?? null),
            'source_modified_at' => $this->clean($post['modified'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $acf
     * @return array<int, string>
     */
    private function extractGallery(array $acf): array
    {
        $urls = [];

        foreach (Arr::get($acf, 'photo_gallery.pkl_photo_gallery', []) as $group) {
            foreach ((array) $group as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $url = $this->clean($item['full_image_url'] ?? $item['url'] ?? null);
                if ($url !== null) {
                    $urls[] = $url;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  array<string, mixed>  $acf
     * @param  array<int, string>  $gallery
     */
    private function featuredImage(array $post, array $acf, array $gallery): ?string
    {
        return $this->clean(Arr::get($post, '_embedded.wp:featuredmedia.0.source_url'))
            ?? $this->urlValue($acf['pkl_story_image'] ?? null)
            ?? ($gallery[0] ?? null);
    }

    private function urlValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['url'] ?? $value['full_image_url'] ?? null;
        }

        $value = $this->clean(is_string($value) ? $value : null);

        return ($value !== null && str_starts_with($value, 'http')) ? $value : null;
    }
}
