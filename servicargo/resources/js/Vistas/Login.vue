<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Truck } from 'lucide-vue-next';
import { useAuth } from '../servicios/useAuth';
import { useToast } from '../servicios/useUI';
import { withBase } from '../servicios/useBase';
import ToastHost from '../Componentes/ToastHost.vue';

const { login } = useAuth();
const toast = useToast();
const correo = ref('admin@servicargo.bo');
const contrasena = ref('password');
const cargando = ref(false);

async function entrar() {
  cargando.value = true;
  try {
    await login(correo.value, contrasena.value);
    toast.exito('Bienvenido a Servicargo');
    router.visit(withBase('/inicio'));
  } catch (e) {
    toast.error(e.message || 'No se pudo iniciar sesión');
  } finally {
    cargando.value = false;
  }
}
</script>

<template>
  <div class="auth-wrap">
    <div class="auth-card">
      <div style="text-align:center; margin-bottom:18px">
        <Truck :size="40" color="#1f6feb" />
        <h2 style="margin:8px 0 0">Servicargo</h2>
        <p style="color:var(--color-texto-suave); margin:4px 0 0">Inicia sesión</p>
      </div>
      <form @submit.prevent="entrar">
        <label>Correo</label>
        <input class="input" type="email" v-model="correo" required />
        <label>Contraseña</label>
        <input class="input" type="password" v-model="contrasena" required />
        <button class="btn" style="width:100%; margin-top:18px; justify-content:center" :disabled="cargando">
          {{ cargando ? 'Entrando…' : 'Entrar' }}
        </button>
      </form>
      <p style="text-align:center; margin-top:16px; color:var(--color-texto-suave)">
        ¿No tienes cuenta? <Link :href="withBase('/registro')">Crear cuenta</Link>
      </p>
      <p style="text-align:center; font-size:12px; color:var(--color-texto-suave)">
        Demo: admin@ / vendedor@ / cliente@servicargo.bo · contraseña <strong>password</strong>
      </p>
    </div>
    <ToastHost />
  </div>
</template>
