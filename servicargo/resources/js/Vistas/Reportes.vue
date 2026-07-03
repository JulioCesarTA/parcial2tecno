<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useAuth } from '../servicios/useAuth';
import { useToast } from '../servicios/useUI';
import { descargarPdf as descargarArchivoPdf } from '../servicios/descargarArchivo';

const { sesion } = useAuth();
const toast = useToast();
const esAdmin = computed(() => sesion.usuario?.rol === 'admin');

const tipo = ref('ventas');
const datos = ref(null);
const fi = ref('');
const ff = ref('');

const tipos = computed(() => {
  const base = [
    { k: 'ventas', n: 'Ventas' }, { k: 'encomiendas', n: 'Encomiendas' },
    { k: 'cotizaciones', n: 'Cotizaciones' }, { k: 'facturas', n: 'Facturas' },
  ];
  if (esAdmin.value) { base.push({ k: 'inventario', n: 'Inventario' }); }
  return base;
});

function fmt(f) {
  if (!f) return '—';
  const d = new Date(f);
  return isNaN(d) ? f : d.toLocaleDateString();
}

async function cargar() {
  try {
    const params = fi.value ? { fecha_inicio: fi.value, fecha_fin: ff.value || fi.value } : { fecha_inicio: '*' };
    datos.value = await api('/reportes/' + tipo.value, { params });
  } catch (e) { toast.error(e.message); datos.value = null; }
}
onMounted(cargar);
function cambiar(t) { tipo.value = t; datos.value = null; cargar(); }

