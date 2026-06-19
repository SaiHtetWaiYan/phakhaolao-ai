<?php

namespace App\Services;

use App\Models\LibraryResource;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Embeddings;
use Smalot\PdfParser\Parser;
use Throwable;

class LibraryPdfIndexer
{
    public function __construct(
        private readonly int $chunkSize = 1200,
        private readonly int $dimensions = 1536,
    ) {}

    /**
     * Download a resource's PDF, extract its text, split it into chunks, embed
     * them and store them. Returns the resulting status:
     * done | skipped | no_text | failed.
     */
    public function index(LibraryResource $resource, bool $force = false): string
    {
        $url = trim((string) $resource->file_url);

        if ($url === '' || ! str_contains($url, 'phakhaolao.la') || preg_match('/\.pdf$/i', $url) !== 1) {
            return $this->markStatus($resource, 'skipped');
        }

        try {
            $body = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->timeout(60)
                ->get($url)
                ->throw()
                ->body();
        } catch (Throwable) {
            return $this->markStatus($resource, 'failed');
        }

        $text = $this->extractText($body);

        if ($text === '') {
            // Almost always a scanned (image-only) PDF that needs OCR.
            return $this->markStatus($resource, 'no_text');
        }

        $hash = md5($text);

        if (! $force && $resource->pdf_status === 'done' && $resource->pdf_text_hash === $hash) {
            return 'skipped';
        }

        $chunks = self::chunkText($text, $this->chunkSize);
        $embeddings = $this->embed($chunks);

        $resource->chunks()->delete();

        foreach ($chunks as $index => $content) {
            $embedding = $embeddings[$index] ?? null;

            $resource->chunks()->create([
                'chunk_index' => $index,
                'content' => $content,
                'content_hash' => md5($content),
                'embedding' => is_array($embedding) ? $embedding : null,
            ]);
        }

        $resource->forceFill([
            'pdf_status' => 'done',
            'pdf_text_hash' => $hash,
            'pdf_indexed_at' => now(),
        ])->save();

        return 'done';
    }

    /**
     * Split text into chunks of roughly $maxChars characters, breaking on word
     * boundaries so no word is cut in half.
     *
     * @return list<string>
     */
    public static function chunkText(string $text, int $maxChars = 1200): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($text === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        $chunks = [];
        $current = '';

        foreach ($words as $word) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($word) + 1 > $maxChars) {
                $chunks[] = $current;
                $current = '';
            }

            $current = $current === '' ? $word : $current.' '.$word;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function extractText(string $body): string
    {
        try {
            $document = (new Parser)->parseContent($body);
            $text = $document->getText();
        } catch (Throwable) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param  list<string>  $chunks
     * @return array<int, array<float>|null>
     */
    private function embed(array $chunks): array
    {
        if ($chunks === []) {
            return [];
        }

        try {
            return Embeddings::for($chunks)->dimensions($this->dimensions)->generate()->embeddings;
        } catch (Throwable) {
            $results = [];

            foreach ($chunks as $chunk) {
                try {
                    $results[] = Embeddings::for([$chunk])->dimensions($this->dimensions)->generate()->first();
                } catch (Throwable) {
                    $results[] = null;
                }
            }

            return $results;
        }
    }

    private function markStatus(LibraryResource $resource, string $status): string
    {
        $resource->forceFill([
            'pdf_status' => $status,
            'pdf_indexed_at' => now(),
        ])->save();

        return $status;
    }
}
