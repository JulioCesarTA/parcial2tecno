<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import ProductosCotizacion from '../Componentes/ProductosCotizacion.vue';
import { api } from '../servicios/useApi';
import { useToast } from '../servicios/useUI';
import { withBase } from '../servicios/useBase';

const toast = useToast();
const lista = ref([]);
const productos = ref([]);
const modal = ref(false);
const detalle = ref(null);
const impuestos = ref(0);

async function cargar() {
  try { lista.value = await api('/cotizaciones/solicitudes'); }
  catch (e) { toast.error(e.message); }
}
onMounted(async () => {
  await cargar();
  productos.value = await api('/productos').catch(() => []);
});

async function revisar(s) {
  detalle.value = await api('/cotizaciones/' + s.id);
  impuestos.value = detalle.value.impuestos || 0;
  modal.value = true;
}

function onProductosActualizados(cot) {
  detalle.value = cot;
  cargar(); // si ya tiene productos, sale de la bandeja de pendientes
}

async function guardarImpuestos() {
  try {
    const d = await api('/cotizaciones/' + detalle.value.id, { method: 'PUT', body: { impuestos: impuestos.value } });
    detalle.value = { ...detalle.value, ...d.cotizacion };
    toast.exito('Impuestos actualizados.');
  } catch (e) { toast.error(e.message); }
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Solicitudes</h1>
    <p class="subtitulo">Cotizaciones que pidieron los clientes por su cuenta, todavía sin revisar</p>

    <div class="card">
      <table>
        <thead><tr><th>Cliente</th><th>Ruta</th><th>Contenido</th><th>Envío</th><th>Fotos</th><th>Pedida</th><th></th></tr></thead>
        <tbody>
          <tr v-for="s in lista" :key="s.id">
            <td>{{ s.cliente?.nombre }} {{ s.cliente?.apellido }}</td>
            <td>{{ s.origen }} → {{ s.destino }}</td>
            <td>{{ s.contenido }}</td>
            <td>{{ s.tipo_envio }} · {{ s.peso_kg }} kg · {{ s.volumen_m3 }} m³</td>
            <td>{{ s.fotos?.length || 0 }}</td>
            <td>{{ new Date(s.fecha_emision).toLocaleDateString() }}</td>
            <td><button class="btn chico" @click="revisar(s)">Revisar</button></td>
          </tr>
          <tr v-if="!lista.length"><td colspan="7" style="color:var(--color-texto-suave)">No hay solicitudes pendientes de revisión</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="modal && detalle" class="modal-fondo" @click.self="modal=false">
      <div class="modal" style="max-width:700px">
        <h3 style="margin-top:0">Solicitud de {{ detalle.cliente?.nombre }} {{ detalle.cliente?.apellido }}</h3>
        <p><strong>{{ detalle.remitente }}</strong> → <strong>{{ detalle.destinatario }}</strong></p>
        <p>{{ detalle.origen }} → {{ detalle.destino }} · {{ detalle.tipo_envio }} · {{ detalle.peso_kg }} kg · {{ detalle.volumen_m3 }} m³</p>
        <p>Contenido: {{ detalle.contenido }}</p>
        <p style="color:var(--color-texto-suave); font-size:13px">Válida por {{ detalle.validez_dias }} día(s) desde que se pidió.</p>

        <template v-if="detalle.fotos?.length">
          <label>Fotos enviadas por el cliente</label>
          <div class="fila-acciones" style="flex-wrap:wrap; margin-bottom:10px">
            <a v-for="f in detalle.fotos" :key="f.id" :href="withBase('/' + f.ruta)" target="_blank">
              <img :src="withBase('/' + f.ruta)" style="width:110px; height:110px; object-fit:cover; border-radius:8px; border:1px solid var(--color-borde)" />
            </a>
          </div>
        </template>
        <p v-else style="color:var(--color-texto-suave); font-size:13px">El cliente no adjuntó fotos.</p>

        <ProductosCotizacion
          :cotizacion="detalle"
          :productos="productos"
          :puede-editar="true"
          @actualizado="onProductosActualizados"
        />

        <div class="fila-acciones" style="align-items:flex-end; margin-top:12px">
          <div style="max-width:160px">
            <label>Impuestos (Bs)</label>
            <input class="input" type="number" step="0.01" v-model.number="impuestos" />
          </div>
          <button class="btn chico secundario" @click="guardarImpuestos">Guardar impuestos</button>
        </div>

        <p style="text-align:right; margin-top:10px">Subtotal: Bs {{ detalle.subtotal }} · Impuestos: Bs {{ detalle.impuestos }} · <strong>Total: Bs {{ detalle.total_estimado }}</strong></p>
        <p v-if="!detalle.detalles?.length" style="color:var(--color-texto-suave); font-size:13px">
          Agregá al menos un producto para que el cliente pueda aprobar la cotización.
        </p>
        <div class="fila-acciones" style="justify-content:flex-end"><button class="btn secundario" @click="modal=false">Cerrar</button></div>
      </div>
    </div>
  </AppLayout>
</template>
