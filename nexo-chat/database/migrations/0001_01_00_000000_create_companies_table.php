<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas (tenants). Toda entidade pertencente a um cliente referencia companies.id
 * via company_id — base da estratégia de multi-tenancy (ver docs/ARQUITETURA.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('document', 32)->nullable()->comment('CNPJ/CPF');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
