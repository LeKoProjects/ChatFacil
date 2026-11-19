<?php

namespace App\Http\Controllers\Company;

use App\Enums\BotNodeType;
use App\Http\Controllers\Controller;
use App\Models\BotNode;
use App\Models\Chatbot;
use App\Models\FaqEntry;
use App\Services\AuditLogger;
use App\Services\Chatbot\FaqSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuração do chatbot por regras: textos, menu em árvore e FAQ.
 * Rotas restritas ao administrador da empresa; BotNode e FaqEntry têm
 * escopo de empresa, então registros de outra empresa resultam em 404.
 */
class ChatbotController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        $bot = Chatbot::current();

        return Inertia::render('chatbot/Index', [
            'settings' => [
                'is_enabled' => $bot->is_enabled,
                'greeting' => $bot->greeting,
                'fallback_message' => $bot->fallback_message,
                'handoff_message' => $bot->handoff_message,
                'offline_handoff_message' => $bot->offline_handoff_message,
                'resolved_message' => $bot->resolved_message,
            ],
            'nodes' => BotNode::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (BotNode $n) => [
                    'id' => $n->id,
                    'parent_id' => $n->parent_id,
                    'type' => $n->type->value,
                    'label' => $n->label,
                    'message' => $n->message,
                    'handoff_tag' => $n->handoff_tag,
                ]),
            'faqs' => FaqEntry::query()
                ->orderBy('question')
                ->get()
                ->map(fn (FaqEntry $f) => [
                    'id' => $f->id,
                    'question' => $f->question,
                    'answer' => $f->answer,
                    'keywords' => $f->keywords,
                    'is_active' => $f->is_active,
                    'hits' => $f->hits,
                ]),
            'types' => collect(BotNodeType::cases())->map(fn (BotNodeType $t) => ['value' => $t->value, 'label' => $t->label()]),
            'limits' => ['depth' => BotNode::MAX_DEPTH, 'nodes' => BotNode::MAX_NODES, 'faqs' => FaqEntry::MAX_ENTRIES],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'greeting' => ['required', 'string', 'max:1000'],
            'fallback_message' => ['required', 'string', 'max:1000'],
            'handoff_message' => ['required', 'string', 'max:1000'],
            'offline_handoff_message' => ['required', 'string', 'max:1000'],
            'resolved_message' => ['required', 'string', 'max:1000'],
        ]);

        $bot = Chatbot::query()->firstOrNew();
        $bot->fill($data)->save();

        $this->audit->log($data['is_enabled'] ? 'chatbot.enabled' : 'chatbot.updated', $bot);

        return back()->with('success', 'Chatbot salvo.');
    }

    public function storeNode(Request $request): RedirectResponse
    {
        if (BotNode::query()->count() >= BotNode::MAX_NODES) {
            throw ValidationException::withMessages(['label' => 'Limite de '.BotNode::MAX_NODES.' opções atingido.']);
        }

        $data = $this->validateNode($request);
        $parent = $this->parentFor($data['parent_id'] ?? null);

        BotNode::create([
            ...$data,
            'parent_id' => $parent?->id,
            'sort_order' => (int) BotNode::query()->where('parent_id', $parent?->id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Opção adicionada.');
    }

    public function updateNode(Request $request, BotNode $botNode): RedirectResponse
    {
        $data = $this->validateNode($request);

        // Um submenu com opções dentro dele não pode virar outro tipo.
        if ($botNode->type === BotNodeType::Menu && $data['type'] !== BotNodeType::Menu->value && $botNode->children()->exists()) {
            throw ValidationException::withMessages(['type' => 'Remova as opções deste submenu antes de mudar o tipo.']);
        }

        unset($data['parent_id']);
        $botNode->update($data);

        return back()->with('success', 'Opção atualizada.');
    }

    public function destroyNode(BotNode $botNode): RedirectResponse
    {
        $botNode->delete(); // as opções filhas saem junto (cascade)

        return back()->with('success', 'Opção removida.');
    }

    /** Sobe ou desce a opção entre as irmãs. */
    public function moveNode(Request $request, BotNode $botNode): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $siblings = BotNode::query()
            ->where('parent_id', $botNode->parent_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $siblings->search(fn (BotNode $n) => $n->id === $botNode->id);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($siblings[$swap])) {
            $ordered = $siblings->all();
            [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
            foreach ($ordered as $position => $node) {
                $node->forceFill(['sort_order' => $position])->save();
            }
        }

        return back();
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        if (FaqEntry::query()->count() >= FaqEntry::MAX_ENTRIES) {
            throw ValidationException::withMessages(['question' => 'Limite de '.FaqEntry::MAX_ENTRIES.' perguntas atingido.']);
        }

        FaqEntry::create($this->validateFaq($request));

        return back()->with('success', 'Pergunta adicionada.');
    }

    public function updateFaq(Request $request, FaqEntry $faqEntry): RedirectResponse
    {
        $faqEntry->update($this->validateFaq($request));

        return back()->with('success', 'Pergunta atualizada.');
    }

    public function destroyFaq(FaqEntry $faqEntry): RedirectResponse
    {
        $faqEntry->delete();

        return back()->with('success', 'Pergunta removida.');
    }

    /** "Testar busca": mostra o que o bot sugeriria para uma frase. */
    public function searchFaq(Request $request, FaqSearch $search): JsonResponse
    {
        $q = $request->validate(['q' => ['required', 'string', 'max:300']])['q'];

        return response()->json([
            'results' => $search->search($q)->map(fn (FaqEntry $f) => ['id' => $f->id, 'question' => $f->question]),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateNode(Request $request): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::enum(BotNodeType::class)],
            'label' => ['required', 'string', 'max:60'],
            'message' => [Rule::requiredIf($request->input('type') === BotNodeType::Answer->value), 'nullable', 'string', 'max:2000'],
            'handoff_tag' => ['nullable', 'string', 'max:60'],
        ], attributes: ['label' => 'texto do botão', 'message' => 'mensagem', 'handoff_tag' => 'setor']);
    }

    private function parentFor(?int $parentId): ?BotNode
    {
        if ($parentId === null) {
            return null;
        }

        $parent = BotNode::query()->find($parentId); // escopo de empresa

        if (! $parent || $parent->type !== BotNodeType::Menu) {
            throw ValidationException::withMessages(['parent_id' => 'Só é possível adicionar opções dentro de um submenu.']);
        }

        if ($parent->depth() >= BotNode::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent_id' => 'O menu pode ter no máximo '.BotNode::MAX_DEPTH.' níveis.']);
        }

        return $parent;
    }

    /** @return array<string, mixed> */
    private function validateFaq(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:200'],
            'answer' => ['required', 'string', 'max:4000'],
            'keywords' => ['nullable', 'string', 'max:300'],
            'is_active' => ['sometimes', 'boolean'],
        ], attributes: ['question' => 'pergunta', 'answer' => 'resposta', 'keywords' => 'palavras-chave']);
    }
}
