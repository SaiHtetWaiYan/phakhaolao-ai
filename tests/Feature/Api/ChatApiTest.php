<?php

use App\Http\Requests\Api\SendChatMessageRequest;
use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const DEVICE_TOKEN = 'a4f1c2d3-e5b6-4789-9abc-def012345678';

function withDevice(array $headers = []): array
{
    return array_merge(['X-Device-Token' => DEVICE_TOKEN], $headers);
}

function makeConversation(string $deviceToken = DEVICE_TOKEN): AgentConversation
{
    return AgentConversation::create([
        'id' => (string) Str::uuid(),
        'user_id' => null,
        'guest_token' => $deviceToken,
        'title' => 'Test conversation',
    ]);
}

it('reports health without a device token', function () {
    $this->getJson('/api/v1/health')
        ->assertSuccessful()
        ->assertJson(['status' => 'ok', 'api_version' => 'v1']);
});

it('rejects requests without a device token', function () {
    $this->postJson('/api/v1/chat', ['message' => 'hello'])
        ->assertUnauthorized();
});

it('rejects a malformed device token', function () {
    $this->postJson('/api/v1/chat', ['message' => 'hello'], ['X-Device-Token' => 'short'])
        ->assertUnauthorized();
});

it('validates the message', function (array $payload, string $field) {
    $this->postJson('/api/v1/chat', $payload, withDevice())
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing message' => [[], 'message'],
    'empty message' => [['message' => ''], 'message'],
    'too long' => [['message' => str_repeat('a', 5001)], 'message'],
    'bad conversation id' => [['message' => 'hi', 'conversation_id' => 'not-a-uuid'], 'conversation_id'],
]);

// The reply follows the question's own language now, so there is no
// preference to send. An older build still sending one is ignored rather than
// rejected, because the field is no longer validated at all.
it('no longer takes a reply-language preference', function () {
    expect((new SendChatMessageRequest)->rules())->not->toHaveKey('response_language');
});

it('404s when sending to a conversation owned by another device', function () {
    $other = makeConversation('someone-elses-device-token-1234');

    $this->postJson('/api/v1/chat', [
        'message' => 'hello',
        'conversation_id' => $other->id,
    ], withDevice())->assertNotFound();
});

it('lists only conversations belonging to the device', function () {
    $mine = makeConversation();
    makeConversation('someone-elses-device-token-1234');

    $response = $this->getJson('/api/v1/conversations', withDevice())->assertSuccessful();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($mine->id);
});

it('returns a conversation with its messages', function () {
    $conversation = makeConversation();

    AgentConversationMessage::create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'user_id' => null,
        'role' => 'user',
        'agent' => 'user',
        'content' => 'What is a champion?',
        'attachments' => [], 'tool_calls' => [], 'tool_results' => [], 'usage' => [], 'meta' => [],
    ]);

    $this->getJson("/api/v1/conversations/{$conversation->id}", withDevice())
        ->assertSuccessful()
        ->assertJsonPath('id', $conversation->id)
        ->assertJsonPath('messages.0.content', 'What is a champion?');
});

it('hides another device conversation', function () {
    $other = makeConversation('someone-elses-device-token-1234');

    $this->getJson("/api/v1/conversations/{$other->id}", withDevice())->assertNotFound();
});

it('deletes a conversation and its messages', function () {
    $conversation = makeConversation();

    AgentConversationMessage::create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'user_id' => null,
        'role' => 'user',
        'agent' => 'user',
        'content' => 'hi',
        'attachments' => [], 'tool_calls' => [], 'tool_results' => [], 'usage' => [], 'meta' => [],
    ]);

    $this->deleteJson("/api/v1/conversations/{$conversation->id}", [], withDevice())
        ->assertSuccessful();

    expect(AgentConversation::find($conversation->id))->toBeNull()
        ->and(AgentConversationMessage::where('conversation_id', $conversation->id)->count())->toBe(0);
});

it('will not delete another device conversation', function () {
    $other = makeConversation('someone-elses-device-token-1234');

    $this->deleteJson("/api/v1/conversations/{$other->id}", [], withDevice())->assertNotFound();

    expect(AgentConversation::find($other->id))->not->toBeNull();
});

it('blocks sending once the daily limit is reached', function () {
    config(['chat.daily_message_limit' => 1]);

    // Burn the single allowance without invoking the model.
    $key = 'chat-usage:'.md5(DEVICE_TOKEN).':'.now(config('chat.limit_timezone'))->toDateString();
    cache()->put($key, 1, now()->addDay());

    $this->postJson('/api/v1/chat', ['message' => 'hello'], withDevice())
        ->assertStatus(429)
        ->assertJsonPath('limit', 1);
});

// A photo on its own is a valid request: it asks the assistant to identify the
// species. Checked against the rules directly so the test does not call the model.
it('accepts a photo with no caption', function () {
    $rules = (new \App\Http\Requests\Api\SendChatMessageRequest)->rules();

    $validator = validator(
        ['image' => UploadedFile::fake()->image('plant.jpg')],
        $rules
    );

    expect($validator->fails())->toBeFalse();
});

it('rejects a request with neither a message nor a photo', function () {
    $this->postJson('/api/v1/chat', [], withDevice())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

it('rejects a file that is not an image', function () {
    Storage::fake('public');

    $this->postJson('/api/v1/chat', [
        'message' => 'what is this?',
        'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
    ], withDevice())->assertUnprocessable()->assertJsonValidationErrors('image');
});

// The mobile client uploads with no declared type, which arrives as
// application/octet-stream; sending that to the vision model is rejected.
it('detects the image type from its contents, not the client claim', function () {
    $path = tempnam(sys_get_temp_dir(), 'img').'.png';
    copy(public_path('favicon-192.png'), $path);

    $upload = new UploadedFile(
        $path,
        'plant.png',
        'application/octet-stream', // what Flutter sends
        null,
        true
    );

    expect($upload->getClientMimeType())->toBe('application/octet-stream')
        ->and($upload->getMimeType())->toStartWith('image/');

    unlink($path);
});

// Titles were cut to 18 characters with an ellipsis, so a history row read
// "Which plants are u...". They now carry enough text for the client to
// truncate at its own width.
it('builds a readable conversation title', function (string $message, string $expected) {
    $method = new ReflectionMethod(\App\Http\Controllers\Api\V1\ChatController::class, 'conversationTitle');
    $method->setAccessible(true);

    expect($method->invoke(app(\App\Http\Controllers\Api\V1\ChatController::class), $message))->toBe($expected);
})->with([
    'short question kept whole' => [
        'Which plants are used in Lao cooking?',
        'Which plants are used in Lao cooking?',
    ],
    'newlines collapsed' => ["How many\n\nchampions?", 'How many champions?'],
    'photo with no caption' => ['', 'Photo'],
    'very long message trimmed without an ellipsis' => [
        str_repeat('a', 80),
        str_repeat('a', 60),
    ],
]);
