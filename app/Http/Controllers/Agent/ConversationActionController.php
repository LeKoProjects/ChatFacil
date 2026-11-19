<?php

namespace App\Http\Controllers\Agent;

use App\Actions\Conversations\AssignConversation;
use App\Actions\Conversations\CloseConversation;
use App\Actions\Conversations\ReopenConversation;
use App\Actions\Conversations\SendMessage;
use App\Enums\MessageSender;
use App\Events\TypingChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ações da central (JSON — chamadas via fetch para não recarregar a página).
 */
class ConversationActionController extends Controller
{
    public function sendMessage(SendMessageRequest $request, Conversation $conversation, SendMessage $send): JsonResponse
    {
        $this->authorize('reply', $conversation);

        $message = $send->handle(
            $conversation,
            MessageSender::Agent,
            $request->validated('body'),
            $request->validated('client_message_id'),
            $request->user(),
        );

        $message->load(['conversation.visitor', 'user']);

        return response()->json(['message' => (new MessageResource($message))->resolve()], 201);
    }

    public function assign(Request $request, Conversation $conversation, AssignConversation $assign): JsonResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer']]);

        /** @var User $actor */
        $actor = $request->user();

        $assignee = isset($data['user_id'])
            ? User::query()->inCompany($actor->company_id)->where('is_active', true)->findOrFail($data['user_id'])
            : $actor;

        $this->authorize('assign', [$conversation, $assignee]);

        $assign->handle($conversation, $assignee, $actor);

        return $this->conversationResponse($conversation);
    }

    public function close(Request $request, Conversation $conversation, CloseConversation $close): JsonResponse
    {
        $this->authorize('close', $conversation);

        $close->handle($conversation, $request->user());

        return $this->conversationResponse($conversation);
    }

    public function reopen(Request $request, Conversation $conversation, ReopenConversation $reopen): JsonResponse
    {
        $this->authorize('reopen', $conversation);

        $reopen->handle($conversation, $request->user());

        return $this->conversationResponse($conversation);
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $request->validate(['typing' => ['required', 'boolean']]);

        broadcast(new TypingChanged($conversation, 'agent', $request->user()->name, $request->boolean('typing')))->toOthers();

        return response()->json(['ok' => true]);
    }

    private function conversationResponse(Conversation $conversation): JsonResponse
    {
        $conversation->refresh()->load(['visitor', 'widget', 'assignee', 'latestMessage']);

        return response()->json(['conversation' => (new ConversationResource($conversation))->resolve()]);
    }
}
