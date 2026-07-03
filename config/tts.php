<?php

return [
    // Absolute path to the edge-tts binary (installed in a Python venv).
    'edge_tts_bin' => env('EDGE_TTS_BIN', 'edge-tts'),

    // Neural voices per language.
    'voices' => [
        'en' => env('TTS_VOICE_EN', 'en-US-AriaNeural'),
        'lo' => env('TTS_VOICE_LO', 'lo-LA-ChanthavongNeural'),
    ],

    // Max characters to synthesize per request.
    'max_chars' => 3000,
];
