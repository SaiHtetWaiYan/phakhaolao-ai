<?php

use App\Services\SpeciesImageResponder;

// "pics" is as common as "pictures" in real questions, and missing it meant a
// photo request fell through to the model and came back without images.
it('recognises a photo request', function (string $message) {
    expect(app(SpeciesImageResponder::class)->isImageRequest($message))->toBeTrue();
})->with([
    'how many monkey species, can you show me some pics?',
    'show me a pic',
    'can you show me some photos',
    'do you have a picture of it',
    'show images please',
    'any photographs?',
]);

it('leaves ordinary questions to the model', function (string $message) {
    expect(app(SpeciesImageResponder::class)->isImageRequest($message))->toBeFalse();
})->with([
    'how many monkey species are there?',
    'tell me about macaques',
    '',
    'how many champions per province',
]);

it('widens short follow-ups with recent context', function () {
    $responder = app(SpeciesImageResponder::class);

    expect($responder->withContext('show me its photo', 'Macaca mulatta'))
        ->toContain('Macaca mulatta');
});

it('leaves a long, self-contained question alone', function () {
    $responder = app(SpeciesImageResponder::class);
    $message = 'show me photographs of the Phayre leaf monkey found in northern Laos';

    expect($responder->withContext($message, 'unrelated earlier chatter'))
        ->toBe($message);
});
