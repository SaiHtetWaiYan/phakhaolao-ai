<?php

use App\Ai\Agents\ChatAssistant;

/**
 * The assistant answered "which country won the World Cup 2026" even though the
 * prompt already listed sport as out of scope: the scope rule sat at the very
 * end, below four instructions telling the model never to refuse. These guard
 * the ordering that fixed it — they cannot prove the model obeys, only that the
 * instructions still say what they were rewritten to say.
 */
it('states the scope rule before the helpfulness rules', function () {
    $instructions = (string) (new ChatAssistant)->instructions();

    $scope = strpos($instructions, 'Scope — decide this first');
    $helpful = strpos($instructions, 'Be helpful first');

    expect($scope)->not->toBeFalse()
        ->and($helpful)->not->toBeFalse()
        ->and($scope)->toBeLessThan($helpful);
});

it('tells the model to decline even when it knows the answer', function () {
    $instructions = (string) (new ChatAssistant)->instructions();

    expect($instructions)
        ->toContain('Decline even when you know the answer')
        ->toContain('false premise')
        ->toContain('never override this section');
});

it('limits the never-refuse rule to in-scope questions', function () {
    $instructions = (string) (new ChatAssistant)->instructions();

    expect($instructions)->toContain('Never reply to an in-scope question with a bare refusal');
});

it('names the out-of-scope categories users actually asked about', function (string $category) {
    expect((string) (new ChatAssistant)->instructions())->toContain($category);
})->with(['coding', 'world news', 'sport', 'politics', 'travel or hotel advice', 'general knowledge']);
