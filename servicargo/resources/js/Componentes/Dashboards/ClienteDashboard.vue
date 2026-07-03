<script setup>
import { ref, onMounted, computed } from 'vue';
import KpiCard from '../KpiCard.vue';
import { api } from '../../servicios/useApi';
import { withBase } from '../../servicios/useBase';
import { useToast } from '../../servicios/useUI';

const toast = useToast();
const cotizaciones = ref([]);
const encomiendas = ref([]);
const facturas = ref([]);

function moneda(n) {
  return '$ ' + Number(n || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const porAprobar = computed(() => cotizaciones.value.filter((c) => c.estado === 'PENDIENTE'));
const enCamino = computed(() => encomiendas.value.filter((e) => e.estado !== 'ENTREGADO'));
const totalFacturado = computed(() => facturas.value.reduce((s, f) => s + Number(f.total || 0), 0));

onMounted(async () => {
  // Cargamos cada bloque por separado (si uno falla, los demás igual se muestran)
  // y avisamos una sola vez si algo no se pudo cargar.
  let fallo = null;
  try { cotizaciones.value = await api('/cotizaciones'); } catch (e) { fallo = fallo || e; }
  try { encomiendas.value = await api('/encomiendas'); } catch (e) { fallo = fallo || e; }
  try { facturas.value = await api('/facturas'); } catch (e) { fallo = fallo || e; }
  if (fallo) toast.error('No se pudieron cargar algunos datos del panel. ' + fallo.message);
});
</script>

<template>
  <div>
    <div class="grid grid-3" style="margin-bottom:22px">
      <KpiCard etiqueta="Cotizaciones por aprobar" :valor="porAprobar.length" :enlace="withBase('/cotizaciones')" enlace-texto="Revisar" />
      <KpiCard etiqueta="Encomiendas en camino" :valor="enCamino.length" :enlace="withBase('/encomiendas')" enlace-texto="Rastrear" />
      <KpiCard etiqueta="Total facturado" :valor="moneda(totalFacturado)" :enlace="withBase('/facturas')" enlace-texto="Ver facturas" />
    </div>

    <div class="grid grid-2">
      <!-- Cotizaciones esperando mi aprobación -->
      <div class="card">
        <h3>Esperando tu aprobación</h3>
        <p v-if="!porAprobar.length" class="subtitulo" style="margin:0">No tenés cotizaciones pendientes.</p>
        <table v-else>
          <thead><tr><th>#</th><th>Destino</th><th></th></tr></thead>
          <tbody>
            <tr v-for="c in porAprobar.slice(0,8)" :key="c.id">
              <td>{{ c.id }}</td>
              <td>{{ c.destino }}</td>
              <td style="text-align:right"><span class="badge aviso">Pendiente</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Rastreo de mis encomiendas -->
      <div class="card">
        <h3>Seguimiento de mis encomiendas</h3>
        <p v-if="!enCamino.length" class="subtitulo" style="margin:0">No tenés encomiendas en camino.</p>
        <table v-else>
          <thead><tr><th>Guía</th><th>Destino</th><th>Estado</th></tr></thead>
          <tbody>
            <tr v-for="e in enCamino.slice(0,8)" :key="e.id">
              <td>{{ e.guia_rastreo }}</td>
              <td>{{ e.destino }}</td>
              <td><span class="badge info">{{ e.estado }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
