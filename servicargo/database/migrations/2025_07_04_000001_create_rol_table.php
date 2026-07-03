<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de roles — le da integridad referencial a usuario.rol y permiso.rol
 * (antes eran strings sueltos, sin relación real en el modelo de datos).
 * Se identifica por 'clave' (mismo valor que ya se guarda en usuario.rol /
 * permiso.rol), así ningún dato ni comparación existente cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol', function (Blueprint $table) {
            $table->string('clave', 30)->primary(); // admin | asesor | cliente
            $table->string('nombre', 60);
            $table->integer('orden')->default(0);
        });

        DB::table('rol')->insert([
            ['clave' => 'admin', 'nombre' => 'Administrador', 'orden' => 1],
            ['clave' => 'asesor', 'nombre' => 'Asesor', 'orden' => 2],
            ['clave' => 'cliente', 'nombre' => 'Cliente', 'orden' => 3],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rol');
    }
};
