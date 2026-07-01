<?php

namespace App\Http\Controllers;

use App\Servicios\Gestion\ProductoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    private array $tiposValidos = ['carga_general', 'fragil', 'perecedera', 'peligrosa'];

    public function __construct(private ProductoService $productos) {}

    public function index(Request $request)
    {
        return response()->json($this->productos->listar($request->query('categoria_id')));
    }

    public function show($codigo)
    {
        return response()->json($this->productos->obtenerPorCodigo($codigo));
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

        $p = $this->productos->crear($datos, $request->user()->id);

        return response()->json(['message' => 'Producto creado.', 'producto' => $p], 201);
    }

    public function update(Request $request, $codigo)
    {
        $datos = $request->validate([
            'categoria_id' => ['sometimes', 'integer', 'exists:categoria,id'],
            'nombre' => ['sometimes', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string'],
            'precio_unitario' => ['sometimes', 'numeric', 'gt:0'],
            'tipo' => ['sometimes', Rule::in($this->tiposValidos)],
        ]);

        $p = $this->productos->actualizarPorCodigo($codigo, $datos, $request->user()->id);

        return response()->json(['message' => 'Producto actualizado.', 'producto' => $p]);
    }

    public function destroy(Request $request, $codigo)
    {
        $this->productos->eliminarPorCodigo($codigo, $request->user()->id);

        return response()->json(['message' => 'Producto eliminado.']);
    }
}
