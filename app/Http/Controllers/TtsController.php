<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class TtsController extends Controller
{
    /**
     * Synthesize the given (possibly mixed Lao/English) text to speech and
     * return an MP3.
     */
    public function speak(Request $request): Response
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
        ]);

        $text = trim(preg_replace('/\s+/u', ' ', $validated['text']) ?? '');
        $text = mb_substr($text, 0, (int) config('tts.max_chars', 3000));

        if ($text === '') {
            abort(422, 'No text to speak.');
        }

        $audio = config('tts.provider') === 'edge'
            ? $this->edgeAudio($text)
            : $this->googleAudio($text);

        if ($audio === '') {
            abort(500, 'Speech synthesis failed.');
        }

        return response($audio, 200, [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Google Gemini-TTS — one generative multilingual voice that reads Lao and
     * embedded English/scientific names correctly in a single pass.
     */
    private function googleAudio(string $text): string
    {
        $config = config('tts.google');
        $languageCode = preg_match('/[\x{0E80}-\x{0EFF}]/u', $text) === 1 ? 'lo-LA' : 'en-US';

        try {
            $response = Http::withToken($this->googleAccessToken())
                ->timeout(60)
                ->post('https://texttospeech.googleapis.com/v1/text:synthesize', [
                    'input' => ['text' => $text],
                    'voice' => [
                        'languageCode' => $languageCode,
                        'name' => $config['voice'],
                        'modelName' => $config['model'],
                    ],
                    'audioConfig' => ['audioEncoding' => 'MP3'],
                ]);
        } catch (\Throwable $e) {
            Log::error('Google TTS request failed', ['error' => $e->getMessage()]);

            return '';
        }

        $audio = $response->json('audioContent');

        if (! $response->successful() || ! is_string($audio) || $audio === '') {
            Log::error('Google TTS failed', ['status' => $response->status(), 'error' => $response->json('error')]);

            return '';
        }

        return (string) base64_decode($audio);
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

    /**
     * edge-tts fallback: split into Lao/English runs, speak each with its voice,
     * and concatenate the MP3s.
     */
    private function edgeAudio(string $text): string
    {
        $binary = (string) config('tts.edge_tts_bin');
        $audio = '';

        foreach ($this->segments($text) as $segment) {
            $voice = (string) config("tts.voices.{$segment['lang']}");
            $file = tempnam(sys_get_temp_dir(), 'tts_').'.mp3';

            $result = Process::timeout(60)->run([
                $binary, '--voice', $voice, '--text', $segment['text'], '--write-media', $file,
            ]);

            if ($result->successful() && is_file($file) && filesize($file) > 0) {
                $audio .= (string) file_get_contents($file);
            }

            @unlink($file);
        }

        return $audio;
    }

    /**
     * Split text into consecutive Lao / English runs. Neutral characters
     * (spaces, punctuation, digits) stay with the current run.
     *
     * @return list<array{lang: string, text: string}>
     */
    private function segments(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $runs = [];
        $current = null;
        $buffer = '';

        foreach ($chars as $char) {
            $lang = match (true) {
                preg_match('/[\x{0E80}-\x{0EFF}]/u', $char) === 1 => 'lo',
                preg_match('/[A-Za-z]/', $char) === 1 => 'en',
                default => null,
            };

            if ($lang !== null && $current !== null && $lang !== $current) {
                $runs[] = ['lang' => $current, 'text' => $buffer];
                $buffer = '';
            }

            if ($lang !== null) {
                $current = $lang;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $runs[] = ['lang' => $current ?? 'en', 'text' => $buffer];
        }

        return array_values(array_filter(
            $runs,
            fn (array $run): bool => preg_match('/[\p{L}]/u', $run['text']) === 1
        ));
    }
}
