<?php

namespace App\Support;

use Throwable;

/**
 * How a failed model call should be handled and described.
 */
class AiFailure
{
    /** Attempts, and how long to wait between them. */
    public const ATTEMPTS = 3;

    public const RETRY_DELAY_MS = 1500;

    /**
     * Whether the provider turned us away for asking too often.
     *
     * These clear within the minute, so they are worth waiting out rather than
     * reporting: the caller sees an apology for something already over.
     */
    public static function isRateLimit(Throwable $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'rate limit')
            || str_contains($message, 'rate_limit')
            || str_contains($message, 'too many requests')
            || str_contains($message, '429');
    }

    /**
     * What to tell the reader. "Try again" is only worth saying when trying
     * again is likely to work.
     */
    public static function message(Throwable $e): string
    {
        return self::isRateLimit($e)
            ? 'I am handling a lot of questions right now. Please try again in a moment.'
            : 'Sorry, I encountered an error processing your request. Please try again.';
    }
}
