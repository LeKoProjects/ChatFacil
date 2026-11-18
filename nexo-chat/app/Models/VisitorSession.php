<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $visitor_id
 * @property int $widget_id
 * @property string $token_hash
 * @property string|null $origin_host
 * @property Carbon $expires_at
 * @property-read Visitor $visitor
 * @property-read Widget $widget
 */
class VisitorSession extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'visitor_id', 'widget_id', 'token_hash', 'origin_host',
        'page_url', 'ip_address', 'user_agent', 'last_activity_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (VisitorSession $s) => $s->uuid ??= (string) Str::uuid());
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** @return BelongsTo<Visitor, $this> */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /** @return BelongsTo<Widget, $this> */
    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }
}
