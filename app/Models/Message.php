<?php

namespace App\Models;

use App\Enums\MessageSender;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $conversation_id
 * @property MessageSender $sender_type
 * @property int|null $user_id
 * @property string|null $client_message_id
 * @property string $body
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Conversation $conversation
 */
class Message extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'conversation_id', 'sender_type', 'user_id',
        'client_message_id', 'body', 'delivered_at', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'sender_type' => MessageSender::class,
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Message $m) => $m->uuid ??= (string) Str::uuid());
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
