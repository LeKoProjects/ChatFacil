<?php

namespace App\Models;

use App\Enums\AgentAvailability;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuários não usam o escopo global de empresa (o login precisa localizá-los
 * antes de existir contexto). O isolamento é feito por policies e pelas
 * consultas via $company->users() / scopeInCompany.
 *
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property AgentAvailability $availability
 * @property int $max_concurrent_chats
 * @property bool $is_active
 * @property-read Company|null $company
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'email', 'password', 'role',
        'availability', 'max_concurrent_chats', 'is_active', 'last_seen_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'availability' => AgentAvailability::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @param Builder<User> $query */
    public function scopeInCompany(Builder $query, int $companyId): void
    {
        $query->where('company_id', $companyId);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === UserRole::PlatformAdmin;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === UserRole::CompanyAdmin;
    }

    public function belongsToCompany(int $companyId): bool
    {
        return $this->company_id !== null && $this->company_id === $companyId;
    }
}
