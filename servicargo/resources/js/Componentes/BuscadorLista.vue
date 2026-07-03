<script setup>
import { ref } from 'vue';
import { Search } from 'lucide-vue-next';

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Buscar…' },
  // [{ id, titulo, subtitulo }]
  sugerencias: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'elegir']);

const enfocado = ref(false);

function elegir(s) {
  emit('elegir', s);
  enfocado.value = false;
}
</script>

<template>
  <div class="buscador-lista">
    <Search :size="15" class="buscador-lista-icono" />
    <input
      class="input"
      style="padding-left:32px"
      :value="modelValue"
      @input="emit('update:modelValue', $event.target.value)"
      @focus="enfocado = true"
      @blur="() => { enfocado = false }"
      :placeholder="placeholder"
    />
    <div v-if="enfocado && modelValue.trim().length > 0 && sugerencias.length" class="resultados-busqueda">
      <div
        v-for="s in sugerencias"
        :key="s.id"
        class="buscador-lista-sugerencia"
        @mousedown.prevent="elegir(s)"
      >
        <strong>{{ s.titulo }}</strong>
        <span v-if="s.subtitulo" style="color:var(--color-texto-suave)"> — {{ s.subtitulo }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.buscador-lista { position: relative; flex: 1; min-width: 220px; max-width: 380px; }
.buscador-lista-icono {
  position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
  color: var(--color-texto-suave); pointer-events: none;
}
.buscador-lista-sugerencia { cursor: pointer; }
.buscador-lista-sugerencia:hover { background: var(--color-fondo); }
</style>
