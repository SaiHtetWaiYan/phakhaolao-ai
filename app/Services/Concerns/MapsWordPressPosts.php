<?php

namespace App\Services\Concerns;

use Illuminate\Support\Arr;

trait MapsWordPressPosts
{
    /**
     * Group embedded taxonomy terms by taxonomy name.
     *
     * @param  array<string, mixed>  $post
     * @return array<string, array<int, string>>
     */
    protected function extractTerms(array $post): array
    {
        $grouped = [];

        foreach (Arr::get($post, '_embedded.wp:term', []) as $group) {
            foreach ((array) $group as $term) {
                $taxonomy = $term['taxonomy'] ?? null;
                $name = $this->htmlText($term['name'] ?? null);

                if ($taxonomy !== null && $name !== null) {
                    $grouped[$taxonomy][] = $name;
                }
            }
        }

        return $grouped;
    }

    protected function htmlText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->clean($value);
    }

    protected function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
