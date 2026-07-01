<?php

namespace App\Http\Controllers;

use App\Servicios\Core\PermisoService;
use Illuminate\Http\Request;

class PermisoController extends Controller
{
    public function __construct(private PermisoService $permisos) {}

    // Matriz completa (rol × recurso) para el dashboard
    public function index()
    {
        return response()->json($this->permisos->matriz());
    }

    // Actualiza una celda de la matriz
    public function update(Request $request)
    {
        $datos = $request->validate([
            'rol' => ['required', 'in:admin,vendedor,cliente'],
            'recurso_id' => ['required', 'integer', 'exists:recurso,id'],
            'ver' => ['required', 'boolean'],
            'crear' => ['required', 'boolean'],
            'editar' => ['required', 'boolean'],
            'eliminar' => ['required', 'boolean'],
        ]);

        $permiso = $this->permisos->actualizar($datos, $request->user()->id);

        return response()->json(['message' => 'Permiso actualizado.', 'permiso' => $permiso]);
    }
}
