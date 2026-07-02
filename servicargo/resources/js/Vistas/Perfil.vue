<script setup>
import { ref, computed, onMounted } from 'vue';
import { User, Camera } from 'lucide-vue-next';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { withBase } from '../servicios/useBase';
import { useAuth } from '../servicios/useAuth';
import { useToast } from '../servicios/useUI';

const toast = useToast();
const { sesion, refrescar } = useAuth();

const form = ref({ nombre: '', apellido: '', correo: '', telefono: '', contrasena: '' });
const errores = ref({});
const guardando = ref(false);
const subiendo = ref(false);
const inputFoto = ref(null);

// URL de la foto: ruta estática dentro de public/ (funciona en local y en el hosting).
const fotoUrl = computed(() => {
  const f = sesion.usuario?.foto;
  return f ? withBase('/' + f) : null;
});

const iniciales = computed(() => {
  const n = sesion.usuario?.nombre || '';
  const a = sesion.usuario?.apellido || '';
  return ((n[0] || '') + (a[0] || n[1] || '')).toUpperCase() || 'U';
});

function cargarDesdeSesion() {
  const u = sesion.usuario || {};
  form.value = {
    nombre: u.nombre || '',
    apellido: u.apellido || '',
    correo: u.correo || '',
    telefono: u.telefono || '',
    contrasena: '',
  };
}

onMounted(async () => {
  await refrescar();
  cargarDesdeSesion();
});

async function guardar() {
  errores.value = {};
  guardando.value = true;
  try {
    const body = { ...form.value };
    if (!body.contrasena) delete body.contrasena;
    await api('/perfil', { method: 'PUT', body });
    await refrescar();
    cargarDesdeSesion();
    toast.exito('Perfil actualizado');
  } catch (e) {
    errores.value = e.errors || {};
    toast.error(e.message);
  } finally {
    guardando.value = false;
  }
}

function elegirFoto() {
  inputFoto.value?.click();
}

async function subirFoto(evento) {
  const archivo = evento.target.files?.[0];
  if (!archivo) return;
  subiendo.value = true;
  try {
    const fd = new FormData();
    fd.append('foto', archivo);
    await api('/perfil/foto', { method: 'POST', body: fd });
    await refrescar();
    toast.exito('Foto actualizada');
  } catch (e) {
    toast.error(e.errors?.foto?.[0] || e.message);
  } finally {
    subiendo.value = false;
    if (inputFoto.value) inputFoto.value.value = '';
  }
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Mi Perfil</h1>
    <p class="subtitulo">Edita tus datos personales y tu foto de perfil</p>

    <div class="grid grid-2" style="align-items:start; gap:16px">
      <!-- Foto -->
      <div class="card" style="text-align:center">
        <div class="foto-perfil">
          <img v-if="fotoUrl" :src="fotoUrl" alt="Foto de perfil" />
          <span v-else class="foto-iniciales">{{ iniciales }}</span>
        </div>
        <div style="margin-top:14px">
          <button class="btn secundario" :disabled="subiendo" @click="elegirFoto">
            <Camera :size="16" style="vertical-align:-3px; margin-right:6px" />
            {{ subiendo ? 'Subiendo…' : 'Cambiar foto' }}
          </button>
          <input ref="inputFoto" type="file" accept="image/jpeg,image/png,image/webp" style="display:none" @change="subirFoto" />
        </div>
        <p class="subtitulo" style="margin-top:10px; font-size:.85em">JPG, PNG o WEBP. Máx. 30 MB.</p>
      </div>

      <!-- Datos -->
      <div class="card">
        <form @submit.prevent="guardar">
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

          <div class="grid grid-2">
            <div>
              <label>Teléfono</label>
              <input class="input" v-model="form.telefono" />
              <div v-if="errores.telefono" class="error-campo">{{ errores.telefono[0] }}</div>
            </div>
            <div>
              <label>Nueva contraseña <span style="color:var(--color-texto-suave)">(dejar vacío = no cambiar)</span></label>
              <input class="input" type="password" v-model="form.contrasena" autocomplete="new-password" />
              <div v-if="errores.contrasena" class="error-campo">{{ errores.contrasena[0] }}</div>
            </div>
          </div>

          <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
            <button class="btn" :disabled="guardando">{{ guardando ? 'Guardando…' : 'Guardar cambios' }}</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.foto-perfil {
  width: 140px;
  height: 140px;
  margin: 0 auto;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primario, #1f6feb);
  color: #fff;
  border: 3px solid var(--color-borde, #e5e7eb);
}
.foto-perfil img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.foto-iniciales {
  font-size: 3rem;
  font-weight: 700;
}
</style>
