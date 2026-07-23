<?php

namespace App\Jobs;

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

use function Laravel\Ai\agent;

/**
 * Names a conversation after what it is actually about.
 *
 * The opening message alone makes a poor title — it is often a long question,
 * or just "hello" — so the model writes a short label from the first exchange,
 * as other assistants do.
 *
 * Dispatched after the response so the reply is never held up by it.
 */
class GenerateConversationTitle implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $conversationId) {}

    public function handle(): void
    {
        $conversation = AgentConversation::find($this->conversationId);

        if ($conversation === null) {
            return;
        }

        $messages = AgentConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->limit(2)
            ->get(['role', 'content']);

        $question = trim((string) $messages->firstWhere('role', 'user')?->content);
        $answer = trim((string) $messages->firstWhere('role', 'assistant')?->content);

        if ($question === '') {
            return;
        }

        try {
            $title = $this->write($question, $answer);
        } catch (Throwable $e) {
            // A missing title is not worth failing over; the fallback stands.
            Log::info('Could not generate a conversation title', ['error' => $e->getMessage()]);

            return;
        }

        if ($title !== '') {
            $conversation->update(['title' => $title]);
        }
    }

    private function write(string $question, string $answer): string
    {
        $reply = (string) agent(
            instructions: 'You name chat conversations. Reply with the title only: '
                .'three to six words, no quotes, no trailing punctuation, no "chat" or '
                .'"conversation". Use the language the user wrote in.',
        )->prompt(
            "Title this conversation.\n\nUser: ".Str::limit($question, 500)
                ."\n\nAssistant: ".Str::limit($answer, 500),
            model: config('ai.chat.model') ?: null,
        );

        return $this->tidy($reply);
    }

    /**
     * Models like to wrap a title in quotes or end it with a full stop.
     */
    private function tidy(string $title): string
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));
        $title = trim($title, " \t\n\r\0\x0B\"'“”‘’");
        $title = rtrim($title, '.。');

        return Str::limit($title, 60, '');
    }
}
