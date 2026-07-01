<?php

namespace App\Http\Controllers;

use App\Servicios\Comercial\FacturaService;
use Illuminate\Http\Request;

class FacturaController extends Controller
{
    public function __construct(private FacturaService $facturas) {}

    public function index(Request $request)
    {
        return response()->json($this->facturas->listarPara($request->user()));
    }

    public function show(Request $request, $id)
    {
        return response()->json($this->facturas->obtenerPara($request->user(), $id));
    }
}
