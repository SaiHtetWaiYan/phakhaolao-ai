<?php

use App\Http\Controllers\TtsController;

/** Reach the private chooser directly; the routing is the whole behaviour. */
function chosenVoice(string $text, ?string $language = null): array
{
    $method = new ReflectionMethod(TtsController::class, 'googleVoice');
    $method->setAccessible(true);

    return $method->invoke(app(TtsController::class), $text, $language);
}

// Gemini-TTS is the only Google voice that speaks Lao, but it is generative
// and roughly ten times slower than a standard voice on the same sentence.
it('reads Lao with the multilingual model', function () {
    $voice = chosenVoice('ຊະນິດພັນນີ້ພົບໃນລາວ');

    expect($voice['languageCode'])->toBe('lo-LA')
        ->and($voice['modelName'])->toBe(config('tts.google.model'));
});

it('reads English with the faster standard voice', function () {
    $voice = chosenVoice('This fern grows in wet places.');

    expect($voice['languageCode'])->toBe('en-US')
        ->and($voice['name'])->toBe(config('tts.google.english_voice'))
        ->and($voice)->not->toHaveKey('modelName');
});

// A scientific name inside a Lao sentence must not send it to the English
// voice, which cannot pronounce the surrounding script.
it('treats a mixed sentence as Lao', function () {
    expect(chosenVoice('ຜັກກູດ (Diplazium esculentum) ພົບໃນລາວ')['languageCode'])
        ->toBe('lo-LA');
});

// A reply is read in pieces. Deciding per piece changed the speaker partway
// through a mixed answer, so the caller fixes the voice for the whole reply.
it('takes the caller\'s language over what a piece happens to contain', function () {
    expect(chosenVoice('It grows near streams and in wet ground.', 'lo')['languageCode'])
        ->toBe('lo-LA')
        ->and(chosenVoice('ຜັກກູດ', 'en')['languageCode'])
        ->toBe('en-US');
});

it('still detects the language when the caller says nothing', function () {
    expect(chosenVoice('ຜັກກູດພົບໃນລາວ')['languageCode'])->toBe('lo-LA')
        ->and(chosenVoice('This fern grows in wet places.')['languageCode'])->toBe('en-US');
});
