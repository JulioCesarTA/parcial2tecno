<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuario')->nullOnDelete();
            $table->string('accion', 50); // login_ok|login_fallido|logout|acceso_recurso|accion
            $table->string('recurso', 80)->nullable();
            $table->text('detalle')->nullable();
            $table->string('ip', 60)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
