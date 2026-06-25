<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Cotizacion;
use App\Models\Encomienda;
use App\Models\Factura;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Reporte;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    private function rango(Request $request): array
    {
        $ini = $request->query('fecha_inicio');
        $fin = $request->query('fecha_fin');
        if ($ini === '*' || $ini === null) {
            return [null, null];
        }

        return [$ini, $fin ?: now()->toDateString()];
    }

    private function registrar(Request $request, string $tipo): void
    {
        Reporte::create([
            'propietario_id' => $request->user()->id,
            'fecha_generacion' => now(),
            'parametros' => json_encode($request->only(['fecha_inicio', 'fecha_fin'])),
            'tipo_reporte' => $tipo,
        ]);
    }

    public function ventas(Request $request)
    {
        [$ini, $fin] = $this->rango($request);
        $q = Venta::query();
        if ($ini) {
            $q->whereBetween('fecha_venta', [$ini . ' 00:00:00', $fin . ' 23:59:59']);
        }
        $ventas = $q->get();

        $data = [
            'total_ventas' => $ventas->count(),
            'monto_total' => round($ventas->sum('total_final'), 2),
            'pagadas' => $ventas->where('estado', 'PAGADA')->count(),
            'parciales' => $ventas->where('estado', 'PARCIAL')->count(),
            'pendientes' => $ventas->where('estado', 'PENDIENTE')->count(),
            'total_cobrado' => round((float) DB::table('pago')->where('estado', 'REGISTRADO')->sum('monto'), 2),
            'por_tipo_pago' => $ventas->groupBy('tipo_pago')->map(fn ($g) => [
                'cantidad' => $g->count(),
                'monto' => round($g->sum('total_final'), 2),
            ]),
            'ultimas' => Venta::orderByDesc('id')->limit(50)->get(),
        ];

        $this->registrar($request, 'REPVEN');

        return response()->json($data);
    }

    public function encomiendas(Request $request)
    {
        [$ini, $fin] = $this->rango($request);
        $q = Encomienda::query();
        if ($ini) {
            $q->whereBetween('fecha_registro', [$ini . ' 00:00:00', $fin . ' 23:59:59']);
        }
        $enc = $q->get();

        $this->registrar($request, 'REPENC');

        return response()->json([
            'total' => $enc->count(),
            'por_estado' => $enc->groupBy('estado')->map->count(),
            'ultimas' => Encomienda::orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function inventario(Request $request)
    {
        $stock = Inventario::with(['producto', 'almacen'])
            ->orderBy('cantidad')
            ->get()
            ->map(fn ($i) => [
                'producto' => $i->producto?->nombre,
                'almacen' => $i->almacen?->nombre,
                'cantidad' => $i->cantidad,
                'stock_minimo' => $i->stock_minimo,
                'nivel' => $i->cantidad <= $i->stock_minimo ? 'BAJO' : 'OK',
            ]);

        $this->registrar($request, 'REPINV');

        return response()->json([
            'total_productos' => Producto::count(),
            'unidades_totales' => (int) Inventario::sum('cantidad'),
            'stock' => $stock,
        ]);
    }

    public function cotizaciones(Request $request)
    {
        [$ini, $fin] = $this->rango($request);
        $q = Cotizacion::query();
        if ($ini) {
            $q->whereBetween('fecha_emision', [$ini . ' 00:00:00', $fin . ' 23:59:59']);
        }
        $cot = $q->get();
        $total = $cot->count();
        $aprobadas = $cot->whereIn('estado', ['APROBADA', 'COMPLETADA'])->count();

        $this->registrar($request, 'REPCOT');

        return response()->json([
            'total' => $total,
            'tasa_conversion' => $total > 0 ? round($aprobadas / $total * 100, 1) : 0,
            'por_estado' => $cot->groupBy('estado')->map(fn ($g) => [
                'cantidad' => $g->count(),
                'monto' => round($g->sum('total_estimado'), 2),
            ]),
            'ultimas' => Cotizacion::orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function facturas(Request $request)
    {
        [$ini, $fin] = $this->rango($request);
        $q = Factura::query();
        if ($ini) {
            $q->whereBetween('fecha_emision', [$ini . ' 00:00:00', $fin . ' 23:59:59']);
        }
        $fac = $q->get();

        $this->registrar($request, 'REPFAC');

        return response()->json([
            'total' => $fac->count(),
            'monto_total' => round($fac->sum('total'), 2),
            'por_metodo' => $fac->groupBy('metodo_pago')->map(fn ($g) => [
                'cantidad' => $g->count(),
                'monto' => round($g->sum('total'), 2),
            ]),
            'ultimas' => Factura::orderByDesc('id')->limit(50)->get(),
        ]);
    }

    // Estadísticas de acceso (de la bitácora)
    public function acceso(Request $request)
    {
        return response()->json([
            'logins_ok' => Bitacora::where('accion', 'login_ok')->count(),
            'logins_fallidos' => Bitacora::where('accion', 'login_fallido')->count(),
            'recursos_mas_accedidos' => Bitacora::where('accion', 'acceso_recurso')
                ->select('recurso', DB::raw('count(*) as total'))
                ->groupBy('recurso')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ]);
    }

    // Export PDF (dompdf)
    public function pdf(Request $request, $tipo)
    {
        $mapa = [
            'ventas' => 'REPVEN', 'encomiendas' => 'REPENC', 'inventario' => 'REPINV',
            'cotizaciones' => 'REPCOT', 'facturas' => 'REPFAC',
        ];
        if (! isset($mapa[$tipo])) {
            return response()->json(['message' => 'Tipo de reporte inválido.'], 422);
        }

        $datos = match ($tipo) {
            'ventas' => $this->ventas($request)->getData(true),
            'encomiendas' => $this->encomiendas($request)->getData(true),
            'inventario' => $this->inventario($request)->getData(true),
            'cotizaciones' => $this->cotizaciones($request)->getData(true),
            'facturas' => $this->facturas($request)->getData(true),
        };

        $pdf = Pdf::loadView('reportes.generico', [
            'titulo' => 'Reporte de ' . ucfirst($tipo),
            'datos' => $datos,
            'fecha' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("reporte-{$tipo}.pdf");
    }
}
