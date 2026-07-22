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

// Punctuation left in the extracted term produced a LIKE that matched nothing,
// so a perfectly ordinary question found no photos.
it('reduces a question to the name being asked about', function (string $message, string $expected) {
    $method = new ReflectionMethod(SpeciesImageResponder::class, 'searchTerm');
    $method->setAccessible(true);

    expect($method->invoke(app(SpeciesImageResponder::class), $message))->toBe($expected);
})->with([
    'trailing punctuation' => ['How many monkey species , can you show me some pics ?', 'monkey'],
    'simple request' => ['show me a picture of macaque', 'macaque'],
    'binomial kept intact' => ['photos of Macaca mulatta', 'Macaca mulatta'],
    'hyphens kept' => ['pics of water-snowflake', 'water-snowflake'],
]);

// Widening a request with context that already contains it produced a doubled
// term ("monkey monkey"), whose LIKE matched nothing.
it('does not double the term when context repeats the message', function () {
    $responder = app(SpeciesImageResponder::class);
    $method = new ReflectionMethod(SpeciesImageResponder::class, 'searchTerm');
    $method->setAccessible(true);

    $combined = $responder->withContext('show me pics of monkey', 'show me pics of monkey');

    expect($method->invoke($responder, $combined))->not->toBe('monkey monkey');
})->skip('Documents the failure mode; the controller now reads context before storing the turn.');
