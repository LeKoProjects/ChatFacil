<?php

namespace App\Services\Chatbot;

use App\Actions\Conversations\CloseConversation;
use App\Actions\Conversations\SendMessage;
use App\Enums\BotNodeType;
use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Events\ConversationUpdated;
use App\Models\BotNode;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\FaqEntry;
use App\Models\Message;
use App\Support\Widget\WidgetPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Chatbot por regras (sem IA). Conduz a conversa enquanto ela está com o
 * status "bot": mostra o menu, responde pela FAQ e transfere para a equipe.
 *
 * Cada resposta do bot é uma mensagem comum (sender "bot") com as opções
 * clicáveis em `meta.options`. Só as opções da última mensagem do bot são
 * aceitas, então o visitante não consegue "pular" para partes do menu.
 * Quando um atendente assume a conversa, o status muda e o bot para.
 */
class BotEngine
{
    /** Mensagens digitadas sem resposta na FAQ antes de transferir sozinho. */
    public const MAX_MISSES = 2;

    public function __construct(
        private readonly SendMessage $send,
        private readonly CloseConversation $close,
        private readonly WidgetPresenter $presenter,
        private readonly FaqSearch $faq,
    ) {}

    /** O chatbot da empresa atual está ligado e tem conteúdo (menu ou FAQ)? */
    public function isActive(): bool
    {
        if (! Chatbot::query()->where('is_enabled', true)->exists()) {
            return false;
        }

        return BotNode::query()->exists() || FaqEntry::query()->where('is_active', true)->exists();
    }

    /**
     * Primeira resposta, logo após o visitante enviar o formulário inicial.
     *
     * @return list<Message>
     */
    public function greet(Conversation $conversation, string $firstMessage): array
    {
        $bot = Chatbot::current();
        $matches = $this->faq->search($firstMessage);

        if ($matches->isNotEmpty()) {
            return [$this->reply(
                $conversation,
                $bot->greeting."\n\nEncontrei estas respostas que podem ajudar:",
                [...$this->faqOptions($matches), ...$this->navOptions()],
            )];
        }

        return [$this->reply($conversation, $bot->greeting, [...$this->rootOptions(), $this->handoffOption()])];
    }

    /**
     * Mensagem digitada pelo visitante enquanto está com o bot.
     *
     * @return list<Message>
     */
    public function handleText(Conversation $conversation, string $text): array
    {
        if (! $conversation->isWithBot()) {
            return [];
        }

        $matches = $this->faq->search($text);

        if ($matches->isNotEmpty()) {
            $conversation->forceFill(['bot_misses' => 0])->save();

            return [$this->reply($conversation, 'Encontrei estas respostas:', [...$this->faqOptions($matches), ...$this->navOptions()])];
        }

        $misses = $conversation->bot_misses + 1;
        $conversation->forceFill(['bot_misses' => $misses])->save();

        if ($misses >= self::MAX_MISSES) {
            return $this->handoff($conversation);
        }

        return [$this->reply($conversation, Chatbot::current()->fallback_message, [...$this->rootOptions(), $this->handoffOption()])];
    }

    /**
     * O visitante clicou em uma opção. Grava o clique como mensagem dele
     * (o atendente vê o caminho no histórico) e executa a ação.
     *
     * @return list<Message>
     */
    public function select(Conversation $conversation, string $optionId, ?string $clientMessageId = null): array
    {
        if (! $conversation->isWithBot()) {
            throw ValidationException::withMessages(['option' => 'Esta conversa já está com a nossa equipe.']);
        }

        $lastBotMessage = $conversation->messages()
            ->where('sender_type', MessageSender::Bot->value)
            ->latest('id')
            ->first();

        $option = collect($lastBotMessage->meta['options'] ?? [])->firstWhere('id', $optionId);

        if (! $option) {
            throw ValidationException::withMessages(['option' => 'Esta opção não está mais disponível.']);
        }

        $choice = $this->send->handle($conversation, MessageSender::Visitor, $option['label'], $clientMessageId);

        if (! $choice->wasRecentlyCreated) {
            return [$choice]; // reenvio do mesmo clique: o bot já respondeu
        }

        $conversation->refresh();
        $conversation->forceFill(['bot_misses' => 0])->save();

        [$kind, $ref] = array_pad(explode(':', $optionId, 2), 2, null);

        $replies = match ($kind) {
            'node' => $this->openNode($conversation, (string) $ref),
            'faq' => $this->openFaq($conversation, (int) $ref),
            'resolved' => $this->resolve($conversation),
            'handoff' => $this->handoff($conversation),
            default => [$this->reply($conversation, 'Escolha uma opção:', [...$this->rootOptions(), $this->handoffOption()])],
        };

        return [$choice, ...$replies];
    }

