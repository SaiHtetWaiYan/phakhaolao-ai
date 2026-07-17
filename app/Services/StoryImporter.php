<?php

namespace App\Services;

use App\Models\Story;
use App\Services\Concerns\MapsWordPressPosts;
use Illuminate\Support\Arr;

class StoryImporter
{
    use MapsWordPressPosts;

    /** Languages to sync (WPML stores each as a separate post). */
    private const LANGUAGES = ['en', 'lo'];

    /** Minimum posts expected before a sync may prune, to guard against a broken API response. */
    private const SANITY_MIN = 10;

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
            foreach ($this->client->fetchAll('story', $lang) as $post) {
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

                $existing = Story::query()->where('source_id', $sourceId)->first();

                if (! $existing || $existing->content_hash !== $hash) {
                    $changed++;
                }

                $data['content_hash'] = $hash;
                Story::query()->updateOrCreate(['source_id' => $sourceId], $data);
                $imported++;
            }
        }

        if (! $dryRun && count($seenSourceIds) >= self::SANITY_MIN) {
            $archived = Story::query()->whereNotIn('source_id', $seenSourceIds)->delete();
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

        return [
            'language' => $lang,
            'slug' => $this->clean($post['slug'] ?? null),
            'title' => $this->htmlText(Arr::get($post, 'title.rendered')) ?? 'Untitled',
            'authors' => $this->clean($acf['pkl_story_authors'] ?? null),
            'summary' => $this->clean($acf['pkl_story_headline'] ?? null)
                ?? $this->htmlText(Arr::get($post, 'excerpt.rendered')),
            'story' => $this->htmlText(Arr::get($post, 'content.rendered')),
            'image_credits' => $this->clean($acf['pkl_story_image_credits'] ?? null),
            'story_types' => array_values($terms['story-type'] ?? []),
            'source_url' => $this->clean($post['link'] ?? null),
            'source_modified_at' => $this->clean($post['modified'] ?? null),
        ];
    }
}
