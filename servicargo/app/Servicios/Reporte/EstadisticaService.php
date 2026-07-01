<?php

namespace App\Servicios\Reporte;

use App\Models\Cotizacion;
use App\Models\Encomienda;
use App\Models\Venta;
use App\Models\Visita;
use Illuminate\Support\Facades\DB;

/**
 * Requisito 8 — Estadísticas del negocio y de acceso para toma de decisiones.
 * Agrega datos pensados para que el administrador decida (horas pico de pago,
 * actividad reciente, conversión, métodos de pago, páginas más usadas).
 */
class EstadisticaService
{
    /** Panel completo de estadísticas para el administrador. */
    public function panel(): array
    {
        return [
            'kpis' => $this->kpis(),
            'pagos_por_hora' => $this->pagosPorHora(),
            'actividad_30dias' => $this->actividad30dias(),
            'ventas_por_metodo' => $this->ventasPorMetodo(),
            'paginas_mas_visitadas' => $this->paginasMasVisitadas(),
            'recursos_mas_accedidos' => $this->recursosMasAccedidos(),
        ];
    }

    /** Indicadores clave del negocio. */
    public function kpis(): array
    {
        $totalCot = Cotizacion::count();
        $aprobadas = Cotizacion::whereIn('estado', ['APROBADA', 'COMPLETADA'])->count();

        return [
            'ventas_total' => Venta::count(),
            'ingresos' => round((float) DB::table('pago')->where('estado', 'REGISTRADO')->sum('monto'), 2),
            'encomiendas_activas' => Encomienda::whereNotIn('estado', ['ENTREGADO', 'CANCELADA'])->count(),
            'cotizaciones_pendientes' => Cotizacion::where('estado', 'PENDIENTE')->count(),
            'tasa_conversion' => $totalCot > 0 ? round($aprobadas / $totalCot * 100, 1) : 0,
        ];
    }

    /** Distribución de pagos confirmados por hora del día → detecta horas pico. */
    public function pagosPorHora(): array
    {
        return DB::table('pago')
            ->where('estado', 'REGISTRADO')
            ->selectRaw('EXTRACT(HOUR FROM fecha_pago)::int as hora, count(*) as cantidad, round(sum(monto)::numeric, 2) as monto')
            ->groupBy('hora')
            ->orderBy('hora')
            ->get()
            ->toArray();
    }

    /** Actividad de cotizaciones y encomiendas en los últimos 30 días (por día). */
    public function actividad30dias(): array
    {
        $desde = now()->subDays(30)->toDateString();

        $cotizaciones = DB::table('cotizacion')
            ->where('fecha_emision', '>=', $desde)
            ->selectRaw('date(fecha_emision) as dia, count(*) as total')
            ->groupBy('dia')->orderBy('dia')->get();

        $encomiendas = DB::table('encomienda')
            ->where('fecha_registro', '>=', $desde)
            ->selectRaw('date(fecha_registro) as dia, count(*) as total')
            ->groupBy('dia')->orderBy('dia')->get();

        return [
            'cotizaciones' => $cotizaciones->toArray(),
            'encomiendas' => $encomiendas->toArray(),
        ];
    }

    /** Facturación agrupada por método de pago. */
    public function ventasPorMetodo(): array
    {
        return DB::table('factura')
            ->selectRaw('metodo_pago, count(*) as cantidad, round(sum(total)::numeric, 2) as monto')
            ->groupBy('metodo_pago')
            ->orderByDesc('monto')
            ->get()
            ->toArray();
    }

    /** Páginas con más visitas (contador único por página). */
    public function paginasMasVisitadas(): array
    {
        return Visita::orderByDesc('contador')->limit(10)->get()->toArray();
    }

    /** Recursos internos más accedidos (de la bitácora). */
    public function recursosMasAccedidos(): array
    {
        return DB::table('bitacora')
            ->where('accion', 'acceso_recurso')
            ->selectRaw('recurso, count(*) as total')
            ->groupBy('recurso')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
    }
}
