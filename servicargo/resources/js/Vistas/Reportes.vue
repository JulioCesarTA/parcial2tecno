<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api, getToken } from '../servicios/useApi';
import { useAuth } from '../servicios/useAuth';
import { useToast } from '../servicios/useUI';
import { withBase } from '../servicios/useBase';

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
  if (esAdmin.value) { base.push({ k: 'inventario', n: 'Inventario' }, { k: 'acceso', n: 'Acceso (bitácora)' }); }
  return base;
});

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
    const res = await fetch(withBase(`/api/reportes/${tipo.value}/pdf?` + params), { headers: { Authorization: 'Bearer ' + getToken() } });
    if (!res.ok) throw new Error('No se pudo generar el PDF');
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = `reporte-${tipo.value}.pdf`; a.click();
    URL.revokeObjectURL(url);
  } catch (e) { toast.error(e.message); }
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Reportes y Estadísticas</h1>
    <p class="subtitulo">Análisis del negocio y de acceso</p>

    <div class="card">
      <div class="fila-acciones">
        <button v-for="t in tipos" :key="t.k" class="btn" :class="{ secundario: tipo !== t.k }" @click="cambiar(t.k)">{{ t.n }}</button>
      </div>
      <div class="fila-acciones" style="margin-top:12px; align-items:flex-end">
        <div><label>Desde</label><input class="input" type="date" v-model="fi" /></div>
        <div><label>Hasta</label><input class="input" type="date" v-model="ff" /></div>
        <button class="btn" @click="cargar">Aplicar</button>
        <button v-if="tipo!=='acceso'" class="btn secundario" @click="descargarPdf">Exportar PDF</button>
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
      <div v-else-if="tipo==='acceso'" class="grid grid-3">
        <div class="metric"><div class="valor">{{ datos.logins_ok }}</div><div class="etiqueta">Logins OK</div></div>
        <div class="metric"><div class="valor">{{ datos.logins_fallidos }}</div><div class="etiqueta">Logins fallidos</div></div>
      </div>

      <!-- Tablas detalle -->
      <div v-if="tipo==='acceso'" style="margin-top:16px">
        <h4>Recursos más accedidos</h4>
        <table><thead><tr><th>Recurso</th><th>Accesos</th></tr></thead>
          <tbody><tr v-for="r in datos.recursos_mas_accedidos" :key="r.recurso"><td>{{ r.recurso }}</td><td>{{ r.total }}</td></tr></tbody>
        </table>
      </div>
      <div v-if="tipo==='inventario'" style="margin-top:16px">
        <table><thead><tr><th>Producto</th><th>Almacén</th><th>Cantidad</th><th>Nivel</th></tr></thead>
          <tbody><tr v-for="(s,i) in datos.stock" :key="i"><td>{{ s.producto }}</td><td>{{ s.almacen }}</td><td>{{ s.cantidad }}</td><td><span class="badge" :class="s.nivel==='BAJO'?'error':'exito'">{{ s.nivel }}</span></td></tr></tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
