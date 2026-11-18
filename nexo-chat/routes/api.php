<?php

use App\Http\Controllers\Widget\VisitorConversationController;
use App\Http\Controllers\Widget\VisitorSessionController;
use Illuminate\Support\Facades\Route;

/*
| API pública do widget. Sem cookies/sessão: o visitante se autentica pelo
| token de sessão (Authorization: Bearer) emitido em /sessions.
*/
Route::prefix('widget')->name('widget.api.')->group(function () {
    Route::post('{publicKey}/sessions', [VisitorSessionController::class, 'store'])
        ->middleware('throttle:widget-session')
        ->where('publicKey', 'wgt_[a-z0-9]{26}')
        ->name('sessions.store');

    Route::middleware(['auth:visitor', 'visitor.tenant', 'throttle:widget'])->group(function () {
        Route::get('conversation', [VisitorConversationController::class, 'show'])->name('conversation.show');
        Route::post('conversation', [VisitorConversationController::class, 'store'])->name('conversation.store');
        Route::post('conversation/messages', [VisitorConversationController::class, 'sendMessage'])->name('conversation.messages');
        Route::post('conversation/typing', [VisitorConversationController::class, 'typing'])->name('conversation.typing');
        Route::post('conversation/read', [VisitorConversationController::class, 'markRead'])->name('conversation.read');
        Route::post('broadcasting/auth', [VisitorConversationController::class, 'broadcastingAuth'])->name('broadcasting.auth');
    });
});
