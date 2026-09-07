<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserLimitController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationShareController;
use App\Http\Controllers\MessageCommentController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\PromptController;
use Illuminate\Support\Facades\Route;

Route::get('/share/{token}', [ConversationShareController::class, 'show']);

Route::get('/', function () {
    return redirect('/dashboard');
});

// Rotas do Breeze (já existem, não mexes)
require __DIR__.'/auth.php';

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return inertia('Chat/Index');
    })->name('dashboard');

    // Modelos
    Route::get('/api/models', [ModelController::class, 'index']);

    // Conversas
    Route::get('/api/conversations', [ConversationController::class, 'index']);
    Route::post('/api/conversations', [ConversationController::class, 'store']);
    Route::get('/api/conversations/search', [ConversationController::class, 'search']);
    Route::get('/api/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::delete('/api/conversations/{conversation}', [ConversationController::class, 'destroy']);
    Route::patch('/api/conversations/{conversation}/pin', [ConversationController::class, 'pin']);
    Route::get('/api/conversations/{conversation}/export', [ConversationController::class, 'export']);

    // Partilha de conversas
    Route::post('/api/conversations/{conversation}/share', [ConversationShareController::class, 'store']);
    Route::delete('/api/conversations/{conversation}/share', [ConversationShareController::class, 'destroy']);

    // Mensagens (streaming)
    Route::post('/api/conversations/{conversation}/messages', [MessageController::class, 'store']);

    // Prompts
    Route::get('/api/prompts', [PromptController::class, 'index']);
    Route::post('/api/prompts', [PromptController::class, 'store']);
    Route::put('/api/prompts/{prompt}', [PromptController::class, 'update']);
    Route::delete('/api/prompts/{prompt}', [PromptController::class, 'destroy']);

    // Comentários de mensagens
    Route::get('/api/messages/{message}/comments', [MessageCommentController::class, 'index']);
    Route::post('/api/messages/{message}/comments', [MessageCommentController::class, 'store']);
    Route::put('/api/messages/{message}/comments/{comment}', [MessageCommentController::class, 'update']);
    Route::delete('/api/messages/comments/{comment}', [MessageCommentController::class, 'destroy']);

    // Admin
    Route::get('/admin', function () {
        if (!auth()->user()->is_admin) abort(403);
        return inertia('Admin/AuditLogs');
    });

    Route::prefix('admin')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/export', [AuditLogController::class, 'export']);
    });

    Route::get('/admin/usage-limits', function () {
        if (!auth()->user()->is_admin) abort(403);
        return inertia('Admin/UsageLimits');
    });

    Route::get('/admin/users', [UserLimitController::class, 'index']);
    Route::put('/admin/users/{user}/limits', [UserLimitController::class, 'updateLimits']);
    Route::post('/admin/users/{user}/reset-usage', [UserLimitController::class, 'resetUsage']);
});