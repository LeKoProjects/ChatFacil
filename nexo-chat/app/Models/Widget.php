<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property int $website_id
 * @property string $public_key
 * @property string $name
 * @property string $primary_color
 * @property string $position
 * @property string $title
 * @property string|null $welcome_message
 * @property string|null $offline_message
 * @property bool $ask_name
 * @property bool $ask_email
 * @property bool $ask_phone
 * @property bool $require_email
 * @property bool $sound_enabled
 * @property bool $is_active
 * @property-read Website $website
 */
class Widget extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'website_id', 'name', 'primary_color', 'position', 'title',
        'welcome_message', 'offline_message', 'ask_name', 'ask_email', 'ask_phone',
        'require_email', 'sound_enabled', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ask_name' => 'boolean',
            'ask_email' => 'boolean',
            'ask_phone' => 'boolean',
            'require_email' => 'boolean',
            'sound_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Widget $widget): void {
            $widget->public_key ??= 'wgt_'.Str::lower((string) Str::ulid());
        });
    }

    /** @return BelongsTo<Website, $this> */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function installSnippet(): string
    {
        $src = rtrim((string) config('app.url'), '/').'/widget.js';

        return '<script src="'.$src.'" data-widget-id="'.$this->public_key.'" async></script>';
    }
}
