<script setup>
import { ref, computed, onMounted } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useToast } from '../servicios/useUI';

const toast = useToast();
const panel = ref(null);
const cargando = ref(false);

const kpis = computed(() => panel.value?.kpis || null);

// Configuración común para todos los gráficos.
const baseChart = {
  toolbar: { show: false },
  fontFamily: 'inherit',
  animations: { enabled: true, easing: 'easeinout', speed: 600 },
};
const noData = { text: 'Sin datos', style: { color: '#94a3b8' } };

/* 1) Ventas por estado de cobro — dona */
const ventasEstado = computed(() => {
  const filas = panel.value?.ventas_por_estado || [];
  const colores = { PAGADA: '#22c55e', PARCIAL: '#f59e0b', PENDIENTE: '#ef4444' };
  return {
    series: filas.map((f) => Number(f.cantidad)),
    options: {
      chart: { type: 'donut', ...baseChart },
      labels: filas.map((f) => f.estado),
      colors: filas.map((f) => colores[f.estado] || '#3b82f6'),
      legend: { position: 'bottom' },
      stroke: { width: 2 },
      plotOptions: { pie: { donut: { size: '62%' } } },
      dataLabels: { enabled: true, formatter: (v) => Math.round(v) + '%' },
      noData,
    },
  };
});

/* 2) Actividad últimos 30 días — área */
const actividad = computed(() => {
  const d = panel.value?.actividad_30dias || { cotizaciones: [], encomiendas: [] };
  const mapC = {}, mapE = {};
  (d.cotizaciones || []).forEach((x) => { mapC[x.dia] = Number(x.total); });
  (d.encomiendas || []).forEach((x) => { mapE[x.dia] = Number(x.total); });
  const dias = [...new Set([...Object.keys(mapC), ...Object.keys(mapE)])].sort();
  return {
    series: [
      { name: 'Cotizaciones', data: dias.map((dia) => mapC[dia] || 0) },
      { name: 'Encomiendas', data: dias.map((dia) => mapE[dia] || 0) },
    ],
    options: {
      chart: { type: 'area', ...baseChart },
      colors: ['#3b82f6', '#ef4444'],
      xaxis: { categories: dias.map((x) => x.slice(5)) },
      stroke: { curve: 'smooth', width: 2 },
      fill: { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0.05 } },
      dataLabels: { enabled: false },
      legend: { position: 'top' },
      noData,
    },
  };
});

/* 3) Facturación por método de pago — dona */
const metodoPago = computed(() => {
  const filas = panel.value?.ventas_por_metodo || [];
  const colores = { EFECTIVO: '#10b981', QR: '#6366f1', TARJETA: '#f59e0b', TRANSFERENCIA: '#0ea5e9' };
  return {
    series: filas.map((f) => Number(f.monto)),
    options: {
      chart: { type: 'donut', ...baseChart },
      labels: filas.map((f) => f.metodo_pago || 'N/D'),
      colors: filas.map((f) => colores[f.metodo_pago] || '#3b82f6'),
      legend: { position: 'bottom' },
      stroke: { width: 2 },
      plotOptions: { pie: { donut: { size: '62%' } } },
      dataLabels: { enabled: true, formatter: (v) => Math.round(v) + '%' },
      tooltip: { y: { formatter: (v) => 'Bs ' + Number(v).toFixed(2) } },
      noData,
    },
  };
});

/* 4) Páginas más visitadas — barras */
const visitas = computed(() => {
  const filas = panel.value?.paginas_mas_visitadas || [];
  return {
    series: [{ name: 'Visitas', data: filas.map((f) => Number(f.contador)) }],
    options: {
      chart: { type: 'bar', ...baseChart },
      colors: ['#10b981'],
      xaxis: { categories: filas.map((f) => f.pagina) },
      plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
      dataLabels: { enabled: true },
      legend: { show: false },
      noData,
    },
  };
});

async function cargar() {
  cargando.value = true;
  try {
    panel.value = await api('/estadisticas');
  } catch (e) {
    toast.error(e.message);
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);
</script>

<template>
  <AppLayout>
    <div class="fila-acciones" style="justify-content:space-between; align-items:center">
      <div>
        <h1 class="titulo-pagina">Estadísticas</h1>
        <p class="subtitulo">Indicadores clave del negocio</p>
      </div>
      <button class="btn secundario" :disabled="cargando" @click="cargar">
        {{ cargando ? 'Actualizando…' : 'Actualizar' }}
      </button>
    </div>

    <!-- Indicadores -->
    <div class="card" v-if="kpis">
      <div class="grid grid-3">
        <div class="metric"><div class="valor">{{ kpis.ventas_total }}</div><div class="etiqueta">Ventas</div></div>
        <div class="metric"><div class="valor">Bs {{ kpis.ingresos }}</div><div class="etiqueta">Ingresos cobrados</div></div>
        <div class="metric"><div class="valor">{{ kpis.encomiendas_activas }}</div><div class="etiqueta">Encomiendas activas</div></div>
        <div class="metric"><div class="valor">{{ kpis.cotizaciones_pendientes }}</div><div class="etiqueta">Cotizaciones pendientes</div></div>
        <div class="metric"><div class="valor">{{ kpis.tasa_conversion }}%</div><div class="etiqueta">Tasa de conversión</div></div>
      </div>
    </div>

    <!-- Gráficos: 2 por fila (mín. 440px); en pantallas chicas se apilan -->
    <div class="graficos-grid" v-if="panel">
      <div class="card">
        <h4 style="margin:0 0 8px">Ventas por estado de cobro</h4>
        <apexchart type="donut" height="340" :options="ventasEstado.options" :series="ventasEstado.series" />
      </div>
      <div class="card">
        <h4 style="margin:0 0 8px">Actividad de los últimos 30 días</h4>
        <apexchart type="area" height="340" :options="actividad.options" :series="actividad.series" />
      </div>
      <div class="card">
        <h4 style="margin:0 0 8px">Facturación por método de pago</h4>
        <apexchart type="donut" height="340" :options="metodoPago.options" :series="metodoPago.series" />
      </div>
      <div class="card">
        <h4 style="margin:0 0 8px">Páginas más visitadas</h4>
        <apexchart type="bar" height="340" :options="visitas.options" :series="visitas.series" />
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.graficos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(440px, 1fr));
  gap: 16px;
  align-items: start;
}
@media (max-width: 640px) {
  .graficos-grid { grid-template-columns: 1fr; }
}
</style>
