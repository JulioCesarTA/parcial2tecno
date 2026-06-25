<script setup>
import { _confirmState } from '../servicios/useUI';
const estado = _confirmState();

function responder(valor) {
  if (estado.confirm) {
    estado.confirm.resolver(valor);
    estado.confirm = null;
  }
}
</script>

<template>
  <div v-if="estado.confirm" class="modal-fondo" @click.self="responder(false)">
    <div class="modal" style="max-width: 420px">
      <h3 style="margin-top:0">{{ estado.confirm.titulo }}</h3>
      <p style="color: var(--color-texto-suave)">{{ estado.confirm.mensaje }}</p>
      <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
        <button class="btn secundario" @click="responder(false)">Cancelar</button>
        <button class="btn peligro" @click="responder(true)">Confirmar</button>
      </div>
    </div>
  </div>
</template>
