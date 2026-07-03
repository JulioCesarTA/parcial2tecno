<?php

namespace App\Servicios\Comercial;

use App\Models\Cotizacion;
use App\Models\CotizacionFoto;
use App\Models\DetalleCotizacion;
use App\Models\Producto;
use App\Models\Usuario;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CU05 — Gestión de Cotizaciones (módulo comercial).
 */
class CotizacionService
{
    /** Carpeta (dentro de public/) donde viven las fotos que el cliente adjunta a su solicitud. */
    private const CARPETA_FOTOS = 'fotos_cotizacion';

    /**
     * Elimina cotizaciones PENDIENTES vencidas (cabecera + detalle por cascade).
     *
     * Excluye las que ya tienen una encomienda enganchada: en el flujo normal eso
     * no pasa (generar la encomienda deja la cotización COMPLETADA), pero datos
     * inconsistentes (p. ej. de un seeder de demo mal armado) podían dejar una
     * PENDIENTE con encomienda y tirar abajo *todo* el listado con una violación
     * de llave foránea. Mejor ignorar esa fila que romper la página entera.
     *
     * Excluye también las solicitudes del cliente que todavía nadie revisó
     * (`vendedor_id` null): no tendría sentido perderlas por vencimiento antes
     * de que un admin/asesor llegue a verlas.
     */
    public function limpiarVencidas(): void
    {
        Cotizacion::where('estado', 'PENDIENTE')
            ->whereNotNull('vendedor_id')
            ->whereDoesntHave('encomienda')
            ->whereRaw("fecha_emision + (validez_dias || ' days')::interval < now()")
            ->delete();
    }

    /** Lista para el actor (el cliente solo ve las suyas). */
    public function listarPara(Usuario $actor): Collection
    {
        $this->limpiarVencidas();

        $q = Cotizacion::with(['cliente', 'vendedor']);
        if ($actor->esCliente()) {
            $q->where('cliente_id', $actor->id);
        }

        return $q->orderByDesc('id')->get();
    }

    /** Obtiene una cotización validando propiedad del cliente. */
    public function obtenerPara(Usuario $actor, int|string $id): Cotizacion
    {
        $this->limpiarVencidas();

        $cot = Cotizacion::with(['cliente', 'vendedor', 'detalles.producto', 'fotos'])->find($id);
        if (! $cot) {
            throw new ErrorDominio('Cotización no encontrada.', 404);
        }
        if ($actor->esCliente() && $cot->cliente_id !== $actor->id) {
            throw new ErrorDominio('No puedes ver una cotización ajena.', 403);
        }

        return $cot;
    }

    /** Crea una cotización con su detalle; calcula subtotal/total desde los productos. */
    public function crear(array $datos, Usuario $actor): Cotizacion
    {
        $this->limpiarVencidas();

        $cliente = Usuario::find($datos['cliente_id']);
        $asesor = Usuario::find($datos['vendedor_id']);
        if (! $cliente->esCliente()) {
            throw new ErrorDominio('El cliente indicado no tiene rol cliente.', 422);
        }
        if (! ($asesor->esAsesor() || $asesor->esAdmin())) {
            throw new ErrorDominio('El asesor indicado no tiene rol asesor/admin.', 422);
        }

        $cot = DB::transaction(function () use ($datos) {
            $impuestos = (float) ($datos['impuestos'] ?? 0);
            $subtotal = 0;
            $lineas = [];
            foreach ($datos['productos'] as $item) {
                $p = Producto::findOrFail($item['producto_id']);
                $sub = round($p->precio_unitario * $item['cantidad'], 2);
                $subtotal += $sub;
                $lineas[] = [
                    'producto_id' => $p->id,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $p->precio_unitario,
                    'subtotal' => $sub,
                ];
            }
            $subtotal = round($subtotal, 2);

            $cot = Cotizacion::create([
                'cliente_id' => $datos['cliente_id'],
                'vendedor_id' => $datos['vendedor_id'],
                'estado' => 'PENDIENTE',
                'fecha_emision' => $datos['fecha_emision'] ?? now(),
                'remitente' => $datos['remitente'],
                'destinatario' => $datos['destinatario'],
                'contenido' => $datos['contenido'],
                'origen' => $datos['origen'],
                'destino' => $datos['destino'],
                'tipo_envio' => $datos['tipo_envio'],
                'peso_kg' => $datos['peso_kg'],
                'volumen_m3' => $datos['volumen_m3'],
                'fecha_entrega_estimada' => $datos['fecha_entrega_estimada'] ?? null,
                'impuestos' => $impuestos,
                'subtotal' => $subtotal,
                'total_estimado' => round($subtotal + $impuestos, 2),
                'validez_dias' => $datos['validez_dias'],
            ]);

            foreach ($lineas as $l) {
                $l['cotizacion_id'] = $cot->id;
                DetalleCotizacion::create($l);
            }

            return $cot;
        });

        BitacoraService::registrar($actor->id, 'accion', 'cotizaciones', "Crear cotización #{$cot->id}");

        return $cot;
    }

