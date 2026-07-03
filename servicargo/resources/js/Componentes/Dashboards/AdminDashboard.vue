<script setup>
import { ref, onMounted, computed } from 'vue';
import KpiCard from '../KpiCard.vue';
import { api } from '../../servicios/useApi';
import { withBase } from '../../servicios/useBase';
import { useToast } from '../../servicios/useUI';

const toast = useToast();
const datos = ref(null);
const cargando = ref(true);

function moneda(n) {
  return '$ ' + Number(n || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const kpis = computed(() => datos.value?.kpis || {});
const porMetodo = computed(() => datos.value?.ventas_por_metodo || []);
const paginas = computed(() => datos.value?.paginas_mas_visitadas || []);
const pagosHora = computed(() => datos.value?.pagos_por_hora || []);
const maxHora = computed(() => Math.max(1, ...pagosHora.value.map((p) => Number(p.cantidad))));
const maxMetodo = computed(() => Math.max(1, ...porMetodo.value.map((m) => Number(m.monto))));

onMounted(async () => {
  try { datos.value = await api('/estadisticas'); }
  catch (e) { toast.error('No se pudieron cargar las estadísticas. ' + e.message); }
  finally { cargando.value = false; }
});
</script>

<template>
  <div>
    <!-- KPIs del negocio -->
    <div class="grid grid-2" style="margin-bottom:22px">
      <KpiCard etiqueta="Ventas registradas" :valor="kpis.ventas_total ?? 0" :enlace="withBase('/reportes')" enlace-texto="Ver reporte" />
      <KpiCard etiqueta="Ingresos cobrados" :valor="moneda(kpis.ingresos)" :enlace="withBase('/reportes')" enlace-texto="Ver reporte" />
      <KpiCard etiqueta="Encomiendas activas" :valor="kpis.encomiendas_activas ?? 0" :enlace="withBase('/encomiendas')" enlace-texto="Ver encomiendas" />
      <KpiCard etiqueta="Conversión de cotizaciones" :valor="(kpis.tasa_conversion ?? 0) + '%'"
               :variacion="Math.round(kpis.tasa_conversion ?? 0)" :enlace="withBase('/reportes')" enlace-texto="Ver detalle" />
    </div>

    <div class="grid grid-2">
      <!-- Facturación por método de pago -->
      <div class="card">
        <h3>Facturación por método de pago</h3>
        <p v-if="!porMetodo.length" class="subtitulo" style="margin:0">Aún no hay facturación registrada.</p>
        <div v-for="m in porMetodo" :key="m.metodo_pago" style="margin-bottom:12px">
          <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px">
            <strong>{{ m.metodo_pago }}</strong><span>{{ moneda(m.monto) }} · {{ m.cantidad }}</span>
          </div>
          <div style="height:8px; background:var(--color-fondo); border-radius:999px; overflow:hidden">
            <div :style="{ width: (m.monto / maxMetodo * 100) + '%', height:'100%', background:'var(--color-primario)' }"></div>
          </div>
        </div>
      </div>

      <!-- Páginas más visitadas -->
      <div class="card">
        <h3>Páginas más visitadas</h3>
        <p v-if="!paginas.length" class="subtitulo" style="margin:0">Sin visitas registradas.</p>
        <table v-else>
          <tbody>
            <tr v-for="p in paginas.slice(0,6)" :key="p.pagina">
              <td>{{ p.pagina }}</td>
              <td style="text-align:right"><span class="badge info">{{ p.contador }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagos por hora del día (horas pico) -->
    <div class="card">
      <h3>Pagos por hora del día</h3>
      <p class="subtitulo">Ayuda a identificar las horas pico en que los clientes pagan.</p>
      <p v-if="!pagosHora.length" style="margin:0; color:var(--color-texto-suave)">Aún no hay pagos confirmados.</p>
      <div v-else style="display:flex; align-items:flex-end; gap:6px; height:140px">
        <div v-for="p in pagosHora" :key="p.hora" style="flex:1; display:flex; flex-direction:column; align-items:center; gap:4px">
          <div :style="{ height: (p.cantidad / maxHora * 110) + 'px', width:'100%', background:'var(--color-acento)', borderRadius:'6px 6px 0 0' }"
               :title="p.cantidad + ' pagos'"></div>
          <span style="font-size:10px; color:var(--color-texto-suave)">{{ p.hora }}h</span>
        </div>
      </div>
    </div>
  </div>
</template>
