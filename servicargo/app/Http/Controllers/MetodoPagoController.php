<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use App\Support\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetodoPagoController extends Controller
{
    // Cada usuario gestiona SUS propios métodos de pago
    public function index(Request $request)
    {
        return response()->json(
            MetodoPago::where('usuario_id', $request->user()->id)->orderByDesc('id')->get()
        );
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['EFECTIVO', 'QR'])],
            'alias' => ['required', 'string', 'max:80'],
            'referencia' => ['nullable', 'string', 'max:60'],
        ]);
        $datos['usuario_id'] = $request->user()->id;
        $datos['activo'] = true;

        $m = MetodoPago::create($datos);
        BitacoraService::registrar($request->user()->id, 'accion', 'metodos_pago', "Registrar método de pago #{$m->id}", $request);

        return response()->json(['message' => 'Método de pago registrado.', 'metodo' => $m], 201);
    }

    public function update(Request $request, $id)
    {
        $m = MetodoPago::where('usuario_id', $request->user()->id)->find($id);
        if (! $m) {
            return response()->json(['message' => 'Método de pago no encontrado.'], 404);
        }

        $datos = $request->validate([
            'alias' => ['sometimes', 'string', 'max:80'],
            'referencia' => ['nullable', 'string', 'max:60'],
            'activo' => ['sometimes', 'boolean'],
        ]);
        $m->update($datos);

        return response()->json(['message' => 'Método de pago actualizado.', 'metodo' => $m]);
    }

    public function destroy(Request $request, $id)
    {
        $m = MetodoPago::where('usuario_id', $request->user()->id)->find($id);
        if (! $m) {
            return response()->json(['message' => 'Método de pago no encontrado.'], 404);
        }
        $m->delete();
        BitacoraService::registrar($request->user()->id, 'accion', 'metodos_pago', "Eliminar método de pago #{$id}", $request);

        return response()->json(['message' => 'Método de pago eliminado.']);
    }
}
