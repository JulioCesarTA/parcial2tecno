<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Support\BitacoraService;
use App\Support\JwtService;
use App\Support\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    // Auto-registro de cliente (público)
    public function register(Request $request)
    {
        $datos = $request->validate([
            'ci' => ['required', 'string', 'max:20', 'regex:/^\d+$/', 'unique:usuario,ci'],
            'nombre' => ['required', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'apellido' => ['required', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'correo' => ['required', 'email', 'max:120', 'unique:usuario,correo'],
            'contrasena' => ['required', 'string', 'min:6', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
        ]);

        $datos['rol'] = 'cliente';
        $datos['contrasena'] = Hash::make($datos['contrasena']);
        $usuario = Usuario::create($datos);

        BitacoraService::registrar($usuario->id, 'accion', 'usuarios', 'Auto-registro de cliente', $request);

        return response()->json([
            'message' => 'Cuenta creada correctamente.',
            'usuario' => $usuario,
        ], 201);
    }

    public function login(Request $request)
    {
        $datos = $request->validate([
            'correo' => ['required', 'email'],
            'contrasena' => ['required', 'string'],
        ]);

        $usuario = Usuario::where('correo', $datos['correo'])->first();

        if (! $usuario || ! Hash::check($datos['contrasena'], $usuario->contrasena)) {
            BitacoraService::registrar($usuario->id ?? null, 'login_fallido', 'auth', $datos['correo'], $request);

            return response()->json(['message' => 'Correo o contraseña incorrectos.'], 401);
        }

        $jwt = JwtService::emitir($usuario);
        BitacoraService::registrar($usuario->id, 'login_ok', 'auth', null, $request);

        return response()->json([
            'token' => $jwt['token'],
            'expira_en' => $jwt['expira_en'],
            'usuario' => $usuario,
            'permisos' => MenuService::permisos($usuario->rol),
            'menu' => MenuService::menu($usuario->rol),
        ]);
    }

    public function me(Request $request)
    {
        $usuario = $request->user();

        return response()->json([
            'usuario' => $usuario,
            'permisos' => MenuService::permisos($usuario->rol),
            'menu' => MenuService::menu($usuario->rol),
        ]);
    }

    public function logout(Request $request)
    {
        BitacoraService::registrar($request->user()->id, 'logout', 'auth', null, $request);

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
