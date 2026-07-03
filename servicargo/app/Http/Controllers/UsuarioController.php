<?php

namespace App\Http\Controllers;

use App\Servicios\Gestion\UsuarioService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    private array $rolesValidos = ['admin', 'asesor', 'cliente'];

    public function __construct(private UsuarioService $usuarios) {}

    public function index(Request $request)
    {
        return response()->json($this->usuarios->listar($request->query('rol')));
    }

    public function show($id)
    {
        return response()->json($this->usuarios->obtener($id));
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

        $usuario = $this->usuarios->crear($datos, $request->user()->id);

        return response()->json(['message' => 'Usuario creado.', 'usuario' => $usuario], 201);
    }

    public function update(Request $request, $id)
    {
        $usuario = $this->usuarios->obtener($id);

        $datos = $request->validate([
            'ci' => ['sometimes', 'string', 'max:20', 'regex:/^\d+$/', Rule::unique('usuario', 'ci')->ignore($usuario->id)],
            'nombre' => ['sometimes', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'apellido' => ['sometimes', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'correo' => ['sometimes', 'email', 'max:120', Rule::unique('usuario', 'correo')->ignore($usuario->id)],
            'contrasena' => ['sometimes', 'string', 'min:6', 'max:120'],
            'rol' => ['sometimes', 'string', Rule::in($this->rolesValidos)],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
        ]);

        $usuario = $this->usuarios->actualizar($usuario->id, $datos, $request->user()->id);

        return response()->json(['message' => 'Usuario actualizado.', 'usuario' => $usuario]);
    }

    // Eliminar por CI (según especificación)
    public function destroy(Request $request, $ci)
    {
        $this->usuarios->eliminarPorCi($ci, $request->user()->id);

        return response()->json(['message' => 'Usuario eliminado.']);
    }
}
