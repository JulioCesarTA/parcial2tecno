<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import BuscadorLista from '../Componentes/BuscadorLista.vue';
import { api } from '../servicios/useApi';
import { coincide } from '../servicios/texto';
import { descargarPdf } from '../servicios/descargarArchivo';
import { useToast } from '../servicios/useUI';

const toast = useToast();
const lista = ref([]);
onMounted(async () => {
  try { lista.value = await api('/facturas'); }
  catch (e) { toast.error(e.message); }
});

async function descargarFacturaPdf(f) {
  try { await descargarPdf(`/facturas/${f.id}/pdf`, `factura-${f.numero_factura}.pdf`); }
  catch (e) { toast.error(e.message); }
}

/* ---------- Buscador + filtro ---------- */
const q = ref('');
const filtroMetodo = ref('');

function textoBusqueda(f) {
  return `${f.numero_factura} ${f.venta?.codigo || ''}`;
}
const listaFiltrada = computed(() => lista.value.filter((f) => (
  (!filtroMetodo.value || f.metodo_pago === filtroMetodo.value) && coincide(textoBusqueda(f), q.value)
)));
const sugerencias = computed(() => {
  if (!q.value.trim()) return [];
  return lista.value
    .filter((f) => coincide(textoBusqueda(f), q.value))
    .slice(0, 6)
    .map((f) => ({ id: f.id, titulo: f.numero_factura, subtitulo: `Venta ${f.venta?.codigo || ''} · Bs ${f.total}` }));
});
function elegirSugerencia(s) { q.value = s.titulo; }
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Facturas</h1>
    <p class="subtitulo">Una factura por cada pago</p>
    <div class="card">
      <div class="fila-acciones" style="margin-bottom:14px; flex-wrap:wrap">
        <BuscadorLista
          v-model="q"
          placeholder="Buscar por Nº factura o venta…"
          :sugerencias="sugerencias"
          @elegir="elegirSugerencia"
        />
        <select class="input" style="max-width:150px" v-model="filtroMetodo">
          <option value="">Efectivo y QR</option>
          <option value="EFECTIVO">Efectivo</option>
          <option value="QR">QR</option>
        </select>
      </div>
      <table>
        <thead><tr><th>Nº Factura</th><th>Venta</th><th>Método</th><th>Cuota</th><th>Total</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
        <tbody>
          <tr v-for="f in listaFiltrada" :key="f.id">
            <td><strong>{{ f.numero_factura }}</strong></td>
            <td>{{ f.venta?.codigo }}</td>
            <td>{{ f.metodo_pago }}</td>
            <td>{{ f.numero_cuota || '—' }}</td>
            <td>Bs {{ f.total }}</td>
            <td><span class="badge exito">{{ f.estado }}</span></td>
            <td>{{ new Date(f.fecha_emision).toLocaleDateString() }}</td>
            <td><button class="btn chico secundario" @click="descargarFacturaPdf(f)">PDF</button></td>
          </tr>
          <tr v-if="!listaFiltrada.length"><td colspan="8" style="color:var(--color-texto-suave)">{{ lista.length ? 'Sin resultados para ese filtro' : 'Sin facturas' }}</td></tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
