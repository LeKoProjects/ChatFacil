<?php

namespace App\Models;

use App\Enums\BotNodeType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Uma opção do menu do chatbot. Opções sem pai formam o menu principal.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int|null $parent_id
 * @property BotNodeType $type
 * @property string $label
 * @property string|null $message
 * @property string|null $handoff_tag
 * @property int $sort_order
 * @property-read BotNode|null $parent
 */
class BotNode extends Model
{
    use BelongsToCompany;

    public const MAX_DEPTH = 4;

    public const MAX_NODES = 100;

    protected $fillable = ['company_id', 'parent_id', 'type', 'label', 'message', 'handoff_tag', 'sort_order'];

    protected function casts(): array
    {
        return ['type' => BotNodeType::class];
    }

    protected static function booted(): void
    {
        static::creating(fn (BotNode $n) => $n->uuid ??= (string) Str::uuid());
    }

    /** @return BelongsTo<BotNode, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<BotNode, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /** Profundidade no menu: 1 = menu principal. */
    public function depth(): int
    {
        $depth = 1;
        $node = $this;
        while ($node->parent_id !== null && $depth <= self::MAX_DEPTH + 1) {
            $node = $node->parent;
            $depth++;
        }

        return $depth;
    }
}
