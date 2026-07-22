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
