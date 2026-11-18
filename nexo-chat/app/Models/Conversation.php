<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $widget_id
 * @property int $visitor_id
 * @property int|null $assigned_user_id
 * @property ConversationStatus $status
 * @property int $priority
 * @property string|null $origin_url
 * @property int $visitor_unread_count
 * @property int $agent_unread_count
 * @property Carbon|null $last_message_at
 * @property Carbon|null $first_response_at
 * @property Carbon|null $assigned_at
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property-read Visitor $visitor
 * @property-read Widget $widget
 * @property-read User|null $assignee
 * @property-read Message|null $latestMessage
 */
class Conversation extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'widget_id', 'visitor_id', 'assigned_user_id', 'status', 'priority',
        'origin_url', 'last_message_at', 'first_response_at', 'assigned_at', 'closed_at',
        'closed_by_user_id', 'visitor_unread_count', 'agent_unread_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'first_response_at' => 'datetime',
            'assigned_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Conversation $c): void {
            $c->uuid ??= (string) Str::uuid();
            $c->status ??= ConversationStatus::Waiting;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @param Builder<Conversation> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', ConversationStatus::Closed->value);
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

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasOne<Message, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /** @return HasMany<ConversationAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(ConversationAssignment::class);
    }

    public function isClosed(): bool
    {
        return $this->status === ConversationStatus::Closed;
    }

    /** Canal privado (Reverb) compartilhado por visitante e atendentes da empresa. */
    public function channelName(): string
    {
        return 'conversation.'.$this->uuid;
    }
}
