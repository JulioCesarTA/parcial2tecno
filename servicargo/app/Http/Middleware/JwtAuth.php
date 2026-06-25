<?php

namespace App\Http\Middleware;

use App\Support\JwtService;
use Closure;
use Illuminate\Http\Request;

class JwtAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $usuario = JwtService::usuarioDesdeToken($token);

        if (! $usuario) {
            return response()->json([
                'message' => 'No autenticado o sesión expirada. Vuelve a iniciar sesión.',
            ], 401);
        }

        // Disponible vía $request->user() en los controllers
        $request->setUserResolver(fn () => $usuario);
        app()->instance('usuario_actual', $usuario);

        return $next($request);
    }
}
