<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SttController extends Controller
{
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
        if ($language === 'en' || $language === 'lo') {
            $result = $this->recognize($content, $language === 'lo' ? 'lo-LA' : 'en-US', $format);
        } else {
            $english = $this->recognize($content, 'en-US', $format);
            $lao = $this->recognize($content, 'lo-LA', $format);
            $result = $english['confidence'] >= $lao['confidence'] ? $english : $lao;
        }

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
            return ['encoding' => 'LINEAR16', 'sampleRateHertz' => null];
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
        $config = [
            'encoding' => $format['encoding'],
            'languageCode' => $languageCode,
            'enableAutomaticPunctuation' => true,
        ];

        if ($format['sampleRateHertz'] !== null) {
            $config['sampleRateHertz'] = $format['sampleRateHertz'];
        }

        try {
            $response = Http::withToken($this->googleAccessToken())
                ->timeout(60)
                ->post('https://speech.googleapis.com/v1/speech:recognize', [
                    'config' => $config,
                    'audio' => ['content' => $content],
                ]);
        } catch (\Throwable $e) {
            Log::error('STT request failed', ['error' => $e->getMessage()]);

            return ['text' => '', 'confidence' => 0.0];
        }

        if (! $response->successful()) {
            Log::error('STT failed', ['status' => $response->status(), 'error' => $response->json('error')]);

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
