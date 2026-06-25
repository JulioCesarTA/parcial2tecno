<?php

namespace App\Support;

use App\Models\Bitacora;
use Illuminate\Http\Request;

class BitacoraService
{
    public static function registrar(
        ?int $usuarioId,
        string $accion,
        ?string $recurso = null,
        ?string $detalle = null,
        ?Request $request = null
    ): void {
        $request ??= request();

        Bitacora::create([
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'recurso' => $recurso,
            'detalle' => $detalle,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
            'fecha' => now(),
        ]);
    }
}
