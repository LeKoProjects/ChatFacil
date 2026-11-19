<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identidade visual NEXO Chat: cor padrão do widget passa a ser o azul NEXO.
 * Widgets que ainda usam o padrão anterior (não personalizados) são migrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widgets', function (Blueprint $table) {
            $table->string('primary_color', 9)->default('#1677F0')->change();
        });

        DB::table('widgets')->whereRaw('upper(primary_color) = ?', ['#4F46E5'])->update(['primary_color' => '#1677F0']);
    }

    public function down(): void
    {
        Schema::table('widgets', function (Blueprint $table) {
            $table->string('primary_color', 9)->default('#4F46E5')->change();
        });
    }
};
