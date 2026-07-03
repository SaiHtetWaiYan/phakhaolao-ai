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

        [$primary, $alternatives] = match ($request->input('language', 'auto')) {
            'lo' => ['lo-LA', []],
            'en' => ['en-US', []],
            default => ['lo-LA', ['en-US']],
        };

        $content = base64_encode((string) file_get_contents($request->file('audio')->getRealPath()));

        try {
            $response = Http::withToken($this->googleAccessToken())
                ->timeout(60)
                ->post('https://speech.googleapis.com/v1/speech:recognize', [
                    'config' => array_filter([
                        'encoding' => 'WEBM_OPUS',
                        'languageCode' => $primary,
                        'alternativeLanguageCodes' => $alternatives ?: null,
                        'enableAutomaticPunctuation' => true,
                    ]),
                    'audio' => ['content' => $content],
                ]);
        } catch (\Throwable $e) {
            Log::error('STT request failed', ['error' => $e->getMessage()]);

            return response()->json(['text' => '', 'error' => 'transcription_failed'], 200);
        }

        if (! $response->successful()) {
            Log::error('STT failed', ['status' => $response->status(), 'error' => $response->json('error')]);

            return response()->json(['text' => '', 'error' => 'transcription_failed'], 200);
        }

        $text = collect($response->json('results', []))
            ->map(fn (array $result): string => (string) ($result['alternatives'][0]['transcript'] ?? ''))
            ->implode(' ');

        return response()->json(['text' => trim($text)]);
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
