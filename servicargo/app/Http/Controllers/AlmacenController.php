<?php

namespace App\Http\Controllers;

use App\Servicios\Gestion\AlmacenService;
use Illuminate\Http\Request;

class AlmacenController extends Controller
{
    public function __construct(private AlmacenService $almacenes) {}

    public function index()
    {
        return response()->json($this->almacenes->listar());
    }

    public function show($id)
    {
        return response()->json($this->almacenes->obtener($id));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'direccion' => ['required', 'string', 'max:200'],
            'capacidad' => ['required', 'integer', 'gt:0'],
            'responsable_id' => ['required', 'integer', 'exists:usuario,id'],
        ]);

        $a = $this->almacenes->crear($datos, $request->user()->id);

        return response()->json(['message' => 'Almacén creado.', 'almacen' => $a], 201);
    }

    public function update(Request $request, $id)
    {
        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:120'],
            'direccion' => ['sometimes', 'string', 'max:200'],
            'capacidad' => ['sometimes', 'integer', 'gt:0'],
            'responsable_id' => ['sometimes', 'integer', 'exists:usuario,id'],
        ]);

        $a = $this->almacenes->actualizar($id, $datos, $request->user()->id);

        return response()->json(['message' => 'Almacén actualizado.', 'almacen' => $a]);
    }

    public function destroy(Request $request, $id)
    {
        $this->almacenes->eliminar($id, $request->user()->id);

        return response()->json(['message' => 'Almacén eliminado.']);
    }
}
