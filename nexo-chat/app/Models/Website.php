<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $url
 */
class Website extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = ['company_id', 'name', 'url'];

    /** @return HasMany<WebsiteDomain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(WebsiteDomain::class);
    }

    /** @return HasMany<Widget, $this> */
    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class);
    }
}
