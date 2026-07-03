<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TtsController extends Controller
{
    /**
     * Synthesize the given text to speech (via edge-tts) and return an MP3.
     */
    public function speak(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
            'language' => ['nullable', 'in:en,lo'],
        ]);

        $text = trim(preg_replace('/\s+/u', ' ', $validated['text']) ?? '');
        $text = mb_substr($text, 0, (int) config('tts.max_chars', 3000));

        if ($text === '') {
            abort(422, 'No text to speak.');
        }

        $language = ($validated['language'] ?? 'en') === 'lo' ? 'lo' : 'en';
        $binary = (string) config('tts.edge_tts_bin');
        $voice = (string) config("tts.voices.{$language}");
        $file = tempnam(sys_get_temp_dir(), 'tts_').'.mp3';

        $result = Process::timeout(60)->run([
            $binary,
            '--voice', $voice,
            '--text', $text,
            '--write-media', $file,
        ]);

        if (! $result->successful() || ! is_file($file) || filesize($file) === 0) {
            @unlink($file);
            abort(500, 'Speech synthesis failed.');
        }

        return response()
            ->file($file, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'no-store'])
            ->deleteFileAfterSend(true);
    }
}
