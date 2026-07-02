<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';

const lista = ref([]);
const filtro = ref('');

async function cargar() {
  const datos = await api('/bitacora', { params: filtro.value ? { accion: filtro.value } : {} });
  // El único "acceso a recurso" válido es la descarga de PDF; el resto (consultas
  // GET y duplicados antiguos del middleware) se ocultan de la vista.
  lista.value = datos.filter((b) => !(b.accion === 'acceso_recurso' && detalleTexto(b) !== 'Descargar PDF'));
}
onMounted(cargar);

function badge(a) {
  return { login_ok: 'exito', login_fallido: 'error', logout: 'info', acceso_recurso: '', accion: 'aviso' }[a] || '';
}

// Etiqueta legible para la columna Acción.
const accionesLegibles = {
  login_ok: 'Inicio de sesión',
  login_fallido: 'Login fallido',
  logout: 'Cierre de sesión',
  acceso_recurso: 'Acceso',
  accion: 'Acción',
};
function accionTexto(a) {
  return accionesLegibles[a] || a;
}

// Nombre legible para la columna Recurso (a partir de la clave de la URL).
const recursosLegibles = {
  usuarios: 'Usuarios', categorias: 'Categorías', productos: 'Productos', catalogo: 'Catálogo',
  almacenes: 'Almacenes', inventario: 'Inventario', cotizaciones: 'Cotizaciones',
  encomiendas: 'Encomiendas', ventas: 'Ventas', pagos: 'Pagos', facturas: 'Facturas',
  reportes: 'Reportes', estadisticas: 'Estadísticas', permisos: 'Matriz de Acceso',
  bitacora: 'Bitácora', preferencias: 'Preferencias', perfil: 'Mi Perfil',
  'metodos-pago': 'Métodos de pago', buscar: 'Búsqueda', visitas: 'Visitas', auth: 'Autenticación',
};
function recursoTexto(r) {
  if (!r) return '—';
  return recursosLegibles[r] || (r.charAt(0).toUpperCase() + r.slice(1));
}

// La columna Detalle muestra SOLO un verbo simple: Crear, Actualizar, Eliminar,
// Descargar PDF, Login, Logout… (nunca el texto largo tipo "Crear usuario #5").
function detalleTexto(b) {
  const a = b.accion;
  if (a === 'login_ok') return 'Login';
  if (a === 'logout') return 'Logout';
  if (a === 'login_fallido') return 'Login fallido';

  const d = (b.detalle || '').toLowerCase();
  if (d.includes('pdf')) return 'Descargar PDF';
  if (d.startsWith('elimin')) return 'Eliminar';
  if (d.startsWith('crear') || d.startsWith('registrar') || d.startsWith('auto-registro') || d.startsWith('pago') || d.startsWith('qr')) return 'Crear';
  if (d.startsWith('editar') || d.startsWith('actualiz') || d.startsWith('cambio') || d.startsWith('callback')) return 'Actualizar';

  // Registros antiguos con formato "MÉTODO /ruta".
  const m = d.match(/^(post|put|patch|delete)\b/);
  if (m) return { post: 'Crear', put: 'Actualizar', patch: 'Actualizar', delete: 'Eliminar' }[m[1]];

  return 'Acción';
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Bitácora</h1>
    <p class="subtitulo">Auditoría de accesos y acciones</p>
    <div class="card">
      <div class="fila-acciones">
        <select class="input" style="max-width:220px" v-model="filtro" @change="cargar">
          <option value="">Todas las acciones</option>
          <option value="login_ok">Inicio de sesión</option>
          <option value="login_fallido">Login fallido</option>
          <option value="logout">Cierre de sesión</option>
          <option value="acceso_recurso">Acceso a recurso</option>
          <option value="accion">Acción</option>
        </select>
      </div>
    </div>
    <div class="card">
      <table>
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Recurso</th><th>Detalle</th><th>IP</th></tr></thead>
        <tbody>
          <tr v-for="b in lista" :key="b.id">
            <td>{{ new Date(b.fecha).toLocaleString() }}</td>
            <td>{{ b.usuario?.nombre || '—' }}</td>
            <td><span class="badge" :class="badge(b.accion)">{{ accionTexto(b.accion) }}</span></td>
            <td>{{ recursoTexto(b.recurso) }}</td>
            <td>{{ detalleTexto(b) }}</td>
            <td>{{ b.ip }}</td>
          </tr>
          <tr v-if="!lista.length"><td colspan="6" style="color:var(--color-texto-suave)">Sin registros</td></tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
