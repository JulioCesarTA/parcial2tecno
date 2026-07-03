<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El rol "vendedor" pasa a llamarse "asesor" (terminología del negocio).
 * Actualiza filas ya existentes; el seeder ya crea las nuevas con 'asesor'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('usuario')->where('rol', 'vendedor')->update(['rol' => 'asesor']);
        DB::table('permiso')->where('rol', 'vendedor')->update(['rol' => 'asesor']);

        // El usuario demo también cambia de correo/apellido para reflejar el nuevo rol.
        DB::table('usuario')->where('correo', 'vendedor@servicargo.bo')->update([
            'correo' => 'asesor@servicargo.bo',
            'apellido' => 'Asesor',
        ]);
    }

    public function down(): void
    {
        DB::table('usuario')->where('rol', 'asesor')->update(['rol' => 'vendedor']);
        DB::table('permiso')->where('rol', 'asesor')->update(['rol' => 'vendedor']);

        DB::table('usuario')->where('correo', 'asesor@servicargo.bo')->update([
            'correo' => 'vendedor@servicargo.bo',
            'apellido' => 'Vendedor',
        ]);
    }
};
