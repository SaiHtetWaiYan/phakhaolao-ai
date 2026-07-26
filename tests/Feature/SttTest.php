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

/** A canonical WAV header, optionally with a chunk placed before "fmt ". */
function wavHeader(int $rate, string $leadingChunk = ''): string
{
    $fmt = 'fmt '.pack('V', 16)          // chunk id + size
        .pack('v', 1)                     // PCM
        .pack('v', 1)                     // mono
        .pack('V', $rate)                 // sample rate
        .pack('V', $rate * 2)             // byte rate
        .pack('v', 2)                     // block align
        .pack('v', 16);                   // bits per sample

    $body = 'WAVE'.$leadingChunk.$fmt.'data'.pack('V', 0);

    return 'RIFF'.pack('V', strlen($body)).$body;
}

function wavRate(string $binary): ?int
{
    $method = new ReflectionMethod(\App\Http\Controllers\SttController::class, 'wavSampleRate');
    $method->setAccessible(true);

    return $method->invoke(app(\App\Http\Controllers\SttController::class), $binary);
}

// LINEAR16 means headerless PCM to Google, so it rejects a request that omits
// the rate. It has to come from the file.
it('reads the sample rate from a wav header', function (int $rate, ?int $expected) {
    expect(wavRate(wavHeader($rate)))->toBe($expected);
})->with([
    'ios hardware rate' => [44100, 44100],
    'requested rate' => [16000, 16000],
    'opus native' => [48000, 48000],
    'implausibly low is ignored' => [10, null],
    'implausibly high is ignored' => [192000, null],
]);

// Writers may put other chunks ahead of "fmt ", so a fixed offset read garbage
// and left the rate unset.
it('finds the rate when another chunk precedes fmt', function () {
    $leading = 'LIST'.pack('V', 10).str_repeat("\x00", 10);

    expect(wavRate(wavHeader(44100, $leading)))->toBe(44100);
});

it('gives up on a truncated header rather than guessing', function () {
    expect(wavRate('RIFF'))->toBeNull();
});

it('declares the wav rate to the recogniser', function () {
    $method = new ReflectionMethod(\App\Http\Controllers\SttController::class, 'audioFormat');
    $method->setAccessible(true);

    $format = $method->invoke(app(\App\Http\Controllers\SttController::class), wavHeader(44100));

    expect($format['encoding'])->toBe('LINEAR16')
        ->and($format['sampleRateHertz'])->toBe(44100);
});
