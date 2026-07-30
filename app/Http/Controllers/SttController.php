<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SttController extends Controller
{
    private const RECOGNIZE_URL = 'https://speech.googleapis.com/v1/speech:recognize';

    /**
     * Transcribe a recorded audio clip to text via Google Speech-to-Text.
     * Lao (lo-LA) transcribes correctly in Lao script, unlike OpenAI Whisper.
     */
    public function transcribe(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
            'language' => ['nullable', 'in:en,lo,auto'],
        ]);

        $language = $request->input('language', 'auto');
        $binary = (string) file_get_contents($request->file('audio')->getRealPath());
        $format = $this->audioFormat($binary);
        $content = base64_encode($binary);

        // Google can't auto-detect Lao vs English, so for "auto" we transcribe
        // with both and keep the higher-confidence result.
        $result = ($language === 'en' || $language === 'lo')
            ? $this->recognize($content, $language === 'lo' ? 'lo-LA' : 'en-US', $format)
            : $this->recognizeEitherLanguage($content, $format);

        return response()->json(['text' => $result['text']]);
    }

    /**
     * Identify the container from its magic bytes.
     *
     * Browsers record Opus in WebM while the mobile app records it in Ogg.
     * Declaring the wrong one makes Google read a sample rate of 0 and reject
     * the request, so the container decides the encoding rather than a guess.
     *
     * @return array{encoding: string, sampleRateHertz: int|null}
     */
    private function audioFormat(string $binary): array
    {
        if (str_starts_with($binary, 'OggS')) {
            // Ogg carries no rate Google will read here; Opus is always 48 kHz.
            return ['encoding' => 'OGG_OPUS', 'sampleRateHertz' => 48000];
        }

        if (str_starts_with($binary, "\x1A\x45\xDF\xA3")) {
            // EBML (WebM/Matroska): the rate is in the container.
            return ['encoding' => 'WEBM_OPUS', 'sampleRateHertz' => null];
        }

        if (str_starts_with($binary, 'RIFF')) {
            // LINEAR16 means headerless PCM to Google, so it demands a rate.
            // Read the real one from the WAV header rather than assume: iOS
            // records at the hardware rate regardless of what was requested.
            return ['encoding' => 'LINEAR16', 'sampleRateHertz' => $this->wavSampleRate($binary)];
        }

        if (str_starts_with($binary, 'fLaC')) {
            return ['encoding' => 'FLAC', 'sampleRateHertz' => null];
        }

        Log::warning('Unrecognised audio container for transcription', [
            'magic' => bin2hex(substr($binary, 0, 4)),
        ]);

        return ['encoding' => 'WEBM_OPUS', 'sampleRateHertz' => null];
    }

    /**
     * Transcribe base64 audio in a single language.
     *
     * @param  array{encoding: string, sampleRateHertz: int|null}  $format
     * @return array{text: string, confidence: float}
     */
    private function recognize(string $content, string $languageCode, array $format): array
    {
        try {
            $response = Http::withToken($this->googleAccessToken())
                ->timeout(60)
                ->post(self::RECOGNIZE_URL, $this->payload($content, $languageCode, $format));
        } catch (\Throwable $e) {
            Log::error('STT request failed', ['error' => $e->getMessage()]);

            return ['text' => '', 'confidence' => 0.0];
        }

        return $this->transcript($response, $format);
    }

    /**
     * Try both languages and keep whichever came back more confident.
     *
     * Concurrently: one after the other doubled the wait on every recording,
     * and the two requests have nothing to say to each other.
     *
     * @param  array{encoding: string, sampleRateHertz: int|null}  $format
     * @return array{text: string, confidence: float}
     */
    private function recognizeEitherLanguage(string $content, array $format): array
    {
        $token = $this->googleAccessToken();

        try {
            $responses = Http::pool(fn (Pool $pool): array => [
                $pool->as('en')->withToken($token)->timeout(60)
                    ->post(self::RECOGNIZE_URL, $this->payload($content, 'en-US', $format)),
                $pool->as('lo')->withToken($token)->timeout(60)
                    ->post(self::RECOGNIZE_URL, $this->payload($content, 'lo-LA', $format)),
            ]);
        } catch (\Throwable $e) {
            Log::error('STT request failed', ['error' => $e->getMessage()]);

            return ['text' => '', 'confidence' => 0.0];
        }

        $english = $this->transcript($responses['en'] ?? null, $format);
        $lao = $this->transcript($responses['lo'] ?? null, $format);

        return $english['confidence'] >= $lao['confidence'] ? $english : $lao;
    }

    /**
     * @param  array{encoding: string, sampleRateHertz: int|null}  $format
     * @return array<string, mixed>
     */
    private function payload(string $content, string $languageCode, array $format): array
    {
        $config = [
            'encoding' => $format['encoding'],
            'languageCode' => $languageCode,
            'enableAutomaticPunctuation' => true,
        ];

        if ($format['sampleRateHertz'] !== null) {
            $config['sampleRateHertz'] = $format['sampleRateHertz'];
        }

        return ['config' => $config, 'audio' => ['content' => $content]];
    }

    /**
     * Read a recognition response, treating any failure as nothing heard.
     *
     * @param  array{encoding: string, sampleRateHertz: int|null}  $format
     * @return array{text: string, confidence: float}
     */
    private function transcript(mixed $response, array $format): array
    {
        if (! $response instanceof Response || ! $response->successful()) {
            Log::error('STT failed', [
                'status' => $response instanceof Response ? $response->status() : null,
                'error' => $response instanceof Response ? $response->json('error') : (string) $response,
                'sent_encoding' => $format['encoding'],
                'sent_rate' => $format['sampleRateHertz'],
            ]);

            return ['text' => '', 'confidence' => 0.0];
        }

        $results = $response->json('results', []);
        $text = collect($results)
            ->map(fn (array $result): string => (string) ($result['alternatives'][0]['transcript'] ?? ''))
            ->implode(' ');

        return [
            'text' => trim($text),
            'confidence' => (float) ($results[0]['alternatives'][0]['confidence'] ?? 0.0),
        ];
    }

    /**
     * The sample rate declared in a WAV file's "fmt " chunk.
     *
     * The chunk is not always at a fixed offset — writers may put other chunks
     * ahead of it — so walk the chunk list rather than reading a fixed
     * position, which yielded garbage and left the rate unset.
     */
    private function wavSampleRate(string $binary): ?int
    {
        $length = strlen($binary);
        $offset = 12; // past "RIFF", size, "WAVE"

        while ($offset + 8 <= $length) {
            $id = substr($binary, $offset, 4);
            $size = unpack('V', substr($binary, $offset + 4, 4))[1] ?? 0;

            if ($id === 'fmt ' && $offset + 12 <= $length) {
                $rate = unpack('V', substr($binary, $offset + 12, 4))[1] ?? 0;

                return $rate >= 8000 && $rate <= 48000 ? $rate : null;
            }

            if ($size <= 0) {
                break;
            }

            // Chunks are word-aligned, so an odd size carries a pad byte.
            $offset += 8 + $size + ($size % 2);
        }

        return null;
    }

    private function googleAccessToken(): string
    {
        return Cache::remember('google_tts_access_token', now()->addMinutes(50), function (): string {
            $path = base_path((string) config('tts.google.credentials'));
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/cloud-platform',
                json_decode((string) file_get_contents($path), true)
            );

            return (string) ($credentials->fetchAuthToken()['access_token'] ?? '');
        });
    }
}
