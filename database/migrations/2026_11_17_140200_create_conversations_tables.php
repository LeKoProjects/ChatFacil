<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('widget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            // waiting | open | pending | closed (App\Enums\ConversationStatus)
            $table->string('status', 20)->default('waiting');
            $table->unsignedTinyInteger('priority')->default(0);
            $table->text('origin_url')->nullable();
            $table->unsignedInteger('visitor_unread_count')->default(0);
            $table->unsignedInteger('agent_unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'last_message_at']);
            $table->index(['company_id', 'assigned_user_id', 'status']);
            $table->index(['visitor_id', 'status']);
        });

        // No máximo uma conversa ativa por visitante (protege contra corrida de requisições).
        DB::statement("CREATE UNIQUE INDEX conversations_one_active_per_visitor ON conversations (visitor_id) WHERE status <> 'closed' AND deleted_at IS NULL");

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // visitor | agent | system
            $table->string('sender_type', 12);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Gerado pelo cliente; garante idempotência no reenvio após falha de rede.
            $table->uuid('client_message_id')->nullable();
            $table->text('body');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'client_message_id']);
            $table->index(['conversation_id', 'id']);
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('conversation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'assigned_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->nullableMorphs('auditable');
            $table->jsonb('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('conversation_assignments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
