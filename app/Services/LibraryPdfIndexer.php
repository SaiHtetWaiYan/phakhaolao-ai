<?php

namespace App\Services;

use App\Models\LibraryResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Smalot\PdfParser\Config as PdfConfig;
use Smalot\PdfParser\Parser;
use Throwable;

class LibraryPdfIndexer
{
    public function __construct(
        private readonly int $chunkSize = 1200,
        private readonly int $dimensions = 1536,
        private readonly bool $store = true,
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

        $body = $this->fetchPdf($resource, $url);

        if ($body === null) {
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

    /**
     * Fetch the PDF, saving it under storage/app/library-pdfs and reusing a
     * previously downloaded copy so re-runs never re-download. Returns null on
     * a failed download.
     */
    private function fetchPdf(LibraryResource $resource, string $url): ?string
    {
        $path = $this->storagePath($resource);
        $disk = Storage::disk('local');

        if ($this->store && $disk->exists($path)) {
            $existing = $disk->get($path);

            if (is_string($existing) && $existing !== '') {
                return $existing;
            }
        }

        try {
            $body = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->timeout(60)
                ->get($this->encodeUrl($url))
                ->throw()
                ->body();
        } catch (Throwable) {
            return null;
        }

        if ($body === '') {
            return null;
        }

        if ($this->store) {
            $disk->put($path, $body);
        }

        return $body;
    }

    private function storagePath(LibraryResource $resource): string
    {
        return 'library-pdfs/'.$resource->id.'.pdf';
    }

    /**
     * Percent-encode each path segment so URLs with raw non-ASCII characters
     * (e.g. Lao filenames) download correctly. Idempotent for already-encoded URLs.
     */
    private function encodeUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['path'])) {
            return $url;
        }

        $path = implode('/', array_map(
            fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            explode('/', $parts['path'])
        ));

        $encoded = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $encoded .= ':'.$parts['port'];
        }

        $encoded .= $path;

        if (isset($parts['query'])) {
            $encoded .= '?'.$parts['query'];
        }

        return $encoded;
    }

    private function extractText(string $body): string
    {
        // Prefer pdftotext (poppler): it streams and stays within bounded memory,
        // where smalot/pdfparser can exhaust gigabytes on some compressed streams.
        $text = $this->pdftotextBinary() !== null
            ? $this->extractWithPdftotext($body)
            : $this->extractWithSmalot($body);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function extractWithPdftotext(string $body): string
    {
        $binary = $this->pdftotextBinary();

        if ($binary === null) {
            return '';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pkl_pdf_');

        if ($tmp === false) {
            return '';
        }

        try {
            file_put_contents($tmp, $body);
            $result = Process::timeout(120)->run([$binary, '-q', '-enc', 'UTF-8', $tmp, '-']);

            return $result->successful() ? $result->output() : '';
        } catch (Throwable) {
            return '';
        } finally {
            @unlink($tmp);
        }
    }

    private function extractWithSmalot(string $body): string
    {
        try {
            // Discarding image content keeps memory bounded on image-heavy PDFs.
            $config = new PdfConfig;
            $config->setRetainImageContent(false);

            return (new Parser([], $config))->parseContent($body)->getText();
        } catch (Throwable) {
            return '';
        }
    }

    private function pdftotextBinary(): ?string
    {
        static $binary = false;

        if ($binary !== false) {
            return $binary;
        }

        foreach (['/opt/homebrew/bin/pdftotext', '/usr/local/bin/pdftotext', '/usr/bin/pdftotext'] as $path) {
            if (is_executable($path)) {
                return $binary = $path;
            }
        }

        return $binary = null;
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
