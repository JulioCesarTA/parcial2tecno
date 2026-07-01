<?php

namespace App\Http\Controllers;

use App\Servicios\Comercial\MetodoPagoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetodoPagoController extends Controller
{
    public function __construct(private MetodoPagoService $metodos) {}

    // Cada usuario gestiona SUS propios métodos de pago
    public function index(Request $request)
    {
        return response()->json($this->metodos->listarDe($request->user()));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['EFECTIVO', 'QR'])],
            'alias' => ['required', 'string', 'max:80'],
            'referencia' => ['nullable', 'string', 'max:60'],
        ]);

        $m = $this->metodos->crear($datos, $request->user());

        return response()->json(['message' => 'Método de pago registrado.', 'metodo' => $m], 201);
    }

    public function update(Request $request, $id)
    {
        $datos = $request->validate([
            'alias' => ['sometimes', 'string', 'max:80'],
            'referencia' => ['nullable', 'string', 'max:60'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $m = $this->metodos->actualizar($id, $datos, $request->user());

        return response()->json(['message' => 'Método de pago actualizado.', 'metodo' => $m]);
    }

    public function destroy(Request $request, $id)
    {
        $this->metodos->eliminar($id, $request->user());

        return response()->json(['message' => 'Método de pago eliminado.']);
    }
}
