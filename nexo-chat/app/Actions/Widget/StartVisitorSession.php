<?php

namespace App\Actions\Widget;

use App\Models\Visitor;
use App\Models\VisitorSession;
use App\Models\Widget;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * Emite (ou renova) a sessão do visitante. O token em texto claro só é
 * devolvido ao navegador uma vez; o banco guarda apenas o SHA-256.
 */
class StartVisitorSession
{
    public const TTL_DAYS = 30;

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return array{session: VisitorSession, token: string}
     */
    public function handle(Widget $widget, ?string $existingToken, string $originHost, ?string $pageUrl, ?string $ip, ?string $userAgent): array
    {
        return $this->tenant->runAs($widget->company_id, function () use ($widget, $existingToken, $originHost, $pageUrl, $ip, $userAgent): array {
            if ($existingToken) {
                $session = VisitorSession::query()
                    ->where('token_hash', VisitorSession::hashToken($existingToken))
                    ->where('widget_id', $widget->id)
                    ->where('expires_at', '>', now())
                    ->first();

                if ($session) {
                    $session->forceFill([
                        'page_url' => $pageUrl,
                        'origin_host' => $originHost,
                        'last_activity_at' => now(),
                        'expires_at' => now()->addDays(self::TTL_DAYS),
                    ])->save();

                    return ['session' => $session, 'token' => $existingToken];
                }
            }

            $visitor = Visitor::create([
                'company_id' => $widget->company_id,
                'widget_id' => $widget->id,
                'last_seen_at' => now(),
            ]);

            $token = Str::random(64);

            $session = VisitorSession::create([
                'company_id' => $widget->company_id,
                'visitor_id' => $visitor->id,
                'widget_id' => $widget->id,
                'token_hash' => VisitorSession::hashToken($token),
                'origin_host' => $originHost,
                'page_url' => $pageUrl,
                'ip_address' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                'last_activity_at' => now(),
                'expires_at' => now()->addDays(self::TTL_DAYS),
            ]);

            return ['session' => $session, 'token' => $token];
        });
    }
}
