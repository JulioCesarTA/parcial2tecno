<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * usuario.rol y permiso.rol pasan a referenciar rol.clave.
 * restrictOnDelete: no se puede borrar un rol mientras haya usuarios/permisos
 * usándolo. cascadeOnUpdate: si algún día se renombra una clave (como pasó con
 * vendedor->asesor), las filas hijas se actualizan solas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->foreign('rol')->references('clave')->on('rol')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::table('permiso', function (Blueprint $table) {
            $table->foreign('rol')->references('clave')->on('rol')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropForeign(['rol']);
        });

        Schema::table('permiso', function (Blueprint $table) {
            $table->dropForeign(['rol']);
        });
    }
};
