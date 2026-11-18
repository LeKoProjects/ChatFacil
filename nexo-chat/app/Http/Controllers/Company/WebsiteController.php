<?php

namespace App\Http\Controllers\Company;

use App\Actions\Websites\SaveWebsite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\WebsiteRequest;
use App\Http\Requests\Company\WidgetSettingsRequest;
use App\Models\Website;
use App\Models\Widget;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cadastro de sites + configuração do widget + código de instalação.
 */
class WebsiteController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Website::class);

        $websites = Website::query()
            ->with(['domains', 'widgets'])
            ->orderBy('name')
            ->get()
            ->map(fn (Website $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'url' => $w->url,
                'domains' => $w->domains->pluck('host'),
                'widgets' => $w->widgets->map(fn (Widget $wg) => [
                    'id' => $wg->id,
                    'name' => $wg->name,
                    'public_key' => $wg->public_key,
                    'title' => $wg->title,
                    'primary_color' => $wg->primary_color,
                    'position' => $wg->position,
                    'welcome_message' => $wg->welcome_message,
                    'offline_message' => $wg->offline_message,
                    'ask_name' => $wg->ask_name,
                    'ask_email' => $wg->ask_email,
                    'ask_phone' => $wg->ask_phone,
                    'require_email' => $wg->require_email,
                    'sound_enabled' => $wg->sound_enabled,
                    'is_active' => $wg->is_active,
                    'snippet' => $wg->installSnippet(),
                ]),
            ]);

        return Inertia::render('websites/Index', ['websites' => $websites]);
    }

    public function store(WebsiteRequest $request, SaveWebsite $save): RedirectResponse
    {
        $this->authorize('create', Website::class);

        $save->handle(new Website, $request->validated());

        return back()->with('success', 'Site cadastrado e widget criado.');
    }

    public function update(WebsiteRequest $request, Website $website, SaveWebsite $save): RedirectResponse
    {
        $this->authorize('update', $website);

        $save->handle($website, $request->validated());

        return back()->with('success', 'Site atualizado.');
    }

    public function destroy(Website $website, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('delete', $website);

        $website->widgets()->update(['is_active' => false]);
        $website->delete();
        $audit->log('website.deleted', $website);

        return back()->with('success', 'Site removido.');
    }

    public function updateWidget(WidgetSettingsRequest $request, Widget $widget, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $widget->website);

        $widget->update($request->validated());
        $audit->log('widget.updated', $widget, ['fields' => array_keys($request->validated())]);

        return back()->with('success', 'Widget atualizado.');
    }
}
