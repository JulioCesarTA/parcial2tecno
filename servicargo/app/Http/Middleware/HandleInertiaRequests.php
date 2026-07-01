<?php

namespace App\Http\Middleware;

use App\Servicios\Core\PreferenciaService;
use App\Support\MenuService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $usuario = $request->user();

        return array_merge(parent::share($request), [
            'appName' => config('app.name'),
            // Ruta base para deploy en subcarpeta (usada por withBase en el front)
            'appBasePath' => rtrim(parse_url((string) config('app.url'), PHP_URL_PATH) ?: '', '/'),

            // Datos de sesión compartidos a TODAS las páginas Inertia
            'auth' => [
                'usuario' => $usuario ? [
                    'id' => $usuario->id,
                    'ci' => $usuario->ci,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'correo' => $usuario->correo,
                    'rol' => $usuario->rol,
                    'telefono' => $usuario->telefono,
                ] : null,
            ],
            'menu' => fn () => $usuario ? MenuService::menu($usuario->rol) : [],
            'permisos' => fn () => $usuario ? MenuService::permisos($usuario->rol) : [],
            'preferencias' => fn () => $usuario ? app(PreferenciaService::class)->obtenerDe($usuario) : null,

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
