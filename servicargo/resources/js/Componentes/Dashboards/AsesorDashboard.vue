<script setup>
import { ref, onMounted, computed } from 'vue';
import KpiCard from '../KpiCard.vue';
import { api } from '../../servicios/useApi';
import { withBase } from '../../servicios/useBase';
import { useToast } from '../../servicios/useUI';

const toast = useToast();
const cotizaciones = ref([]);
const encomiendas = ref([]);
const ventas = ref([]);

const cotPendientes = computed(() => cotizaciones.value.filter((c) => c.estado === 'PENDIENTE'));
const cotAprobadas = computed(() => cotizaciones.value.filter((c) => c.estado === 'APROBADA'));
const encActivas = computed(() => encomiendas.value.filter((e) => e.estado !== 'ENTREGADO'));

onMounted(async () => {
  // Cargamos cada bloque por separado (si uno falla, los demás igual se muestran)
  // y avisamos una sola vez si algo no se pudo cargar.
  let fallo = null;
  try { cotizaciones.value = await api('/cotizaciones'); } catch (e) { fallo = fallo || e; }
  try { encomiendas.value = await api('/encomiendas'); } catch (e) { fallo = fallo || e; }
  try { ventas.value = await api('/ventas'); } catch (e) { fallo = fallo || e; }
  if (fallo) toast.error('No se pudieron cargar algunos datos del panel. ' + fallo.message);
});
</script>

<template>
  <div>
    <div class="grid grid-3" style="margin-bottom:22px">
      <KpiCard etiqueta="Cotizaciones pendientes" :valor="cotPendientes.length" :enlace="withBase('/cotizaciones')" enlace-texto="Gestionar" />
      <KpiCard etiqueta="Aprobadas (listas p/ encomienda)" :valor="cotAprobadas.length" :enlace="withBase('/encomiendas')" enlace-texto="Registrar encomienda" />
      <KpiCard etiqueta="Encomiendas activas" :valor="encActivas.length" :enlace="withBase('/encomiendas')" enlace-texto="Ver seguimiento" />
    </div>

    <div class="grid grid-2">
      <!-- Cotizaciones aprobadas esperando encomienda -->
      <div class="card">
        <h3>Cotizaciones aprobadas por atender</h3>
        <p v-if="!cotAprobadas.length" class="subtitulo" style="margin:0">No hay cotizaciones aprobadas pendientes.</p>
        <table v-else>
          <thead><tr><th>#</th><th>Cliente</th><th>Destino</th></tr></thead>
          <tbody>
            <tr v-for="c in cotAprobadas.slice(0,8)" :key="c.id">
              <td>{{ c.id }}</td>
              <td>{{ c.cliente?.nombre || '—' }}</td>
              <td>{{ c.destino }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Encomiendas en curso -->
      <div class="card">
        <h3>Encomiendas en curso</h3>
        <p v-if="!encActivas.length" class="subtitulo" style="margin:0">No hay encomiendas activas.</p>
        <table v-else>
          <thead><tr><th>Guía</th><th>Destino</th><th>Estado</th></tr></thead>
          <tbody>
            <tr v-for="e in encActivas.slice(0,8)" :key="e.id">
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
