<script setup>
import { useToast } from '../servicios/useUI';
const { toasts, quitar } = useToast();
</script>

<template>
  <div class="toast-host">
    <div v-for="t in toasts" :key="t.id" :class="['toast', t.tipo]" @click="quitar(t.id)">
      {{ t.mensaje }}
    </div>
  </div>
</template>
