<?php

use App\Http\Controllers\ChatController;
use Illuminate\Http\Request;

function callLimit(string $guest): mixed
{
    $controller = app(ChatController::class);
    $method = new ReflectionMethod($controller, 'enforceDailyLimit');
    $method->setAccessible(true);

    return $method->invoke(
        $controller,
        ['user_id' => null, 'guest_token' => $guest],
        Request::create('/chat/send', 'POST'),
        null
    );
}

it('allows messages up to the daily limit then blocks', function () {
    config(['chat.daily_message_limit' => 2]);
    $guest = 'guest-'.uniqid();

    expect(callLimit($guest))->toBeNull();  // 1st
    expect(callLimit($guest))->toBeNull();  // 2nd
    expect(callLimit($guest))->not->toBeNull(); // 3rd blocked
});

it('counts each user separately', function () {
    config(['chat.daily_message_limit' => 1]);

    expect(callLimit('user-a'))->toBeNull();
    expect(callLimit('user-a'))->not->toBeNull(); // a is blocked
    expect(callLimit('user-b'))->toBeNull();      // b still allowed
});

it('is disabled when the limit is zero', function () {
    config(['chat.daily_message_limit' => 0]);
    $guest = 'guest-'.uniqid();

    foreach (range(1, 5) as $i) {
        expect(callLimit($guest))->toBeNull();
    }
});
