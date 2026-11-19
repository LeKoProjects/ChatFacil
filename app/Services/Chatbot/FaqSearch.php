<?php

namespace App\Services\Chatbot;

use App\Models\FaqEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Busca nas perguntas frequentes com a busca textual do PostgreSQL
 * (dicionário português: "boletos" encontra "boleto"), sem acentos e por
 * prefixo ("orcam" encontra "orçamento"). Pergunta e palavras-chave pesam
 * mais que a resposta.
 */
class FaqSearch
{
    /** Palavras comuns que não ajudam a encontrar a resposta. */
    private const STOPWORDS = [
        'que', 'qual', 'quais', 'como', 'para', 'por', 'com', 'sem', 'uma', 'uns', 'umas', 'dos', 'das',
        'nos', 'nas', 'meu', 'minha', 'meus', 'minhas', 'seu', 'sua', 'voce', 'voces', 'vcs', 'ola', 'oi',
        'bom', 'boa', 'dia', 'tarde', 'noite', 'preciso', 'quero', 'gostaria', 'saber', 'sobre', 'tem',
        'tenho', 'esta', 'estou', 'isso', 'esse', 'essa', 'ajuda', 'favor', 'pode', 'posso', 'onde', 'quando',
        'nao', 'sim', 'mais', 'muito', 'obrigado', 'obrigada', 'ate', 'aqui', 'entao', 'tudo', 'fazer',
    ];

    /**
     * @return Collection<int, FaqEntry> até $limit perguntas, da mais relevante para a menos.
     */
    public function search(string $text, int $limit = 3): Collection
    {
        $query = $this->toTsQuery($text);

        if ($query === null) {
            return new Collection;
        }

        $document = "setweight(to_tsvector('portuguese', unaccent(question || ' ' || coalesce(keywords, ''))), 'A')"
            ." || setweight(to_tsvector('portuguese', unaccent(answer)), 'B')";

        return FaqEntry::query()
            ->where('is_active', true)
            ->whereRaw("{$document} @@ to_tsquery('portuguese', ?)", [$query])
            ->orderByRaw("ts_rank({$document}, to_tsquery('portuguese', ?)) DESC", [$query])
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Converte o texto livre em uma consulta "termo1:* | termo2:*". Só letras
     * e números chegam ao banco (e ainda assim como parâmetro).
     */
    public function toTsQuery(string $text): ?string
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = collect($words)
            ->filter(fn (string $w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOPWORDS, true))
            ->unique()
            ->take(12)
            ->map(fn (string $w) => $w.':*');

        return $terms->isEmpty() ? null : $terms->implode(' | ');
    }
}
