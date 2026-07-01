<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Truck } from 'lucide-vue-next';
import { api } from '../servicios/useApi';
import { useToast } from '../servicios/useUI';
import { withBase } from '../servicios/useBase';
import ToastHost from '../Componentes/ToastHost.vue';

const toast = useToast();
const form = ref({ ci: '', nombre: '', apellido: '', correo: '', contrasena: '', telefono: '' });
const errores = ref({});
const cargando = ref(false);

async function registrar() {
  cargando.value = true;
  errores.value = {};
  try {
    await api('/auth/register', { method: 'POST', body: form.value });
    toast.exito('Cuenta creada. Ya puedes iniciar sesión.');
    setTimeout(() => router.visit(withBase('/login')), 800);
  } catch (e) {
    errores.value = e.errors || {};
    toast.error(e.message || 'No se pudo crear la cuenta');
  } finally {
    cargando.value = false;
  }
}
</script>

<template>
  <div class="auth-wrap">
    <div class="auth-card">
      <div style="text-align:center; margin-bottom:14px">
        <Truck :size="40" color="#1f6feb" />
        <h2 style="margin:8px 0 0">Crear cuenta de cliente</h2>
      </div>
      <form @submit.prevent="registrar">
        <label>CI</label>
        <input class="input" v-model="form.ci" />
        <div v-if="errores.ci" class="error-campo">{{ errores.ci[0] }}</div>

        <div class="grid grid-2">
          <div>
            <label>Nombre</label>
            <input class="input" v-model="form.nombre" />
            <div v-if="errores.nombre" class="error-campo">{{ errores.nombre[0] }}</div>
          </div>
          <div>
            <label>Apellido</label>
            <input class="input" v-model="form.apellido" />
            <div v-if="errores.apellido" class="error-campo">{{ errores.apellido[0] }}</div>
          </div>
        </div>

        <label>Correo</label>
        <input class="input" type="email" v-model="form.correo" />
        <div v-if="errores.correo" class="error-campo">{{ errores.correo[0] }}</div>

        <label>Teléfono (opcional)</label>
        <input class="input" v-model="form.telefono" />
        <div v-if="errores.telefono" class="error-campo">{{ errores.telefono[0] }}</div>

        <label>Contraseña</label>
        <input class="input" type="password" v-model="form.contrasena" />
        <div v-if="errores.contrasena" class="error-campo">{{ errores.contrasena[0] }}</div>

        <button class="btn" style="width:100%; margin-top:18px; justify-content:center" :disabled="cargando">
          {{ cargando ? 'Creando…' : 'Crear cuenta' }}
        </button>
      </form>
      <p style="text-align:center; margin-top:16px; color:var(--color-texto-suave)">
        ¿Ya tienes cuenta? <Link :href="withBase('/login')">Iniciar sesión</Link>
      </p>
    </div>
    <ToastHost />
  </div>
</template>
