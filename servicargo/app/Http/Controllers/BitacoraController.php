<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use Illuminate\Http\Request;

class BitacoraController extends Controller
{
    public function index(Request $request)
    {
        $q = Bitacora::with('usuario');
        if ($request->filled('accion')) {
            $q->where('accion', $request->query('accion'));
        }

        return response()->json($q->orderByDesc('id')->limit(300)->get());
    }
}
