<?php

use App\Http\Controllers\ChatController;
use App\Http\Middleware\RestrictToLocal;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index'])->name('home');

Route::get('/chat/{id?}', [ChatController::class, 'index'])->name('chat');
Route::post('/chat/send', [ChatController::class, 'send'])->middleware('throttle:20,1')->name('chat.send');
Route::post('/chat/save-response', [ChatController::class, 'saveResponse'])->middleware('throttle:30,1')->name('chat.save-response');
Route::post('/chat/clear', [ChatController::class, 'clear'])->middleware('throttle:10,1')->name('chat.clear');
Route::delete('/chat/{id}', [ChatController::class, 'destroy'])->middleware('throttle:10,1')->name('chat.destroy');
Route::get('/species/export-generated/{token}', [ChatController::class, 'downloadGeneratedExport'])->middleware('throttle:10,1')->name('species.export-generated');
Route::get('/settings/rag', [ChatController::class, 'ragSettings'])->middleware(RestrictToLocal::class)->name('settings.rag');
Route::post('/settings/rag', [ChatController::class, 'updateRagSettings'])->middleware(RestrictToLocal::class)->name('settings.rag.update');
