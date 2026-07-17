<?php

namespace App\Services;

use App\Models\AppSetting;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class LibraryFilterCatalog
{
    private const FILTER_PAGES = [
        'en' => 'https://phakhaolao.la/en/discover/library/',
        'lo' => 'https://phakhaolao.la/discover/library/',
    ];

    /**
     * @return array{synced: int, failed: int}
     */
    public function syncAll(): array
    {
        $synced = 0;
        $failed = 0;

        foreach (array_keys(self::FILTER_PAGES) as $language) {
            try {
                if ($this->sync($language)) {
                    $synced++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['synced' => $synced, 'failed' => $failed];
    }

    public function sync(string $language): bool
    {
        $url = self::FILTER_PAGES[$language] ?? null;
        if ($url === null || ! $this->tableExists()) {
            return false;
        }

        $html = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->retry(2, 1000)
            ->timeout(30)
            ->get($url)
            ->throw()
            ->body();

        $catalog = $this->parse($html);
        if ($catalog === null) {
            return false;
        }

        AppSetting::query()->updateOrCreate(
            ['key' => $this->key($language)],
            ['value' => json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );

        return true;
    }

    /**
     * @return array{
     *     topics: list<array{value: string, label: string}>,
     *     types: list<array{value: string, label: string}>,
     *     languages: list<array{value: string, label: string}>,
     *     years: list<int>,
     *     authors: list<string>,
     *     sorts: array<string, string>
     * }|null
     */
    public function get(string $language = 'en'): ?array
    {
        if (! $this->tableExists()) {
            return null;
        }

        $value = AppSetting::query()->where('key', $this->key($language))->value('value');
        if (! is_string($value) || $value === '') {
            return null;
        }

        $catalog = json_decode($value, true);

        return is_array($catalog) ? $catalog : null;
    }

    /**
     * @return array{
     *     topics: list<array{value: string, label: string}>,
     *     types: list<array{value: string, label: string}>,
     *     languages: list<array{value: string, label: string}>,
     *     years: list<int>,
     *     authors: list<string>,
     *     sorts: array<string, string>
     * }|null
     */
    private function parse(string $html): ?array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $topics = $this->countedOptions($xpath, '_sft_topic[]');
        $types = $this->countedOptions($xpath, '_sft_resource-type[]');
        $languages = $this->countedOptions($xpath, '_sft_language[]');
        $years = $this->yearOptions($xpath, '_sfm_pkl_resource_year[]');
        $authors = $this->plainOptions($xpath, '_sfm_pkl_resource_author[]');
        $sorts = $this->sortOptions($xpath, '_sf_sort_order[]');

        if ($topics === [] && $types === [] && $languages === []) {
            return null;
        }

        return compact('topics', 'types', 'languages', 'years', 'authors', 'sorts');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function countedOptions(DOMXPath $xpath, string $name): array
    {
        return collect($this->options($xpath, $name))
            ->map(function (array $option): array {
                $value = preg_replace('/\s+\(\d+\)\s*$/u', '', $option['label']) ?? $option['label'];

                return ['value' => trim($value), 'label' => $option['label']];
            })
            ->filter(fn (array $option) => $option['value'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function yearOptions(DOMXPath $xpath, string $name): array
    {
        return collect($this->options($xpath, $name))
            ->map(fn (array $option) => preg_match('/\b(19|20)\d{2}\b/', $option['label'], $matches) === 1
                ? (int) $matches[0]
                : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function plainOptions(DOMXPath $xpath, string $name): array
    {
        return collect($this->options($xpath, $name))
            ->map(fn (array $option) => trim($option['label']))
            ->filter()
            ->unique(fn (string $value) => mb_strtolower($value))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function sortOptions(DOMXPath $xpath, string $name): array
    {
        return collect($this->options($xpath, $name))
            ->mapWithKeys(function (array $option): array {
                $value = match ($option['value']) {
                    'title+asc' => 'title_asc',
                    'title+desc' => 'title_desc',
                    'date+asc' => 'oldest',
                    'date+desc' => 'newest',
                    default => '',
                };

                return $value === '' ? [] : [$value => $option['label']];
            })
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function options(DOMXPath $xpath, string $name): array
    {
        $nodes = $xpath->query('//select[@name="'.$name.'"]/option');
        if ($nodes === false) {
            return [];
        }

        $options = [];

        foreach ($nodes as $node) {
            $value = trim((string) $node->attributes?->getNamedItem('value')?->nodeValue);
            if ($value === '') {
                continue;
            }

            $label = html_entity_decode((string) $node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);

            if ($label !== '') {
                $options[] = ['value' => $value, 'label' => $label];
            }
        }

        return $options;
    }

    private function key(string $language): string
    {
        return "library.filters.{$language}";
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('app_settings');
    }
}
