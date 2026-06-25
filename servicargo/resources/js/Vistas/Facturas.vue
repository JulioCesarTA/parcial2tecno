<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';

const lista = ref([]);
onMounted(async () => { lista.value = await api('/facturas'); });
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Facturas</h1>
    <p class="subtitulo">Una factura por cada pago</p>
    <div class="card">
      <table>
        <thead><tr><th>Nº Factura</th><th>Venta</th><th>Método</th><th>Cuota</th><th>Total</th><th>Estado</th><th>Fecha</th></tr></thead>
        <tbody>
          <tr v-for="f in lista" :key="f.id">
            <td><strong>{{ f.numero_factura }}</strong></td>
            <td>{{ f.venta?.codigo }}</td>
            <td>{{ f.metodo_pago }}</td>
            <td>{{ f.numero_cuota || '—' }}</td>
            <td>Bs {{ f.total }}</td>
            <td><span class="badge exito">{{ f.estado }}</span></td>
            <td>{{ new Date(f.fecha_emision).toLocaleDateString() }}</td>
          </tr>
          <tr v-if="!lista.length"><td colspan="7" style="color:var(--color-texto-suave)">Sin facturas</td></tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
