<?php

use App\Http\Controllers\ChatController;
use App\Models\AgentConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

/** Ask as a guest carrying this token, as the browser does. */
function askForTitle(string $id, string $token): mixed
{
    $request = Request::create("/chat/{$id}/title", 'GET', [], ['pk_guest_token' => $token]);

    return app(ChatController::class)->title($request, $id);
}

function conversationOwnedBy(string $token, string $title): AgentConversation
{
    return AgentConversation::query()->create([
        'id' => (string) Str::uuid(),
        'guest_token' => $token,
        'title' => $title,
    ]);
}

// The title is written by a job that runs after the reply, so the page that
// created the conversation has no way to learn it without asking.
it('reports the title a conversation has now', function () {
    $token = (string) Str::uuid();
    $conversation = conversationOwnedBy($token, 'Birds That Eat Rice in Laos');

    $response = askForTitle($conversation->id, $token);

    expect($response->getData(true))->toBe(['title' => 'Birds That Eat Rice in Laos']);
});

it('will not name a conversation belonging to someone else', function () {
    $conversation = conversationOwnedBy((string) Str::uuid(), 'Private');

    expect(fn () => askForTitle($conversation->id, (string) Str::uuid()))
        ->toThrow(NotFoundHttpException::class);
});
