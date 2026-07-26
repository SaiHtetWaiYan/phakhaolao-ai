<?php

it('requires an audio file to transcribe', function () {
    $this->postJson('/transcribe', [])->assertStatus(422);
});

// Browsers record Opus in WebM, the mobile app records it in Ogg. Declaring the
// wrong container makes Google read a sample rate of 0 and reject the request.
it('picks the recognition encoding from the audio container', function (string $magic, string $encoding, ?int $rate) {
    $method = new ReflectionMethod(\App\Http\Controllers\SttController::class, 'audioFormat');
    $method->setAccessible(true);

    $format = $method->invoke(app(\App\Http\Controllers\SttController::class), $magic.'rest-of-file');

    expect($format['encoding'])->toBe($encoding)
        ->and($format['sampleRateHertz'])->toBe($rate);
})->with([
    'ogg from the app' => ['OggS', 'OGG_OPUS', 48000],
    'webm from a browser' => ["\x1A\x45\xDF\xA3", 'WEBM_OPUS', null],
    'wav' => ['RIFF', 'LINEAR16', null],
    'flac' => ['fLaC', 'FLAC', null],
]);

// LINEAR16 means headerless PCM to Google, so it rejects the request unless a
// rate is supplied. Read the real one from the WAV header.
it('reads the sample rate from a wav header', function (int $rate, ?int $expected) {
    $header = 'RIFF'.str_repeat("\x00", 20).pack('V', $rate).str_repeat("\x00", 8);

    $method = new ReflectionMethod(\App\Http\Controllers\SttController::class, 'wavSampleRate');
    $method->setAccessible(true);

    expect($method->invoke(app(\App\Http\Controllers\SttController::class), $header))->toBe($expected);
})->with([
    'ios hardware rate' => [44100, 44100],
    'requested rate' => [16000, 16000],
    'opus native' => [48000, 48000],
    'implausibly low is ignored' => [10, null],
    'implausibly high is ignored' => [192000, null],
]);

it('declares the wav rate to the recogniser', function () {
    $header = 'RIFF'.str_repeat("\x00", 20).pack('V', 44100).str_repeat("\x00", 8);

    $method = new ReflectionMethod(\App\Http\Controllers\SttController::class, 'audioFormat');
    $method->setAccessible(true);

    $format = $method->invoke(app(\App\Http\Controllers\SttController::class), $header);

    expect($format['encoding'])->toBe('LINEAR16')
        ->and($format['sampleRateHertz'])->toBe(44100);
});
