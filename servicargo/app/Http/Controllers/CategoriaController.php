<?php

namespace App\Http\Controllers;

use App\Servicios\Gestion\CategoriaService;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function __construct(private CategoriaService $categorias) {}

    public function index()
    {
        return response()->json($this->categorias->listar());
    }

    public function show($id)
    {
        return response()->json($this->categorias->obtener($id));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $c = $this->categorias->crear($datos, $request->user()->id);

        return response()->json(['message' => 'Categoría creada.', 'categoria' => $c], 201);
    }

    public function update(Request $request, $id)
    {
        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $c = $this->categorias->actualizar($id, $datos, $request->user()->id);

        return response()->json(['message' => 'Categoría actualizada.', 'categoria' => $c]);
    }

    public function destroy(Request $request, $id)
    {
        $this->categorias->eliminar($id, $request->user()->id);

        return response()->json(['message' => 'Categoría eliminada.']);
    }
}
