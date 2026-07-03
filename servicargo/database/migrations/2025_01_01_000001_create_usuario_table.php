<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->id();
            $table->string('ci', 20)->unique();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('correo', 120)->unique();
            $table->string('contrasena', 120);
            $table->string('rol', 30); // admin | asesor | cliente
            $table->string('telefono', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};
