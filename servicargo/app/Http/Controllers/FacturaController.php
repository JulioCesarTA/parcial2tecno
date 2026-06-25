<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use Illuminate\Http\Request;

class FacturaController extends Controller
{
    public function index(Request $request)
    {
        $q = Factura::with('venta');
        if ($request->user()->esCliente()) {
            $q->whereHas('venta', fn ($v) => $v->where('cliente_id', $request->user()->id));
        }

        return response()->json($q->orderByDesc('id')->get());
    }

    public function show(Request $request, $id)
    {
        $f = Factura::with('venta')->find($id);
        if (! $f) {
            return response()->json(['message' => 'Factura no encontrada.'], 404);
        }
        if ($request->user()->esCliente() && $f->venta->cliente_id !== $request->user()->id) {
            return response()->json(['message' => 'No puedes ver una factura ajena.'], 403);
        }

        return response()->json($f);
    }
}
