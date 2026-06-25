<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Support\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    private array $tiposValidos = ['carga_general', 'fragil', 'perecedera', 'peligrosa'];

    public function index(Request $request)
    {
        $q = Producto::with('categoria');
        if ($request->filled('categoria_id') && $request->query('categoria_id') !== '*') {
            $q->where('categoria_id', $request->query('categoria_id'));
        }

        return response()->json($q->orderBy('nombre')->get());
    }

    public function show($codigo)
    {
        $p = Producto::with('categoria')->where('codigo', $codigo)->first();
        if (! $p) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        return response()->json($p);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'categoria_id' => ['required', 'integer', 'exists:categoria,id'],
            'codigo' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._\-]+$/', 'unique:producto,codigo'],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string'],
            'precio_unitario' => ['required', 'numeric', 'gt:0'],
            'tipo' => ['required', Rule::in($this->tiposValidos)],
        ]);

        $p = Producto::create($datos);
        BitacoraService::registrar($request->user()->id, 'accion', 'catalogo', "Crear producto {$p->codigo}", $request);

        return response()->json(['message' => 'Producto creado.', 'producto' => $p], 201);
    }

    public function update(Request $request, $codigo)
    {
        $p = Producto::where('codigo', $codigo)->first();
        if (! $p) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $datos = $request->validate([
            'categoria_id' => ['sometimes', 'integer', 'exists:categoria,id'],
            'nombre' => ['sometimes', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string'],
            'precio_unitario' => ['sometimes', 'numeric', 'gt:0'],
            'tipo' => ['sometimes', Rule::in($this->tiposValidos)],
        ]);

        $p->update($datos);
        BitacoraService::registrar($request->user()->id, 'accion', 'catalogo', "Editar producto {$p->codigo}", $request);

        return response()->json(['message' => 'Producto actualizado.', 'producto' => $p]);
    }

    public function destroy(Request $request, $codigo)
    {
        $p = Producto::where('codigo', $codigo)->first();
        if (! $p) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $p->delete(); // soft delete
        BitacoraService::registrar($request->user()->id, 'accion', 'catalogo', "Eliminar producto {$codigo}", $request);

        return response()->json(['message' => 'Producto eliminado.']);
    }
}
