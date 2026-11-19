<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chatbot por regras (sem IA): menu em árvore + busca em perguntas frequentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Busca da FAQ sem diferenciar acentos ("orcamento" encontra "orçamento").
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        Schema::create('chatbots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->text('greeting');
            $table->text('fallback_message');
            $table->text('handoff_message');
            $table->text('offline_handoff_message');
            $table->text('resolved_message');
            $table->timestamps();
        });

        Schema::create('bot_nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('bot_nodes')->cascadeOnDelete();
            // menu | answer | handoff (App\Enums\BotNodeType)
            $table->string('type', 12);
            $table->string('label', 60);
            $table->text('message')->nullable();
            $table->string('handoff_tag', 60)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'parent_id', 'sort_order']);
        });

        Schema::create('faq_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('question', 200);
            $table->text('answer');
            $table->string('keywords', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            // Caminho percorrido no menu do chatbot (mostrado ao atendente).
            $table->jsonb('bot_trail')->nullable();
            $table->string('handoff_tag', 60)->nullable();
            $table->unsignedSmallInteger('bot_misses')->default(0);
        });

        Schema::table('messages', function (Blueprint $table) {
            // Opções clicáveis das mensagens do chatbot.
            $table->jsonb('meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn('meta'));
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn(['bot_trail', 'handoff_tag', 'bot_misses']));
        Schema::dropIfExists('faq_entries');
        Schema::dropIfExists('bot_nodes');
        Schema::dropIfExists('chatbots');
    }
};
