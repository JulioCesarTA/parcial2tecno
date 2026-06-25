<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Support\BitacoraService;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index()
    {
        return response()->json(Categoria::orderBy('nombre')->get());
    }

    public function show($id)
    {
        $c = Categoria::find($id);
        if (! $c) {
            return response()->json(['message' => 'Categoría no encontrada.'], 404);
        }

        return response()->json($c);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $c = Categoria::create($datos);
        BitacoraService::registrar($request->user()->id, 'accion', 'catalogo', "Crear categoría #{$c->id}", $request);

        return response()->json(['message' => 'Categoría creada.', 'categoria' => $c], 201);
    }

    public function update(Request $request, $id)
    {
        $c = Categoria::find($id);
        if (! $c) {
            return response()->json(['message' => 'Categoría no encontrada.'], 404);
        }

        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $c->update($datos);
        BitacoraService::registrar($request->user()->id, 'accion', 'catalogo', "Editar categoría #{$c->id}", $request);

        return response()->json(['message' => 'Categoría actualizada.', 'categoria' => $c]);
    }

    public function destroy(Request $request, $id)
    {
        $c = Categoria::find($id);
        if (! $c) {
            return response()->json(['message' => 'Categoría no encontrada.'], 404);
        }

        $c->delete(); // soft delete
        BitacoraService::registrar($request->user()->id, 'accion', 'catalogo', "Eliminar categoría #{$id}", $request);

        return response()->json(['message' => 'Categoría eliminada.']);
    }
}
