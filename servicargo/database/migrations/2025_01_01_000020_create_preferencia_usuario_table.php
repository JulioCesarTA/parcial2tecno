<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Requisito 5 — configuración de tema/accesibilidad, permanente y única por usuario.
        Schema::create('preferencia_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->unique()->constrained('usuario')->cascadeOnDelete();
            $table->string('tema', 20)->default('auto');       // auto | dia | noche | ninos | jovenes
            $table->string('fuente', 5)->default('md');        // sm | md | lg
            $table->string('contraste', 10)->default('normal'); // normal | alto
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencia_usuario');
    }
};