    /** @return list<Message> */
    private function openNode(Conversation $conversation, string $uuid): array
    {
        $node = BotNode::query()->where('uuid', $uuid)->first();

        if (! $node) {
            return [$this->reply($conversation, Chatbot::current()->fallback_message, [...$this->rootOptions(), $this->handoffOption()])];
        }

        $this->addToTrail($conversation, $node->label);

        if ($node->type === BotNodeType::Handoff) {
            return $this->handoff($conversation, $node->handoff_tag ?: $node->label, $node->message);
        }

        $children = $node->type === BotNodeType::Menu ? $node->children()->limit(10)->get() : new Collection;

        if ($children->isNotEmpty()) {
            return [$this->reply(
                $conversation,
                $node->message ?: 'Escolha uma opção:',
                [...$children->map(fn (BotNode $n) => $this->nodeOption($n))->all(), ...$this->navOptions()],
            )];
        }

        return [$this->reply($conversation, $node->message ?: 'Certo.', $this->afterAnswerOptions())];
    }

    /** @return list<Message> */
    private function openFaq(Conversation $conversation, int $id): array
    {
        $entry = FaqEntry::query()->where('is_active', true)->find($id);

        if (! $entry) {
            return [$this->reply($conversation, Chatbot::current()->fallback_message, [...$this->rootOptions(), $this->handoffOption()])];
        }

        $entry->increment('hits');
        $this->addToTrail($conversation, 'Pergunta: '.$entry->question);

        return [$this->reply($conversation, $entry->answer, $this->afterAnswerOptions())];
    }

    /** O visitante disse que a resposta resolveu: agradece e encerra. */
    private function resolve(Conversation $conversation): array
    {
        $thanks = $this->reply($conversation, Chatbot::current()->resolved_message, []);
        $this->close->handle($conversation->refresh());

        return [$thanks];
    }

    /**
     * Passa a conversa para a fila de atendimento humano.
     *
     * @return list<Message>
     */
    private function handoff(Conversation $conversation, ?string $tag = null, ?string $message = null): array
    {
        $bot = Chatbot::current();
        $online = $this->presenter->isOnline($conversation->widget);
        $text = $online ? ($message ?: $bot->handoff_message) : $bot->offline_handoff_message;

        $reply = $this->reply($conversation, $text, []);

        // Só sai do bot se ninguém da equipe assumiu nesse meio-tempo.
        $moved = Conversation::query()
            ->whereKey($conversation->id)
            ->where('status', ConversationStatus::Bot->value)
            ->update(['status' => ConversationStatus::Waiting->value, 'handoff_tag' => $tag, 'updated_at' => now()]);

        if ($moved) {
            $this->send->handle($conversation, MessageSender::System, 'Conversa transferida para a equipe de atendimento.');
            ConversationUpdated::dispatch($conversation->refresh(), 'handoff');
        }

        return [$reply];
    }

    /** @param  list<array{id: string, label: string}>  $options */
    private function reply(Conversation $conversation, string $body, array $options): Message
    {
        return $this->send->handle(
            $conversation,
            MessageSender::Bot,
            $body,
            meta: $options === [] ? null : ['options' => $options],
        );
    }

    private function addToTrail(Conversation $conversation, string $step): void
    {
        $trail = $conversation->bot_trail ?? [];
        $trail[] = Str::limit($step, 120);
        $conversation->forceFill(['bot_trail' => array_slice($trail, -20)])->save();
    }

    /** @return list<array{id: string, label: string}> */
    private function rootOptions(): array
    {
        return BotNode::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (BotNode $n) => $this->nodeOption($n))
            ->all();
    }

    /** @param  Collection<int, FaqEntry>  $entries */
    private function faqOptions(Collection $entries): array
    {
        return $entries->map(fn (FaqEntry $e) => ['id' => 'faq:'.$e->id, 'label' => Str::limit($e->question, 80)])->values()->all();
    }

    /** @return array{id: string, label: string} */
    private function nodeOption(BotNode $node): array
    {
        return ['id' => 'node:'.$node->uuid, 'label' => $node->label];
    }

    /** @return list<array{id: string, label: string}> */
    private function afterAnswerOptions(): array
    {
        return [['id' => 'resolved', 'label' => 'Isso resolveu, obrigado!'], ...$this->navOptions()];
    }

    /** "Voltar ao início" (se houver menu) + "Falar com um atendente". */
    private function navOptions(): array
    {
        $options = BotNode::query()->whereNull('parent_id')->exists()
            ? [['id' => 'menu', 'label' => 'Voltar ao início']]
            : [];

        return [...$options, $this->handoffOption()];
    }

    /** @return array{id: string, label: string} */
    private function handoffOption(): array
    {
        return ['id' => 'handoff', 'label' => 'Falar com um atendente'];
    }
}
