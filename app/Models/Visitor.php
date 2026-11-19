<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Visitor é "autenticável" apenas pelo guard `visitor` (token de sessão do
 * widget), usado nas rotas do widget e na autorização de canais do Reverb.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $widget_id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property-read Widget $widget
 */
class Visitor extends Model implements Authenticatable
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = ['company_id', 'widget_id', 'name', 'email', 'phone', 'consented_at', 'last_seen_at'];

    /** Sessão autenticada da requisição atual (preenchida pelo guard visitor). */
    public ?VisitorSession $currentSession = null;

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Visitor $v) => $v->uuid ??= (string) Str::uuid());
    }

    /** @return BelongsTo<Widget, $this> */
    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function displayName(): string
    {
        return $this->name ?: 'Visitante #'.Str::upper(Str::substr($this->uuid, 0, 4));
    }

    // --- Authenticatable: apenas identificação (visitante não possui senha) ---

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // não aplicável
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
