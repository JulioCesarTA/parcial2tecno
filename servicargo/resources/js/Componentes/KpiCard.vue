<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';

const props = defineProps({
  etiqueta: { type: String, required: true },
  valor: { type: [String, Number], default: 0 },
  // variacion opcional: número (positivo = sube, negativo = baja)
  variacion: { type: Number, default: null },
  enlace: { type: String, default: null },
  enlaceTexto: { type: String, default: 'Ver más' },
});

const sube = computed(() => (props.variacion ?? 0) >= 0);
</script>

<template>
  <div class="kpi">
    <div class="kpi-top">
      <span class="kpi-etiqueta">{{ etiqueta }}</span>
      <span v-if="variacion !== null" class="kpi-var" :class="sube ? 'sube' : 'baja'">
        {{ sube ? '↑' : '↓' }} {{ Math.abs(variacion) }}%
      </span>
    </div>
    <div class="kpi-valor">{{ valor }}</div>
    <Link v-if="enlace" :href="enlace" class="kpi-link">
      {{ enlaceTexto }} <ArrowRight :size="15" />
    </Link>
  </div>
</template>
