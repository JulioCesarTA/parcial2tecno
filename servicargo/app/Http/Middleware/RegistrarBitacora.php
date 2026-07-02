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

        // Aquí solo se audita la DESCARGA de PDF. Las acciones de negocio
        // (crear/editar/eliminar) las registran los propios servicios con su
        // detalle descriptivo, así que no se duplican; y las consultas (GET)
        // no se auditan.
        $usuario = $request->user();
        if ($usuario && str_contains($request->path(), '/pdf')) {
            $segmentos = $request->segments(); // ['api','reportes','ventas','pdf']
            $recurso = $segmentos[1] ?? ($segmentos[0] ?? 'root');
            BitacoraService::registrar(
                $usuario->id,
                'acceso_recurso',
                $recurso,
                'Descargar PDF',
                $request
            );
        }

        return $response;
    }
}
