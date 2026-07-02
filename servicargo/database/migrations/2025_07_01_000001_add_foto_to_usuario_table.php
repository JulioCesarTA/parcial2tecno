<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            // Ruta relativa de la foto dentro de public/ (ej. fotos_perfil/usuario_1_....jpg).
            // Se sirve estáticamente: no depende del symlink storage:link (que no existe en el hosting FTP).
            $table->string('foto', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
