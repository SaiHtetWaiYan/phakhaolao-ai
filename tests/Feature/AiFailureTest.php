<?php

use App\Support\AiFailure;

it('recognises the ways a provider says it is being asked too often', function (string $message) {
    expect(AiFailure::isRateLimit(new RuntimeException($message)))->toBeTrue();
})->with([
    'prism wording' => ['Application rate limited by AI provider [openai].'],
    'openai code' => ['rate_limit_exceeded'],
    'http status' => ['Request failed with status 429'],
    'plain english' => ['Too Many Requests'],
]);

it('does not mistake other failures for one', function (string $message) {
    expect(AiFailure::isRateLimit(new RuntimeException($message)))->toBeFalse();
})->with([
    'timeout' => ['cURL error 28: Operation timed out'],
    'auth' => ['Incorrect API key provided'],
    'nothing useful' => ['Something went wrong'],
]);

// "Try again" is only worth saying when trying again is likely to work.
it('says a rate limit will pass, and stays vague about anything else', function () {
    expect(AiFailure::message(new RuntimeException('rate limit')))
        ->toContain('try again in a moment')
        ->and(AiFailure::message(new RuntimeException('cURL error 28')))
        ->toContain('encountered an error');
});
