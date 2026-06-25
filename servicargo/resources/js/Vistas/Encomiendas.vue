<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useAuth } from '../servicios/useAuth';
import { useToast } from '../servicios/useUI';

const { sesion } = useAuth();
const toast = useToast();
const esCliente = computed(() => sesion.usuario?.rol === 'cliente');

const lista = ref([]);
const modal = ref(false);
const detalle = ref(null);
const nuevoEstado = ref('');
const obs = ref('');
const estados = ['REGISTRADA', 'EN_TRANSITO', 'EN_DISTRIBUCION', 'ENTREGADA', 'DEVUELTA'];

async function cargar() { lista.value = await api('/encomiendas'); }
onMounted(cargar);

async function ver(e) { detalle.value = await api('/encomiendas/' + e.id); nuevoEstado.value = detalle.value.estado; obs.value = ''; modal.value = true; }

async function cambiarEstado() {
  try {
    await api('/encomiendas/' + detalle.value.id, { method: 'PUT', body: { estado: nuevoEstado.value, observaciones: obs.value } });
    toast.exito('Estado actualizado');
    detalle.value = await api('/encomiendas/' + detalle.value.id);
    cargar();
  } catch (e) { toast.error(e.message); }
}

function badge(e) {
  return { REGISTRADA: 'info', EN_TRANSITO: 'aviso', EN_DISTRIBUCION: 'aviso', ENTREGADA: 'exito', DEVUELTA: 'error' }[e] || '';
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Encomiendas</h1>
    <p class="subtitulo">{{ esCliente ? 'Seguimiento de tus envíos' : 'Envíos y trazabilidad' }}</p>
    <div class="card">
      <table>
        <thead><tr><th>Guía</th><th>Cliente</th><th>Destino</th><th>Tipo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          <tr v-for="e in lista" :key="e.id">
            <td><strong>{{ e.guia_rastreo }}</strong></td>
            <td>{{ e.cliente?.nombre }}</td>
            <td>{{ e.origen }} → {{ e.destino }}</td>
            <td>{{ e.tipo_envio }}</td>
            <td><span class="badge" :class="badge(e.estado)">{{ e.estado }}</span></td>
            <td><button class="btn chico secundario" @click="ver(e)">{{ esCliente ? 'Seguimiento' : 'Gestionar' }}</button></td>
          </tr>
          <tr v-if="!lista.length"><td colspan="6" style="color:var(--color-texto-suave)">Sin encomiendas</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="modal && detalle" class="modal-fondo" @click.self="modal=false">
      <div class="modal" style="max-width:600px">
        <h3 style="margin-top:0">Guía {{ detalle.guia_rastreo }} <span class="badge" :class="badge(detalle.estado)">{{ detalle.estado }}</span></h3>
        <p>{{ detalle.remitente }} → {{ detalle.destinatario }}</p>
        <p>{{ detalle.origen }} → {{ detalle.destino }} · {{ detalle.tipo_envio }}</p>

        <h4>Historial de estados</h4>
        <table>
          <thead><tr><th>De</th><th>A</th><th>Fecha</th><th>Obs.</th></tr></thead>
          <tbody>
            <tr v-for="h in detalle.historial" :key="h.id">
              <td>{{ h.estado_anterior || '—' }}</td><td>{{ h.estado_nuevo }}</td>
              <td>{{ new Date(h.fecha_cambio).toLocaleString() }}</td><td>{{ h.observaciones }}</td>
            </tr>
          </tbody>
        </table>

        <div v-if="!esCliente" style="margin-top:16px; border-top:1px solid var(--color-borde); padding-top:12px">
          <label>Cambiar estado</label>
          <select class="input" v-model="nuevoEstado"><option v-for="s in estados" :key="s" :value="s">{{ s }}</option></select>
          <label>Observaciones</label><input class="input" v-model="obs" />
          <div class="fila-acciones" style="justify-content:flex-end; margin-top:12px">
            <button class="btn secundario" @click="modal=false">Cerrar</button>
            <button class="btn" @click="cambiarEstado">Actualizar estado</button>
          </div>
        </div>
        <div v-else class="fila-acciones" style="justify-content:flex-end; margin-top:12px">
          <button class="btn secundario" @click="modal=false">Cerrar</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
