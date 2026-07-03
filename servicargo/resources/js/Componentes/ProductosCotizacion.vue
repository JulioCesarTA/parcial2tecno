<script setup>
import { ref, computed } from 'vue';
import { api } from '../servicios/useApi';
import { useToast, useConfirm } from '../servicios/useUI';

const props = defineProps({
  cotizacion: { type: Object, required: true },
  productos: { type: Array, default: () => [] },
  puedeEditar: { type: Boolean, default: false },
});
const emit = defineEmits(['actualizado']);

const toast = useToast();
const { confirmar } = useConfirm();

const nuevoProductoId = ref('');
const nuevaCantidad = ref(1);
const cantidadesEdit = ref({});

const editable = computed(() => props.puedeEditar && props.cotizacion.estado === 'PENDIENTE');

async function agregar() {
  if (!nuevoProductoId.value) { toast.error('Elegí un producto.'); return; }
  try {
    const d = await api(`/cotizaciones/${props.cotizacion.id}/productos`, {
      method: 'POST', body: { producto_id: nuevoProductoId.value, cantidad: nuevaCantidad.value },
    });
    toast.exito('Producto agregado.');
    nuevoProductoId.value = ''; nuevaCantidad.value = 1;
    emit('actualizado', d.cotizacion);
  } catch (e) { toast.error(e.message); }
}

async function guardarCantidad(d) {
  const cantidad = cantidadesEdit.value[d.producto_id] ?? d.cantidad;
  try {
    const r = await api(`/cotizaciones/${props.cotizacion.id}/productos/${d.producto_id}`, {
      method: 'PUT', body: { cantidad },
    });
    toast.exito('Cantidad actualizada.');
    emit('actualizado', r.cotizacion);
  } catch (e) { toast.error(e.message); }
}

async function quitar(d) {
  if (!(await confirmar({ mensaje: `¿Quitar "${d.producto?.nombre}" de la cotización?` }))) return;
  try {
    const r = await api(`/cotizaciones/${props.cotizacion.id}/productos/${d.producto_id}`, { method: 'DELETE' });
    toast.exito('Producto quitado.');
    emit('actualizado', r.cotizacion);
  } catch (e) { toast.error(e.message); }
}
</script>

<template>
  <div>
    <table>
      <thead><tr><th>Producto</th><th>Cantidad</th><th class="num">P. Unit.</th><th class="num">Subtotal</th><th v-if="editable"></th></tr></thead>
      <tbody>
        <tr v-for="d in cotizacion.detalles" :key="d.producto_id">
          <td>{{ d.producto?.nombre }}</td>
          <td>
            <input
              v-if="editable" class="input" type="number" min="1" style="max-width:80px"
              :value="cantidadesEdit[d.producto_id] ?? d.cantidad"
              @input="cantidadesEdit[d.producto_id] = Number($event.target.value)"
            />
            <template v-else>{{ d.cantidad }}</template>
          </td>
          <td class="num">Bs {{ d.precio_unitario }}</td>
          <td class="num">Bs {{ d.subtotal }}</td>
          <td v-if="editable" class="fila-acciones">
            <button class="btn chico secundario" @click="guardarCantidad(d)">Guardar</button>
            <button class="btn chico peligro" @click="quitar(d)">Quitar</button>
          </td>
        </tr>
        <tr v-if="!cotizacion.detalles?.length"><td colspan="5" style="color:var(--color-texto-suave)">Todavía no hay productos cargados</td></tr>
      </tbody>
    </table>

    <div v-if="editable" class="fila-acciones" style="margin-top:12px; align-items:flex-end; flex-wrap:wrap">
      <div style="flex:1; min-width:200px">
        <label>Agregar producto</label>
        <select class="input" v-model="nuevoProductoId">
          <option value="">—</option>
          <option v-for="p in productos" :key="p.id" :value="p.id">{{ p.nombre }} (Bs {{ p.precio_unitario }})</option>
        </select>
      </div>
      <div style="max-width:110px">
        <label>Cantidad</label>
        <input class="input" type="number" min="1" v-model.number="nuevaCantidad" />
      </div>
      <button class="btn chico" @click="agregar">+ Agregar</button>
    </div>
  </div>
</template>
