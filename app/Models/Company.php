<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property CompanyStatus $status
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'document', 'status', 'suspended_at'];

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
            'suspended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company): void {
            $company->uuid ??= (string) Str::uuid();
            $company->status ??= CompanyStatus::Active;
        });
    }

    public function isActive(): bool
    {
        return $this->status === CompanyStatus::Active;
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /*
     | Relações usadas pela administração NEXO. Website, Widget e Conversation
     | têm escopo global de empresa: consulte-as entre empresas somente dentro
     | de TenantContext::withoutScope().
     */

    /** @return HasMany<Website, $this> */
    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    /** @return HasMany<Widget, $this> */
    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
