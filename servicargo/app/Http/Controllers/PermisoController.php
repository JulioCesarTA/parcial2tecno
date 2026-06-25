<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Models\Recurso;
use App\Support\BitacoraService;
use Illuminate\Http\Request;

class PermisoController extends Controller
{
    // Matriz completa (rol × recurso) para el dashboard
    public function index()
    {
        return response()->json([
            'recursos' => Recurso::orderBy('orden')->get(),
            'roles' => ['admin', 'vendedor', 'cliente'],
            'permisos' => Permiso::all(),
        ]);
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

        $permiso = Permiso::updateOrCreate(
            ['rol' => $datos['rol'], 'recurso_id' => $datos['recurso_id']],
            $datos
        );

        BitacoraService::registrar($request->user()->id, 'accion', 'permisos', "Actualizar matriz {$datos['rol']}/{$datos['recurso_id']}", $request);

        return response()->json(['message' => 'Permiso actualizado.', 'permiso' => $permiso]);
    }
}
