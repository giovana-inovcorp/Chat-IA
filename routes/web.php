<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\MemoryController;
use App\Http\Controllers\KnowledgeController;
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

    // Perfil (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Modelos
    Route::get('/api/models', [ModelController::class, 'index']);

    // Conversas
    Route::get('/api/conversations', [ConversationController::class, 'index']);
    Route::post('/api/conversations', [ConversationController::class, 'store']);
    Route::get('/api/conversations/search', [ConversationController::class, 'search']);
    Route::get('/api/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::delete('/api/conversations/{conversation}', [ConversationController::class, 'destroy']);
    Route::patch('/api/conversations/{conversation}/pin', [ConversationController::class, 'pin']);
    Route::patch('/api/conversations/{conversation}/assistant', [ConversationController::class, 'switchAssistant']);
    Route::get('/api/conversations/{conversation}/export', [ConversationController::class, 'export']);

    // Partilha de conversas
    Route::post('/api/conversations/{conversation}/share', [ConversationShareController::class, 'store']);
    Route::delete('/api/conversations/{conversation}/share', [ConversationShareController::class, 'destroy']);

    // Mensagens (streaming)
    Route::post('/api/conversations/{conversation}/messages', [MessageController::class, 'store']);

    // Confirmação de ferramentas
    Route::post('/api/tool-executions/{toolExecution}/confirm', [MessageController::class, 'confirmTool']);
    Route::post('/api/tool-executions/{toolExecution}/reject', [MessageController::class, 'rejectTool']);

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

    // Base de Conhecimento
    Route::get('/knowledge', function () {
        return inertia('Knowledge/Index');
    })->name('knowledge');

    Route::get('/api/knowledge', [KnowledgeController::class, 'index']);
    Route::post('/api/knowledge', [KnowledgeController::class, 'store']);
    Route::delete('/api/knowledge/{collection}', [KnowledgeController::class, 'destroy']);
    Route::get('/api/knowledge/{collection}/documents', [KnowledgeController::class, 'documents']);
    Route::post('/api/knowledge/{collection}/documents', [KnowledgeController::class, 'uploadDocument']);
    Route::delete('/api/knowledge/documents/{document}', [KnowledgeController::class, 'deleteDocument']);
    Route::post('/api/knowledge/documents/{document}/reprocess', [KnowledgeController::class, 'reprocessDocument']);
    Route::post('/api/knowledge/{collection}/search', [KnowledgeController::class, 'search']);
    Route::get('/api/knowledge/documents/{document}/download', [KnowledgeController::class, 'downloadDocument']);

    // Assistentes
    Route::get('/assistants', function () {
        return inertia('Assistants/Index');
    })->name('assistants');

    Route::get('/api/assistants', [AssistantController::class, 'index']);
    Route::get('/api/tools', [AssistantController::class, 'tools']);
    Route::post('/api/assistants', [AssistantController::class, 'store']);
    Route::put('/api/assistants/{assistant}', [AssistantController::class, 'update']);
    Route::post('/api/assistants/{assistant}/duplicate', [AssistantController::class, 'duplicate']);
    Route::patch('/api/assistants/{assistant}/archive', [AssistantController::class, 'archive']);
    Route::patch('/api/assistants/{assistant}/favorite', [AssistantController::class, 'favorite']);
    Route::delete('/api/assistants/{assistant}', [AssistantController::class, 'destroy']);

    // Memórias
    Route::get('/memories', function () {
        return inertia('Memories/Index');
    })->name('memories');

    Route::get('/api/memories', [MemoryController::class, 'index']);
    Route::post('/api/memories', [MemoryController::class, 'store']);
    Route::post('/api/memories/suggest', [MemoryController::class, 'suggest']);
    Route::put('/api/memories/{memory}', [MemoryController::class, 'update']);
    Route::patch('/api/memories/{memory}/toggle', [MemoryController::class, 'toggle']);
    Route::delete('/api/memories/{memory}', [MemoryController::class, 'destroy']);
    Route::patch('/api/memory-settings', [MemoryController::class, 'setGlobal']);
});