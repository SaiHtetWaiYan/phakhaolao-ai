<?php

use App\Jobs\GenerateConversationTitle;
use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function tidyTitle(string $raw): string
{
    $job = new GenerateConversationTitle('x');
    $method = new ReflectionMethod($job, 'tidy');
    $method->setAccessible(true);

    return $method->invoke($job, $raw);
}

// Models like to wrap a title in quotes or end it with a full stop.
it('cleans up what the model returns', function (string $raw, string $expected) {
    expect(tidyTitle($raw))->toBe($expected);
})->with([
    'plain' => ['Plants Used in Lao Cooking', 'Plants Used in Lao Cooking'],
    'quoted' => ['"Plants Used in Lao Cooking"', 'Plants Used in Lao Cooking'],
    'curly quotes' => ['“Monkey Species Count”', 'Monkey Species Count'],
    'trailing stop' => ['Monkey species count.', 'Monkey species count'],
    'newlines collapsed' => ["Lao\n\ncooking plants", 'Lao cooking plants'],
    'lao kept intact' => ['ຈຳນວນ Champion ທັງໝົດ', 'ຈຳນວນ Champion ທັງໝົດ'],
]);

it('leaves the conversation alone when it has no messages', function () {
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid(),
        'user_id' => null,
        'guest_token' => 'a-device-token-1234',
        'title' => 'Original',
    ]);

    (new GenerateConversationTitle($conversation->id))->handle();

    expect($conversation->fresh()->title)->toBe('Original');
});

it('does nothing when the conversation has gone', function () {
    expect(fn () => (new GenerateConversationTitle((string) Str::uuid()))->handle())
        ->not->toThrow(Exception::class);
});

it('reads the opening exchange', function () {
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid(),
        'user_id' => null,
        'guest_token' => 'a-device-token-1234',
        'title' => 'Original',
    ]);

    foreach ([['user', 'Which plants are used in Lao cooking?'], ['assistant', 'Many dishes use herbs.']] as [$role, $content]) {
        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'role' => $role,
            'agent' => $role,
            'content' => $content,
            'attachments' => [], 'tool_calls' => [], 'tool_results' => [], 'usage' => [], 'meta' => [],
        ]);
    }

    $messages = AgentConversationMessage::where('conversation_id', $conversation->id)
        ->orderBy('created_at')->limit(2)->get();

    expect($messages->firstWhere('role', 'user')->content)
        ->toBe('Which plants are used in Lao cooking?')
        ->and($messages->firstWhere('role', 'assistant')->content)
        ->toBe('Many dishes use herbs.');
});

// trim() takes its character list as bytes, and the curly quotes contribute
// the closing bytes of several common Lao letters. A title ending in one lost
// its last byte and became invalid UTF-8, which Postgres refused to store.
it('keeps a Lao title intact', function (string $title) {
    $tidied = tidyTitle($title);

    expect(mb_check_encoding($tidied, 'UTF-8'))->toBeTrue()
        ->and($tidied)->toBe($title);
})->with([
    'ends in ນ' => ['ຈຳນວນຊະນິດພືດໃນຖານຂໍ້ມູນ'],
    'ends in ເ' => ['ຊະນິດພັນທີ່ພົບໃນເ'],
    'ends in ຜ' => ['ພືດພື້ນເມືອງຜ'],
    'ends in ຝ' => ['ການປູກຝ'],
]);

it('still strips the quotes and full stops a model adds', function () {
    expect(tidyTitle('"Medicinal Plants in Laos."'))->toBe('Medicinal Plants in Laos')
        ->and(tidyTitle('“ຊະນິດພັນພືດ”'))->toBe('ຊະນິດພັນພືດ');
});
