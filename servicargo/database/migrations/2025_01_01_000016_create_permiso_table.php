<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permiso', function (Blueprint $table) {
            $table->id();
            $table->string('rol', 30); // admin | asesor | cliente
            $table->foreignId('recurso_id')->constrained('recurso')->cascadeOnDelete();
            $table->boolean('ver')->default(false);
            $table->boolean('crear')->default(false);
            $table->boolean('editar')->default(false);
            $table->boolean('eliminar')->default(false);
            $table->unique(['rol', 'recurso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permiso');
    }
};
