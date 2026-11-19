<?php

namespace App\Http\Controllers\Widget;

use App\Actions\Widget\StartVisitorSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Widget\StartSessionRequest;
use App\Models\Conversation;
use App\Models\Widget;
use App\Support\Tenancy\TenantContext;
use App\Support\Widget\DomainAuthorizer;
use App\Support\Widget\WidgetConversationPayload;
use Illuminate\Http\JsonResponse;

class VisitorSessionController extends Controller
{
    public function store(
        StartSessionRequest $request,
        string $publicKey,
        TenantContext $tenant,
        DomainAuthorizer $domains,
        StartVisitorSession $startSession,
        WidgetConversationPayload $payload,
    ): JsonResponse {
        $widget = $tenant->withoutScope(fn () => Widget::query()
            ->with('company')
            ->where('public_key', $publicKey)
            ->first());

        abort_if(! $widget || ! $widget->is_active || ! $widget->company?->isActive(), 404);

        $tenant->set($widget->company_id);

        $host = (string) $request->validated('host');

        if (! $domains->isAllowed($widget, $host)) {
            return response()->json(['message' => 'Domínio não autorizado para este widget.'], 403);
        }

        ['session' => $session, 'token' => $token] = $startSession->handle(
            $widget,
            $request->validated('token'),
            $host,
            $request->validated('page_url'),
            $request->ip(),
            $request->userAgent(),
        );

        $conversation = Conversation::query()
            ->where('visitor_id', $session->visitor_id)
            ->orderByRaw("CASE WHEN status <> 'closed' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();

        return response()->json([
            'token' => $token,
            'visitor' => [
                'id' => $session->visitor->uuid,
                'name' => $session->visitor->name,
                'email' => $session->visitor->email,
            ],
            ...$payload->make($conversation),
        ]);
    }
}
