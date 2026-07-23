<?php

use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\SttController;
use App\Http\Controllers\TableExportController;
use App\Http\Controllers\TtsController;
use App\Http\Middleware\ResolveDeviceToken;
use Illuminate\Support\Facades\Route;

/**
 * Mobile API. Versioned so the app can keep working while the web routes,
 * which stream text and rely on session cookies, evolve separately.
 */
Route::prefix('v1')->group(function (): void {
    // Unauthenticated: lets the app check connectivity and its own compatibility.
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'api_version' => 'v1',
    ]))->name('api.health');

    Route::middleware(ResolveDeviceToken::class)->group(function (): void {
        Route::post('/chat', [ChatController::class, 'send'])
            ->middleware('throttle:20,1')
            ->name('api.chat.send');

        Route::get('/conversations', [ChatController::class, 'conversations'])
            ->middleware('throttle:60,1')
            ->name('api.conversations.index');

        Route::get('/conversations/{id}', [ChatController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('api.conversations.show');

        Route::delete('/conversations/{id}', [ChatController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('api.conversations.destroy');

        // Shares the web controller; the app posts the table it rendered.
        Route::post('/export-table', [TableExportController::class, 'xlsx'])
            ->middleware('throttle:20,1')
            ->name('api.export-table');

        // Speech reuses the web controllers: both already return JSON/audio.
        Route::post('/tts', [TtsController::class, 'speak'])
            ->middleware('throttle:60,1')
            ->name('api.tts');

        Route::post('/transcribe', [SttController::class, 'transcribe'])
            ->middleware('throttle:30,1')
            ->name('api.transcribe');
    });
});
