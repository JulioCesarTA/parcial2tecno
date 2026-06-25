<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\Encomienda;
use App\Models\HistorialEstado;
use App\Support\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EncomiendaController extends Controller
{
    private const VENTANA_MIN = 20;

    public function index(Request $request)
    {
        $q = Encomienda::with(['cliente', 'cotizacion']);
        if ($request->user()->esCliente()) {
            $q->where('cliente_id', $request->user()->id);
        }

        return response()->json($q->orderByDesc('id')->get());
    }

    public function show(Request $request, $id)
    {
        $e = Encomienda::with(['cliente', 'cotizacion', 'historial'])->find($id);
        if (! $e) {
            return response()->json(['message' => 'Encomienda no encontrada.'], 404);
        }
        if ($request->user()->esCliente() && $e->cliente_id !== $request->user()->id) {
            return response()->json(['message' => 'No puedes ver una encomienda ajena.'], 403);
        }

        return response()->json($e);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:usuario,id'],
            'guia_rastreo' => ['required', 'integer'], // = id de la cotización APROBADA
        ]);

        $cot = Cotizacion::find($datos['guia_rastreo']);
        if (! $cot) {
            return response()->json(['message' => 'La cotización indicada no existe.'], 404);
        }
        if ($cot->cliente_id !== (int) $datos['cliente_id']) {
            return response()->json(['message' => 'La cotización no pertenece a ese cliente.'], 422);
        }
        if ($cot->estado !== 'APROBADA') {
            return response()->json(['message' => 'La cotización debe estar APROBADA.'], 422);
        }

        // Ventana de asignación de 20 minutos
        $actor = $request->user();
        if ($cot->fecha_aprobacion && ! $actor->esAdmin() && $actor->id !== $cot->vendedor_id) {
            $minutos = $cot->fecha_aprobacion->diffInMinutes(now());
            if ($minutos < self::VENTANA_MIN) {
                $faltan = self::VENTANA_MIN - $minutos;

                return response()->json([
                    'message' => "Encomienda reservada al vendedor que atendió la cotización. Faltan {$faltan} min.",
                ], 423);
            }
        }

        if (Encomienda::where('guia_rastreo', (string) $cot->id)->exists()) {
            return response()->json(['message' => 'Ya existe una encomienda para esta cotización.'], 409);
        }

        $enc = DB::transaction(function () use ($cot, $datos) {
            $enc = Encomienda::create([
                'cliente_id' => $datos['cliente_id'],
                'cotizacion_id' => $cot->id,
                'guia_rastreo' => (string) $cot->id,
                'remitente' => $cot->remitente,
                'destinatario' => $cot->destinatario,
                'contenido' => $cot->contenido,
                'origen' => $cot->origen,
                'destino' => $cot->destino,
                'tipo_envio' => $cot->tipo_envio,
                'peso_kg' => $cot->peso_kg,
                'volumen_m3' => $cot->volumen_m3,
                'estado' => 'REGISTRADA',
                'fecha_registro' => now(),
                'fecha_entrega_estimada' => $cot->fecha_entrega_estimada,
            ]);

            HistorialEstado::create([
                'encomienda_id' => $enc->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'REGISTRADA',
                'fecha_cambio' => now(),
                'observaciones' => 'Encomienda registrada desde cotización aprobada.',
            ]);

            $cot->estado = 'COMPLETADA';
            $cot->fecha_aprobacion = null; // se limpia el registro de la ventana
            $cot->save();

            return $enc;
        });

        BitacoraService::registrar($request->user()->id, 'accion', 'encomiendas', "Crear encomienda #{$enc->id} (guía {$enc->guia_rastreo})", $request);

        return response()->json([
            'message' => 'Encomienda creada.',
            'id' => $enc->id,
            'guia_rastreo' => $enc->guia_rastreo,
        ], 201);
    }

    // Actualizar: cambio de estado (con historial) y/o edición de datos
    public function update(Request $request, $id)
    {
        $enc = Encomienda::find($id);
        if (! $enc) {
            return response()->json(['message' => 'Encomienda no encontrada.'], 404);
        }

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

        DB::transaction(function () use ($enc, $datos) {
            // Cambio de estado → registra historial
            if (isset($datos['estado']) && $datos['estado'] !== $enc->estado) {
                HistorialEstado::create([
                    'encomienda_id' => $enc->id,
                    'estado_anterior' => $enc->estado,
                    'estado_nuevo' => $datos['estado'],
                    'fecha_cambio' => $datos['fecha_cambio'] ?? now(),
                    'observaciones' => $datos['observaciones'] ?? null,
                ]);
                $enc->estado = $datos['estado'];
            }

            // Edición de datos
            foreach (['remitente', 'destinatario', 'contenido', 'origen', 'destino', 'tipo_envio', 'peso_kg', 'volumen_m3', 'fecha_entrega_estimada'] as $campo) {
                if (array_key_exists($campo, $datos)) {
                    $enc->$campo = $datos[$campo];
                }
            }
            $enc->save();
        });

        BitacoraService::registrar($request->user()->id, 'accion', 'encomiendas', "Actualizar encomienda #{$enc->id}", $request);

        return response()->json(['message' => 'Encomienda actualizada.', 'encomienda' => $enc->fresh('historial')]);
    }
}
