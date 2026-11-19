<?php

namespace App\Support\Widget;

use App\Enums\AgentAvailability;
use App\Models\User;
use App\Models\Widget;

/**
 * Configuração pública entregue ao widget (sem dados sensíveis).
 */
class WidgetPresenter
{
    public function isOnline(Widget $widget): bool
    {
        return User::query()
            ->inCompany($widget->company_id)
            ->where('is_active', true)
            ->where('availability', AgentAvailability::Online->value)
            ->exists();
    }

    /** @return array<string, mixed> */
    public function config(Widget $widget, string $host): array
    {
        return [
            'key' => $widget->public_key,
            'host' => $host,
            'title' => $widget->title,
            'company' => $widget->company?->name,
            'primary_color' => $widget->primary_color,
            'position' => $widget->position,
            'welcome_message' => $widget->welcome_message,
            'offline_message' => $widget->offline_message,
            'form' => [
                'ask_name' => $widget->ask_name,
                'ask_email' => $widget->ask_email,
                'ask_phone' => $widget->ask_phone,
                'require_email' => $widget->require_email,
            ],
            'sound_enabled' => $widget->sound_enabled,
            'online' => $this->isOnline($widget),
            'api_base' => url('/api/widget'),
            'reverb' => [
                'key' => config('broadcasting.connections.reverb.key'),
                ...config('nexo.reverb_client'),
            ],
        ];
    }
}
