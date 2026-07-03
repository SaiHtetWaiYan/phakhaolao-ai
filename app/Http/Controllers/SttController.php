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
        $content = base64_encode((string) file_get_contents($request->file('audio')->getRealPath()));

        // Google can't auto-detect Lao vs English, so for "auto" we transcribe
        // with both and keep the higher-confidence result.
        if ($language === 'en' || $language === 'lo') {
            $result = $this->recognize($content, $language === 'lo' ? 'lo-LA' : 'en-US');
        } else {
            $english = $this->recognize($content, 'en-US');
            $lao = $this->recognize($content, 'lo-LA');
            $result = $english['confidence'] >= $lao['confidence'] ? $english : $lao;
        }

        return response()->json(['text' => $result['text']]);
    }

    /**
     * Transcribe base64 audio in a single language.
     *
     * @return array{text: string, confidence: float}
     */
    private function recognize(string $content, string $languageCode): array
    {
        try {
            $response = Http::withToken($this->googleAccessToken())
                ->timeout(60)
                ->post('https://speech.googleapis.com/v1/speech:recognize', [
                    'config' => [
                        'encoding' => 'WEBM_OPUS',
                        'languageCode' => $languageCode,
                        'enableAutomaticPunctuation' => true,
                    ],
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