    /**
     * El cliente pide su propia cotización desde donde esté (no tiene que ir a
     * la tienda). Se guarda sin asesor asignado y sin productos todavía —eso lo
     * completa un admin/asesor al revisarla, ver `agregarProducto()`—, junto con
     * las fotos del producto/paquete que haya adjuntado.
     */
    public function solicitar(array $datos, array $fotos, Usuario $cliente): Cotizacion
    {
        $cot = Cotizacion::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => null,
            'estado' => 'PENDIENTE',
            'fecha_emision' => now(),
            'remitente' => $datos['remitente'],
            'destinatario' => $datos['destinatario'],
            'contenido' => $datos['contenido'],
            'origen' => $datos['origen'],
            'destino' => $datos['destino'],
            'tipo_envio' => $datos['tipo_envio'],
            'peso_kg' => $datos['peso_kg'],
            'volumen_m3' => $datos['volumen_m3'],
            'fecha_entrega_estimada' => $datos['fecha_entrega_estimada'] ?? null,
            'impuestos' => 0,
            'subtotal' => 0,
            'total_estimado' => 0,
            'validez_dias' => $datos['validez_dias'],
        ]);

        $this->guardarFotos($cot, $fotos);
        BitacoraService::registrar($cliente->id, 'accion', 'cotizaciones', "Solicitar cotización #{$cot->id}");

        return $cot->load('fotos');
    }

    private function guardarFotos(Cotizacion $cot, array $fotos): void
    {
        if (! $fotos) {
            return;
        }
        $destino = public_path(self::CARPETA_FOTOS);
        if (! is_dir($destino)) {
            @mkdir($destino, 0755, true);
        }
        foreach ($fotos as $foto) {
            if (! $foto->isValid()) {
                continue;
            }
            $extension = strtolower($foto->getClientOriginalExtension() ?: $foto->extension());
            $nombre = 'cotizacion_'.$cot->id.'_'.time().'_'.Str::random(6).'.'.$extension;
            $foto->move($destino, $nombre);
            CotizacionFoto::create(['cotizacion_id' => $cot->id, 'ruta' => self::CARPETA_FOTOS.'/'.$nombre]);
        }
    }

    /** Bandeja de solicitudes del cliente que todavía nadie tomó (sin asesor asignado). */
    public function listarSolicitudesPendientes(): Collection
    {
        return Cotizacion::with(['cliente', 'fotos'])
            ->whereNull('vendedor_id')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Agrega un producto a la cotización (parte de completar una solicitud del
     * cliente, o de armar una cotización creada por el propio admin/asesor). Si
     * nadie la había tomado todavía, queda asignada a quien la está trabajando.
     */
    public function agregarProducto(int|string $id, array $datos, Usuario $actor): Cotizacion
    {
        $cot = Cotizacion::find($id);
        if (! $cot) {
            throw new ErrorDominio('Cotización no encontrada.', 404);
        }
        if ($cot->estado !== 'PENDIENTE') {
            throw new ErrorDominio('Solo se pueden agregar productos a una cotización PENDIENTE.', 422);
        }
        if ($cot->detalles()->where('producto_id', $datos['producto_id'])->exists()) {
            throw new ErrorDominio('Ese producto ya está en la cotización; edita su cantidad en vez de agregarlo de nuevo.', 422);
        }

        $producto = Producto::findOrFail($datos['producto_id']);
        DetalleCotizacion::create([
            'cotizacion_id' => $cot->id,
            'producto_id' => $producto->id,
            'cantidad' => $datos['cantidad'],
            'precio_unitario' => $producto->precio_unitario,
            'subtotal' => round($producto->precio_unitario * $datos['cantidad'], 2),
        ]);

        if ($cot->vendedor_id === null) {
            $cot->vendedor_id = $actor->id;
        }
        $this->recalcularTotales($cot);

        BitacoraService::registrar($actor->id, 'accion', 'cotizaciones', "Agregar producto a cotización #{$cot->id}");

        return $cot->fresh(['cliente', 'vendedor', 'detalles.producto', 'fotos']);
    }

    /** Edita la cantidad de un producto ya cargado en la cotización. */
    public function actualizarProducto(int|string $id, int|string $productoId, array $datos, Usuario $actor): Cotizacion
    {
        $cot = Cotizacion::find($id);
        if (! $cot) {
            throw new ErrorDominio('Cotización no encontrada.', 404);
        }
        if ($cot->estado !== 'PENDIENTE') {
            throw new ErrorDominio('Solo se puede editar el detalle de una cotización PENDIENTE.', 422);
        }
        $linea = $cot->detalles()->where('producto_id', $productoId)->first();
        if (! $linea) {
            throw new ErrorDominio('Ese producto no está en la cotización.', 404);
        }

        $cot->detalles()->where('producto_id', $productoId)->update([
            'cantidad' => $datos['cantidad'],
            'subtotal' => round((float) $linea->precio_unitario * $datos['cantidad'], 2),
        ]);
        $this->recalcularTotales($cot);

        BitacoraService::registrar($actor->id, 'accion', 'cotizaciones', "Editar producto de cotización #{$cot->id}");

        return $cot->fresh(['cliente', 'vendedor', 'detalles.producto', 'fotos']);
    }

    /** Quita un producto de la cotización (no afecta nada más; solo recalcula el total). */
    public function eliminarProducto(int|string $id, int|string $productoId, Usuario $actor): Cotizacion
    {
        $cot = Cotizacion::find($id);
        if (! $cot) {
            throw new ErrorDominio('Cotización no encontrada.', 404);
        }
        if ($cot->estado !== 'PENDIENTE') {
            throw new ErrorDominio('Solo se puede editar el detalle de una cotización PENDIENTE.', 422);
        }

        $cot->detalles()->where('producto_id', $productoId)->delete();
        $this->recalcularTotales($cot);

        BitacoraService::registrar($actor->id, 'accion', 'cotizaciones', "Quitar producto de cotización #{$cot->id}");

        return $cot->fresh(['cliente', 'vendedor', 'detalles.producto', 'fotos']);
    }

    private function recalcularTotales(Cotizacion $cot): void
    {
        $subtotal = (float) $cot->detalles()->sum('subtotal');
        $cot->subtotal = $subtotal;
        $cot->total_estimado = round($subtotal + (float) $cot->impuestos, 2);
        $cot->save();
    }

    /** Edita una cotización PENDIENTE (impuestos, validez o estado). */
    public function actualizar(int|string $id, array $datos, Usuario $actor): Cotizacion
    {
        $this->limpiarVencidas();

        $cot = Cotizacion::find($id);
        if (! $cot) {
            throw new ErrorDominio('Cotización no encontrada o ya vencida.', 404);
        }
        if ($cot->estado !== 'PENDIENTE') {
            throw new ErrorDominio('Solo se puede editar una cotización en estado PENDIENTE.', 422);
        }

        if (isset($datos['impuestos'])) {
            $cot->impuestos = $datos['impuestos'];
            $cot->total_estimado = round($cot->subtotal + $datos['impuestos'], 2);
        }
        if (isset($datos['validez_dias'])) {
            $cot->validez_dias = $datos['validez_dias'];
        }
        if (isset($datos['estado'])) {
            $cot->estado = $datos['estado'];
        }
        $cot->save();

        BitacoraService::registrar($actor->id, 'accion', 'cotizaciones', "Editar cotización #{$cot->id}");

        return $cot;
    }

    /** Elimina una cotización PENDIENTE (borrado forzado admin/asesor). */
    public function eliminar(int|string $id, Usuario $actor): void
    {
        $cot = Cotizacion::find($id);
        if (! $cot) {
            throw new ErrorDominio('Cotización no encontrada.', 404);
        }
        if ($cot->estado !== 'PENDIENTE') {
            throw new ErrorDominio('Solo se puede eliminar una cotización PENDIENTE.', 422);
        }

        $cot->delete(); // cascade borra detalle
        BitacoraService::registrar($actor->id, 'accion', 'cotizaciones', "Eliminar cotización #{$id}");
    }

    /** El cliente dueño aprueba (si) o rechaza (no) la cotización. */
    public function decidir(int|string $id, array $datos, Usuario $actor): Cotizacion
    {
        $cot = Cotizacion::find($id);
        if (! $cot) {
            throw new ErrorDominio('Cotización no encontrada.', 404);
        }
        if ($cot->cliente_id !== $actor->id) {
            throw new ErrorDominio('Solo el cliente dueño puede aprobar o rechazar.', 403);
        }
        if ($cot->estado !== 'PENDIENTE') {
            throw new ErrorDominio('La cotización ya no está pendiente.', 422);
        }
        if ($cot->estaVencida()) {
            $cot->delete();
            throw new ErrorDominio('La cotización venció y fue eliminada.', 410);
        }

        if ($datos['decision'] === 'si') {
            if ($cot->detalles()->count() === 0) {
                throw new ErrorDominio('Todavía no hay productos cargados en esta cotización; esperá a que el asesor la complete.', 422);
            }
            $cot->estado = 'APROBADA';
            $cot->fecha_aprobacion = now(); // ventana de 20 min para el asesor
        } else {
            $cot->estado = 'RECHAZADA';
        }
        $cot->save();

        BitacoraService::registrar(
            $actor->id,
            'accion',
            'cotizaciones',
            "Cotización #{$cot->id} " . ($datos['decision'] === 'si' ? 'APROBADA' : 'RECHAZADA')
        );

        return $cot;
    }
}
