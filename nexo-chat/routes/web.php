<?php

use App\Http\Controllers\Agent\AvailabilityController;
use App\Http\Controllers\Agent\ConversationActionController;
use App\Http\Controllers\Agent\InboxController;
use App\Http\Controllers\Company\AgentController;
use App\Http\Controllers\Company\DashboardController;
use App\Http\Controllers\Company\WebsiteController;
use App\Http\Controllers\Platform\AuditLogController;
use App\Http\Controllers\Platform\CompanyController;
use App\Http\Controllers\Platform\OverviewController;
use App\Http\Controllers\Widget\WidgetFrameController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect('/inbox') : redirect()->route('login'))->name('home');

// Página do iframe do widget (pública; protegida por domínio + CSP frame-ancestors)
Route::get('widget/frame/{publicKey}', WidgetFrameController::class)
    ->where('publicKey', 'wgt_[a-z0-9]{26}')
    ->name('widget.frame');

// Página local para testar o widget (somente fora de produção)
if (! app()->isProduction()) {
    Route::view('widget/demo', 'widget.demo')->name('widget.demo');
}

// Painel central NEXO — exclusivo da administração do Grupo NEXO
Route::middleware(['auth', 'role:platform_admin'])->prefix('nexo')->name('nexo.')->group(function () {
    Route::get('/', OverviewController::class)->name('overview');
    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::put('companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::post('companies/{company}/suspend', [CompanyController::class, 'suspend'])->name('companies.suspend');
    Route::post('companies/{company}/activate', [CompanyController::class, 'activate'])->name('companies.activate');
    Route::get('audit', AuditLogController::class)->name('audit');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    // Central de atendimento
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox');
    Route::get('inbox/{conversation}', [InboxController::class, 'index'])->name('inbox.show');

    Route::middleware('throttle:agent')->prefix('conversations/{conversation}')->name('conversations.')->group(function () {
        Route::post('messages', [ConversationActionController::class, 'sendMessage'])->name('messages');
        Route::post('assign', [ConversationActionController::class, 'assign'])->name('assign');
        Route::post('close', [ConversationActionController::class, 'close'])->name('close');
        Route::post('reopen', [ConversationActionController::class, 'reopen'])->name('reopen');
        Route::post('typing', [ConversationActionController::class, 'typing'])->name('typing');
    });

    Route::put('me/availability', [AvailabilityController::class, 'update'])->name('me.availability');

    // Painel administrativo da empresa
    Route::middleware('role:company_admin')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('websites', [WebsiteController::class, 'index'])->name('websites.index');
        Route::post('websites', [WebsiteController::class, 'store'])->name('websites.store');
        Route::put('websites/{website}', [WebsiteController::class, 'update'])->name('websites.update');
        Route::delete('websites/{website}', [WebsiteController::class, 'destroy'])->name('websites.destroy');
        Route::put('widgets/{widget}', [WebsiteController::class, 'updateWidget'])->name('widgets.update');

        Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
        Route::post('agents', [AgentController::class, 'store'])->name('agents.store');
        Route::put('agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
        Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
    });
});
