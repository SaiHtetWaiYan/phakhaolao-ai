<?php

return [
    // 'google' (Gemini-TTS, multilingual incl. Lao) or 'edge' (edge-tts, free).
    'provider' => env('TTS_PROVIDER', 'google'),

    // Max characters to synthesize per request.
    'max_chars' => 3000,

    'google' => [
        // Path (relative to base_path) to the service-account JSON.
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'model' => env('GOOGLE_TTS_MODEL', 'gemini-2.5-flash-tts'),
        'voice' => env('GOOGLE_TTS_VOICE', 'Achernar'),
    ],

    // edge-tts fallback (free, no key).
    'edge_tts_bin' => env('EDGE_TTS_BIN', 'edge-tts'),
    'voices' => [
        'en' => env('TTS_VOICE_EN', 'en-US-AriaNeural'),
        'lo' => env('TTS_VOICE_LO', 'lo-LA-ChanthavongNeural'),
    ],
];
