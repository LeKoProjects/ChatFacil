<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Pergunta frequente usada pelo chatbot para responder mensagens digitadas.
 *
 * @property int $id
 * @property int $company_id
 * @property string $question
 * @property string $answer
 * @property string|null $keywords
 * @property bool $is_active
 * @property int $hits
 */
class FaqEntry extends Model
{
    use BelongsToCompany;

    public const MAX_ENTRIES = 300;

    protected $fillable = ['company_id', 'question', 'answer', 'keywords', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
