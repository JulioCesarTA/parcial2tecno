<?php

namespace App\Http\Middleware;

use App\Support\BitacoraService;
use Closure;
use Illuminate\Http\Request;

class RegistrarBitacora
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Registra el acceso al recurso (para "recursos más accedidos")
        $usuario = $request->user();
        if ($usuario) {
            $segmentos = $request->segments(); // ['api','cotizaciones',...]
            $recurso = $segmentos[1] ?? ($segmentos[0] ?? 'root');
            BitacoraService::registrar(
                $usuario->id,
                'acceso_recurso',
                $recurso,
                $request->method() . ' /' . $request->path(),
                $request
            );
        }

        return $response;
    }
}
