<?php

namespace App\Servicios\Reporte;

use Amenadiel\JpGraph\Graph\Graph;
use Amenadiel\JpGraph\Graph\PieGraph;
use Amenadiel\JpGraph\Plot\BarPlot;
use Amenadiel\JpGraph\Plot\LinePlot;
use Amenadiel\JpGraph\Plot\PiePlot;

/**
 * Requisito 8 — Gráficos estadísticos generados en el servidor con JpGraph.
 *
 * Cada método arma un gráfico y devuelve su PNG en binario (sin volcarlo al
 * navegador), para que el controlador lo entregue como respuesta HTTP. Si un
 * conjunto de datos viene vacío se dibuja una imagen "sin datos" en vez de
 * dejar que JpGraph lance un error.
 */
class GraficoService
{
    public function __construct(private EstadisticaService $estadisticas) {}

    /** PNG (binario) del gráfico solicitado. */
    public function png(string $tipo): string
    {
        // JpGraph usa firmas GD antiguas que en PHP 8.3 emiten warnings/deprecations.
        // Se silencian para que NO se cuelen en el binario PNG de la respuesta HTTP.
        $displayErrors = ini_set('display_errors', '0');
        $reporting = error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE & ~E_STRICT);

        try {
            return match ($tipo) {
                'ventas-estado' => $this->ventasPorEstado(),
                'actividad' => $this->actividad(),
                'metodo-pago' => $this->metodoPago(),
                'visitas' => $this->visitas(),
                default => $this->sinDatos('Grafico no encontrado'),
            };
        } finally {
            error_reporting($reporting);
            if ($displayErrors !== false) {
                ini_set('display_errors', $displayErrors);
            }
        }
    }

    /** 1) Ventas por estado de cobro (PAGADA/PARCIAL/PENDIENTE) — torta. */
    private function ventasPorEstado(): string
    {
        $filas = $this->estadisticas->ventasPorEstado();
        $labels = [];
        $valores = [];
        $colores = [];
        $paleta = [
            'PAGADA' => '#2f9e6f',
            'PARCIAL' => '#e0a92f',
            'PENDIENTE' => '#e0455b',
        ];
        foreach ($filas as $f) {
            $c = (int) $f->cantidad;
            if ($c <= 0) {
                continue;
            }
            $labels[] = $f->estado.' ('.$c.')';
            $valores[] = $c;
            $colores[] = $paleta[$f->estado] ?? '#1f6feb';
        }

        if (empty($valores)) {
            return $this->sinDatos('Ventas por estado: sin datos');
        }

        $graph = new PieGraph(720, 340);
        $graph->title->Set('Ventas por estado de cobro');

        $pie = new PiePlot($valores);
        $pie->SetLegends($labels);
        $pie->SetSliceColors($colores);
        $pie->SetCenter(0.35, 0.55);
        $pie->SetSize(0.32);
        $pie->value->Show();
        $pie->value->SetFormat('%d');
        $graph->legend->SetPos(0.05, 0.5, 'right', 'center');
        $graph->Add($pie);

        return $this->salida($graph);
    }

    /** 2) Actividad de cotizaciones vs encomiendas en 30 días — líneas. */
    private function actividad(): string
    {
        $d = $this->estadisticas->actividad30dias();

        // Une los días presentes en ambas series y alinea los valores.
        $mapaCot = [];
        foreach ($d['cotizaciones'] as $c) {
            $mapaCot[(string) $c->dia] = (int) $c->total;
        }
        $mapaEnc = [];
        foreach ($d['encomiendas'] as $e) {
            $mapaEnc[(string) $e->dia] = (int) $e->total;
        }

        $dias = array_unique(array_merge(array_keys($mapaCot), array_keys($mapaEnc)));
        sort($dias);

        if (empty($dias)) {
            return $this->sinDatos('Actividad 30 dias: sin datos');
        }

        $labels = [];
        $serieCot = [];
        $serieEnc = [];
        foreach ($dias as $dia) {
            $labels[] = substr($dia, 5); // MM-DD
            $serieCot[] = $mapaCot[$dia] ?? 0;
            $serieEnc[] = $mapaEnc[$dia] ?? 0;
        }

        // Una línea necesita al menos 2 puntos: si solo hay un día, agrega
        // una base en cero para que el gráfico se dibuje (subida desde 0).
        if (count($labels) < 2) {
            array_unshift($labels, '');
            array_unshift($serieCot, 0);
            array_unshift($serieEnc, 0);
        }

        $graph = new Graph(720, 340);
        $graph->SetScale('textlin');
        $graph->SetMargin(60, 30, 45, 60);
        $graph->title->Set('Actividad ultimos 30 dias');
        $graph->xaxis->SetTickLabels($labels);
        $graph->xaxis->SetLabelAngle(45);
        $graph->legend->SetPos(0.02, 0.02, 'right', 'top');

        $l1 = new LinePlot($serieCot);
        $l1->SetColor('#1f6feb');
        $l1->SetWeight(2);
        $l1->SetLegend('Cotizaciones');
        $graph->Add($l1);

        $l2 = new LinePlot($serieEnc);
        $l2->SetColor('#e0455b');
        $l2->SetWeight(2);
        $l2->SetLegend('Encomiendas');
        $graph->Add($l2);

        return $this->salida($graph);
    }

    /** 3) Facturación por método de pago — torta. */
    private function metodoPago(): string
    {
        $filas = $this->estadisticas->ventasPorMetodo();
        $labels = [];
        $valores = [];
        foreach ($filas as $f) {
            $monto = (float) $f->monto;
            if ($monto <= 0) {
                continue;
            }
            $labels[] = ($f->metodo_pago ?: 'N/D').' (Bs '.number_format($monto, 0).')';
            $valores[] = $monto;
        }

        if (empty($valores)) {
            return $this->sinDatos('Facturacion por metodo: sin datos');
        }

        $graph = new PieGraph(720, 340);
        $graph->title->Set('Facturacion por metodo de pago');

        $pie = new PiePlot($valores);
        $pie->SetLegends($labels);
        $pie->SetCenter(0.35, 0.55);
        $pie->value->SetFormat('%d');
        $graph->legend->SetPos(0.05, 0.5, 'right', 'center');
        $graph->Add($pie);

        return $this->salida($graph);
    }

    /** 4) Páginas más visitadas — barras. */
    private function visitas(): string
    {
        $filas = $this->estadisticas->paginasMasVisitadas();
        if (empty($filas)) {
            return $this->sinDatos('Paginas mas visitadas: sin datos');
        }

        $labels = [];
        $valores = [];
        foreach ($filas as $f) {
            $labels[] = (string) $f['pagina'];
            $valores[] = (int) $f['contador'];
        }

        $graph = new Graph(720, 340);
        $graph->SetScale('textlin');
        $graph->SetMargin(60, 30, 45, 75);
        $graph->title->Set('Paginas mas visitadas');
        $graph->xaxis->SetTickLabels($labels);
        $graph->xaxis->SetLabelAngle(45);

        $bar = new BarPlot($valores);
        $bar->SetFillColor('#2f9e6f');
        $bar->SetWidth(0.6);
        $bar->value->Show();
        $graph->Add($bar);

        return $this->salida($graph);
    }

    /** Vuelca el gráfico a PNG en memoria y devuelve el binario. */
    private function salida(Graph $graph): string
    {
        $gd = $graph->Stroke(_IMG_HANDLER);
        ob_start();
        imagepng($gd);

        return ob_get_clean();
    }

    /** Imagen simple con un mensaje cuando no hay datos que graficar. */
    private function sinDatos(string $mensaje): string
    {
        $img = imagecreatetruecolor(720, 340);
        $fondo = imagecolorallocate($img, 245, 247, 250);
        $texto = imagecolorallocate($img, 120, 130, 140);
        imagefilledrectangle($img, 0, 0, 720, 340, $fondo);
        imagestring($img, 5, 260, 160, $mensaje, $texto);

        ob_start();
        imagepng($img);
        $bin = ob_get_clean();
        imagedestroy($img);

        return $bin;
    }
}
