<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Support\BitacoraService;
use Illuminate\Http\Request;

class AlmacenController extends Controller
{
    public function index()
    {
        return response()->json(Almacen::with('responsable')->orderBy('nombre')->get());
    }

    public function show($id)
    {
        $a = Almacen::with('responsable')->find($id);
        if (! $a) {
            return response()->json(['message' => 'Almacén no encontrado.'], 404);
        }

        return response()->json($a);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'direccion' => ['required', 'string', 'max:200'],
            'capacidad' => ['required', 'integer', 'gt:0'],
            'responsable_id' => ['required', 'integer', 'exists:usuario,id'],
        ]);

        $a = Almacen::create($datos);
        BitacoraService::registrar($request->user()->id, 'accion', 'almacenes', "Crear almacén #{$a->id}", $request);

        return response()->json(['message' => 'Almacén creado.', 'almacen' => $a], 201);
    }

    public function update(Request $request, $id)
    {
        $a = Almacen::find($id);
        if (! $a) {
            return response()->json(['message' => 'Almacén no encontrado.'], 404);
        }

        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:120'],
            'direccion' => ['sometimes', 'string', 'max:200'],
            'capacidad' => ['sometimes', 'integer', 'gt:0'],
            'responsable_id' => ['sometimes', 'integer', 'exists:usuario,id'],
        ]);

        $a->update($datos);
        BitacoraService::registrar($request->user()->id, 'accion', 'almacenes', "Editar almacén #{$a->id}", $request);

        return response()->json(['message' => 'Almacén actualizado.', 'almacen' => $a]);
    }

    public function destroy(Request $request, $id)
    {
        $a = Almacen::find($id);
        if (! $a) {
            return response()->json(['message' => 'Almacén no encontrado.'], 404);
        }

        $a->delete();
        BitacoraService::registrar($request->user()->id, 'accion', 'almacenes', "Eliminar almacén #{$id}", $request);

        return response()->json(['message' => 'Almacén eliminado.']);
    }
}
