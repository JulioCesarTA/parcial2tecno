<?php

namespace App\Http\Controllers;

use App\Servicios\Core\AuthService;
use App\Support\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Autenticación idiomática de Inertia (sesión de Laravel).
 * Convive con el AuthController (JWT) mientras se migran los módulos.
 */
class SessionAuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function login(Request $request)
    {
        $datos = $request->validate([
            'correo' => ['required', 'email'],
            'contrasena' => ['required', 'string'],
        ]);

        $usuario = $this->auth->verificar($datos['correo'], $datos['contrasena']);

        if (! $usuario) {
            BitacoraService::registrar(null, 'login_fallido', 'auth', $datos['correo'], $request);

            return back()->withErrors(['correo' => 'Correo o contraseña incorrectos.'])->onlyInput('correo');
        }

        Auth::login($usuario);
        $request->session()->regenerate();
        BitacoraService::registrar($usuario->id, 'login_ok', 'auth', null, $request);

        return redirect()->intended(route('inicio'));
    }

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

        $usuario = $this->auth->registrarCliente($datos);

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->intended(route('inicio'));
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            BitacoraService::registrar($request->user()->id, 'logout', 'auth', null, $request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }
}
