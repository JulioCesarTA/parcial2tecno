<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Support\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    private array $rolesValidos = ['admin', 'vendedor', 'cliente'];

    public function index(Request $request)
    {
        $rol = $request->query('rol');
        $q = Usuario::query();
        if ($rol && $rol !== '*') {
            $q->where('rol', Usuario::normalizarRol($rol));
        }

        return response()->json($q->orderBy('id')->get());
    }

    public function show($id)
    {
        $usuario = Usuario::find($id);
        if (! $usuario) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        return response()->json($usuario);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'ci' => ['required', 'string', 'max:20', 'regex:/^\d+$/', 'unique:usuario,ci'],
            'nombre' => ['required', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'apellido' => ['required', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'correo' => ['required', 'email', 'max:120', 'unique:usuario,correo'],
            'contrasena' => ['required', 'string', 'min:6', 'max:120'],
            'rol' => ['required', 'string', Rule::in($this->rolesValidos)],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
        ]);

        $datos['rol'] = Usuario::normalizarRol($datos['rol']);
        $datos['contrasena'] = Hash::make($datos['contrasena']);
        $usuario = Usuario::create($datos);

        BitacoraService::registrar($request->user()->id, 'accion', 'usuarios', "Crear usuario #{$usuario->id}", $request);

        return response()->json(['message' => 'Usuario creado.', 'usuario' => $usuario], 201);
    }

    public function update(Request $request, $id)
    {
        $usuario = Usuario::find($id);
        if (! $usuario) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        $datos = $request->validate([
            'ci' => ['sometimes', 'string', 'max:20', 'regex:/^\d+$/', Rule::unique('usuario', 'ci')->ignore($usuario->id)],
            'nombre' => ['sometimes', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'apellido' => ['sometimes', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'correo' => ['sometimes', 'email', 'max:120', Rule::unique('usuario', 'correo')->ignore($usuario->id)],
            'contrasena' => ['sometimes', 'string', 'min:6', 'max:120'],
            'rol' => ['sometimes', 'string', Rule::in($this->rolesValidos)],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
        ]);

        if (isset($datos['contrasena'])) {
            $datos['contrasena'] = Hash::make($datos['contrasena']);
        }
        if (isset($datos['rol'])) {
            $datos['rol'] = Usuario::normalizarRol($datos['rol']);
        }

        $usuario->update($datos);
        BitacoraService::registrar($request->user()->id, 'accion', 'usuarios', "Editar usuario #{$usuario->id}", $request);

        return response()->json(['message' => 'Usuario actualizado.', 'usuario' => $usuario]);
    }

    // Eliminar por CI (según especificación)
    public function destroy(Request $request, $ci)
    {
        $usuario = Usuario::where('ci', $ci)->first();
        if (! $usuario) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        $usuario->delete();
        BitacoraService::registrar($request->user()->id, 'accion', 'usuarios', "Eliminar usuario CI {$ci}", $request);

        return response()->json(['message' => 'Usuario eliminado.']);
    }
}
