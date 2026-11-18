<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'deleted_at']);
        });

        // Domínios autorizados a embutir o widget (validação server-side + CSP frame-ancestors).
        Schema::create('website_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('host');
            $table->timestamps();

            $table->unique(['website_id', 'host']);
            $table->index(['company_id', 'host']);
        });

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            // Identificador público usado no script de instalação. NÃO é credencial.
            $table->string('public_key', 40)->unique();
            $table->string('name');
            $table->string('primary_color', 9)->default('#4F46E5');
            $table->string('position', 12)->default('right');
            $table->string('title')->default('Fale conosco');
            $table->text('welcome_message')->nullable();
            $table->text('offline_message')->nullable();
            $table->boolean('ask_name')->default(true);
            $table->boolean('ask_email')->default(true);
            $table->boolean('ask_phone')->default(false);
            $table->boolean('require_email')->default(false);
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'website_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widgets');
        Schema::dropIfExists('website_domains');
        Schema::dropIfExists('websites');
    }
};