async function descargarPdf() {
  try {
    const params = new URLSearchParams(fi.value ? { fecha_inicio: fi.value, fecha_fin: ff.value || fi.value } : { fecha_inicio: '*' });
    await descargarArchivoPdf(`/reportes/${tipo.value}/pdf?` + params, `reporte-${tipo.value}.pdf`);
  } catch (e) { toast.error(e.message); }
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Reportes</h1>
    <p class="subtitulo">Resúmenes y exportación en PDF del negocio y de acceso</p>

    <div class="card">
      <div class="fila-acciones">
        <button v-for="t in tipos" :key="t.k" class="btn" :class="{ secundario: tipo !== t.k }" @click="cambiar(t.k)">{{ t.n }}</button>
      </div>
      <div class="fila-acciones" style="margin-top:12px; align-items:flex-end">
        <div><label>Desde</label><input class="input" type="date" v-model="fi" /></div>
        <div><label>Hasta</label><input class="input" type="date" v-model="ff" /></div>
        <button class="btn" @click="cargar">Aplicar</button>
        <button class="btn secundario" @click="descargarPdf">Exportar PDF</button>
      </div>
    </div>

    <div class="card" v-if="datos">
      <!-- Métricas según tipo -->
      <div v-if="tipo==='ventas'" class="grid grid-3">
        <div class="metric"><div class="valor">{{ datos.total_ventas }}</div><div class="etiqueta">Ventas</div></div>
        <div class="metric"><div class="valor">Bs {{ datos.monto_total }}</div><div class="etiqueta">Monto total</div></div>
        <div class="metric"><div class="valor">Bs {{ datos.total_cobrado }}</div><div class="etiqueta">Cobrado</div></div>
        <div class="metric"><div class="valor">{{ datos.pagadas }}</div><div class="etiqueta">Pagadas</div></div>
        <div class="metric"><div class="valor">{{ datos.parciales }}</div><div class="etiqueta">Parciales</div></div>
        <div class="metric"><div class="valor">{{ datos.pendientes }}</div><div class="etiqueta">Pendientes</div></div>
      </div>
      <div v-else-if="tipo==='cotizaciones'" class="grid grid-3">
        <div class="metric"><div class="valor">{{ datos.total }}</div><div class="etiqueta">Cotizaciones</div></div>
        <div class="metric"><div class="valor">{{ datos.tasa_conversion }}%</div><div class="etiqueta">Tasa conversión</div></div>
      </div>
      <div v-else-if="tipo==='encomiendas'" class="grid grid-3">
        <div class="metric"><div class="valor">{{ datos.total }}</div><div class="etiqueta">Encomiendas</div></div>
        <div class="metric" v-for="(n,e) in datos.por_estado" :key="e"><div class="valor">{{ n }}</div><div class="etiqueta">{{ e }}</div></div>
      </div>
      <div v-else-if="tipo==='facturas'" class="grid grid-3">
        <div class="metric"><div class="valor">{{ datos.total }}</div><div class="etiqueta">Facturas</div></div>
        <div class="metric"><div class="valor">Bs {{ datos.monto_total }}</div><div class="etiqueta">Monto total</div></div>
      </div>
      <div v-else-if="tipo==='inventario'" class="grid grid-3">
        <div class="metric"><div class="valor">{{ datos.total_productos }}</div><div class="etiqueta">Productos</div></div>
        <div class="metric"><div class="valor">{{ datos.unidades_totales }}</div><div class="etiqueta">Unidades</div></div>
      </div>

      <!-- ===== Tablas de detalle según tipo ===== -->

      <!-- VENTAS -->
      <template v-if="tipo==='ventas'">
        <h4 style="margin:18px 0 8px">Por tipo de pago</h4>
        <table><thead><tr><th>Tipo de pago</th><th>Cantidad</th><th>Monto</th></tr></thead>
          <tbody>
            <tr v-for="(info,tp) in datos.por_tipo_pago" :key="tp"><td>{{ tp || 'N/D' }}</td><td>{{ info.cantidad }}</td><td>Bs {{ info.monto }}</td></tr>
            <tr v-if="!datos.por_tipo_pago || !Object.keys(datos.por_tipo_pago).length"><td colspan="3" style="color:var(--color-texto-suave)">Sin datos</td></tr>
          </tbody>
        </table>
        <h4 style="margin:18px 0 8px">Detalle de ventas</h4>
        <table><thead><tr><th>Código</th><th>Fecha</th><th>Tipo pago</th><th>Estado</th><th>Total</th></tr></thead>
          <tbody>
            <tr v-for="v in datos.ultimas" :key="v.id"><td>{{ v.codigo }}</td><td>{{ fmt(v.fecha_venta) }}</td><td>{{ v.tipo_pago }}</td><td><span class="badge info">{{ v.estado }}</span></td><td>Bs {{ v.total_final }}</td></tr>
            <tr v-if="!datos.ultimas || !datos.ultimas.length"><td colspan="5" style="color:var(--color-texto-suave)">Sin ventas en el periodo</td></tr>
          </tbody>
        </table>
      </template>

      <!-- ENCOMIENDAS -->
      <template v-else-if="tipo==='encomiendas'">
        <h4 style="margin:18px 0 8px">Detalle de encomiendas</h4>
        <table><thead><tr><th>Guía</th><th>Destinatario</th><th>Ruta</th><th>Estado</th><th>Registro</th></tr></thead>
          <tbody>
            <tr v-for="e in datos.ultimas" :key="e.id"><td>{{ e.guia_rastreo }}</td><td>{{ e.destinatario }}</td><td>{{ e.origen }} → {{ e.destino }}</td><td><span class="badge info">{{ e.estado }}</span></td><td>{{ fmt(e.fecha_registro) }}</td></tr>
            <tr v-if="!datos.ultimas || !datos.ultimas.length"><td colspan="5" style="color:var(--color-texto-suave)">Sin encomiendas en el periodo</td></tr>
          </tbody>
        </table>
      </template>

      <!-- COTIZACIONES -->
      <template v-else-if="tipo==='cotizaciones'">
        <h4 style="margin:18px 0 8px">Por estado</h4>
        <table><thead><tr><th>Estado</th><th>Cantidad</th><th>Monto estimado</th></tr></thead>
          <tbody>
            <tr v-for="(info,es) in datos.por_estado" :key="es"><td>{{ es }}</td><td>{{ info.cantidad }}</td><td>Bs {{ info.monto }}</td></tr>
            <tr v-if="!datos.por_estado || !Object.keys(datos.por_estado).length"><td colspan="3" style="color:var(--color-texto-suave)">Sin datos</td></tr>
          </tbody>
        </table>
        <h4 style="margin:18px 0 8px">Detalle de cotizaciones</h4>
        <table><thead><tr><th>#</th><th>Destinatario</th><th>Ruta</th><th>Estado</th><th>Total est.</th><th>Emisión</th></tr></thead>
          <tbody>
            <tr v-for="c in datos.ultimas" :key="c.id"><td>{{ c.id }}</td><td>{{ c.destinatario }}</td><td>{{ c.origen }} → {{ c.destino }}</td><td><span class="badge info">{{ c.estado }}</span></td><td>Bs {{ c.total_estimado }}</td><td>{{ fmt(c.fecha_emision) }}</td></tr>
            <tr v-if="!datos.ultimas || !datos.ultimas.length"><td colspan="6" style="color:var(--color-texto-suave)">Sin cotizaciones en el periodo</td></tr>
          </tbody>
        </table>
      </template>

      <!-- FACTURAS -->
      <template v-else-if="tipo==='facturas'">
        <h4 style="margin:18px 0 8px">Por método de pago</h4>
        <table><thead><tr><th>Método</th><th>Cantidad</th><th>Monto</th></tr></thead>
          <tbody>
            <tr v-for="(info,mt) in datos.por_metodo" :key="mt"><td>{{ mt || 'N/D' }}</td><td>{{ info.cantidad }}</td><td>Bs {{ info.monto }}</td></tr>
            <tr v-if="!datos.por_metodo || !Object.keys(datos.por_metodo).length"><td colspan="3" style="color:var(--color-texto-suave)">Sin datos</td></tr>
          </tbody>
        </table>
        <h4 style="margin:18px 0 8px">Detalle de facturas</h4>
        <table><thead><tr><th>N.º factura</th><th>Método</th><th>Estado</th><th>Total</th><th>Emisión</th></tr></thead>
          <tbody>
            <tr v-for="f in datos.ultimas" :key="f.id"><td>{{ f.numero_factura }}</td><td>{{ f.metodo_pago }}</td><td><span class="badge info">{{ f.estado }}</span></td><td>Bs {{ f.total }}</td><td>{{ fmt(f.fecha_emision) }}</td></tr>
            <tr v-if="!datos.ultimas || !datos.ultimas.length"><td colspan="5" style="color:var(--color-texto-suave)">Sin facturas en el periodo</td></tr>
          </tbody>
        </table>
      </template>

      <!-- INVENTARIO -->
      <template v-else-if="tipo==='inventario'">
        <h4 style="margin:18px 0 8px">Stock por almacén</h4>
        <table><thead><tr><th>Producto</th><th>Almacén</th><th>Cantidad</th><th>Stock mínimo</th><th>Nivel</th></tr></thead>
          <tbody>
            <tr v-for="(s,i) in datos.stock" :key="i"><td>{{ s.producto }}</td><td>{{ s.almacen }}</td><td>{{ s.cantidad }}</td><td>{{ s.stock_minimo }}</td><td><span class="badge" :class="s.nivel==='BAJO'?'error':'exito'">{{ s.nivel }}</span></td></tr>
            <tr v-if="!datos.stock || !datos.stock.length"><td colspan="5" style="color:var(--color-texto-suave)">Sin registros</td></tr>
          </tbody>
        </table>
      </template>
    </div>
  </AppLayout>
</template>
