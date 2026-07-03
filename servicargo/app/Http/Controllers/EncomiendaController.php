<?php

namespace App\Http\Controllers;

use App\Servicios\Comercial\EncomiendaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class EncomiendaController extends Controller
{
    public function __construct(private EncomiendaService $encomiendas) {}

    public function index(Request $request)
    {
        return response()->json($this->encomiendas->listarPara($request->user()));
    }

    public function show(Request $request, $id)
    {
        return response()->json($this->encomiendas->obtenerPara($request->user(), $id));
    }

    public function pdf(Request $request, $id)
    {
        $enc = $this->encomiendas->obtenerPara($request->user(), $id);

        $pdf = Pdf::loadView('reportes.encomienda', [
            'encomienda' => $enc,
            'fecha' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("encomienda-{$enc->guia_rastreo}.pdf");
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:usuario,id'],
            'guia_rastreo' => ['required', 'integer'], // = id de la cotización APROBADA
        ]);

        $enc = $this->encomiendas->crear($datos, $request->user());

        return response()->json([
            'message' => 'Encomienda creada.',
            'id' => $enc->id,
            'guia_rastreo' => $enc->guia_rastreo,
        ], 201);
    }

    // Actualizar: cambio de estado (con historial) y/o edición de datos
    public function update(Request $request, $id)
    {
        $datos = $request->validate([
            'estado' => ['sometimes', 'string', 'max:30'],
            'fecha_cambio' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string'],
            'remitente' => ['sometimes', 'string', 'max:120'],
            'destinatario' => ['sometimes', 'string', 'max:120'],
            'contenido' => ['sometimes', 'string'],
            'origen' => ['sometimes', 'string', 'max:120'],
            'destino' => ['sometimes', 'string', 'max:120'],
            'tipo_envio' => ['sometimes', 'in:aereo,maritimo,terrestre'],
            'peso_kg' => ['sometimes', 'numeric', 'gt:0'],
            'volumen_m3' => ['sometimes', 'numeric', 'gt:0'],
            'fecha_entrega_estimada' => ['nullable', 'date'],
        ]);

        $enc = $this->encomiendas->actualizar($id, $datos, $request->user());

        return response()->json(['message' => 'Encomienda actualizada.', 'encomienda' => $enc]);
    }
}
