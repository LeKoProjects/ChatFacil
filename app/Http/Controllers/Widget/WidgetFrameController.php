<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\Widget;
use App\Support\Tenancy\TenantContext;
use App\Support\Widget\DomainAuthorizer;
use App\Support\Widget\WidgetPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Página carregada dentro do iframe isolado. O CSP frame-ancestors restringe
 * o embed aos domínios autorizados (aplicado pelo navegador), e o host
 * informado pelo loader é validado no servidor.
 */
class WidgetFrameController extends Controller
{
    public function __invoke(
        Request $request,
        string $publicKey,
        TenantContext $tenant,
        DomainAuthorizer $domains,
        WidgetPresenter $presenter,
    ): Response {
        $widget = $tenant->withoutScope(fn () => Widget::query()
            ->with('website', 'company')
            ->where('public_key', $publicKey)
            ->first());

        abort_if(! $widget || ! $widget->is_active || ! $widget->company?->isActive(), 404);

        $tenant->set($widget->company_id);
        $host = (string) $request->query('host', '');

        if (! $domains->isAllowed($widget, $host)) {
            return response()
                ->view('widget.unauthorized', status: 403)
                ->header('Content-Security-Policy', "frame-ancestors *");
        }

        return response()
            ->view('widget.frame', [
                'config' => $presenter->config($widget, $host),
            ])
            ->header('Content-Security-Policy', 'frame-ancestors '.$domains->frameAncestors($widget))
            ->header('Cache-Control', 'no-store');
    }
}
