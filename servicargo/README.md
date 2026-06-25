# Servicargo — Sistema Web de Logística y Encomiendas

Laravel 13 + Inertia 2 + Vue 3 + JWT + PostgreSQL. Implementa el flujo:
**Cotización → (cliente aprueba) → Encomienda → Nota de Venta → Pago(s) → Factura(s)**.

## Requisitos
- PHP 8.3+ con extensiones: `pdo_pgsql`, `pgsql`, `mbstring`, `fileinfo`, `curl`, `openssl`
- Node 20+ y npm
- PostgreSQL (vía Docker)

## Base de datos (Docker)
```bash
docker run -d --name postgres-db \
  -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=mi_bd \
  -p 5435:5432 postgres:latest
```
El `.env` ya apunta a `127.0.0.1:5435 / mi_bd / postgres / postgres`.

## Puesta en marcha
```bash
composer install
npm install
php artisan migrate:fresh --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```
Abrir **http://127.0.0.1:8000**

### Desarrollo (recompila al guardar)
```bash
npm run start   # Vite build --watch + php artisan serve
```

## Usuarios demo (contraseña: `password`)
| Rol | Correo |
|-----|--------|
| Administrador | admin@servicargo.bo |
| Vendedor | vendedor@servicargo.bo |
| Cliente | cliente@servicargo.bo |

## Arquitectura (3 capas, MVC-MVVM)
- **Presentación (MVVM):** `resources/js` — Vue 3 + Inertia. Carpetas: `Vistas/`, `Plantillas/`, `Componentes/`, `servicios/`.
- **Negocio (MVC):** `app/Http/Controllers` (REST), middleware `jwt`/`rol`/`bitacora`, validación en español.
- **Datos:** PostgreSQL, 18 tablas (14 core + 4 web: recurso, permiso, bitacora, visitas).

## Requisitos web cubiertos
Roles + menú dinámico (BD) · Matriz de acceso + Bitácora · 4 temas + accesibilidad (letra/contraste) ·
Validación en español · Contador de visitas por página · Búsqueda del negocio (header) ·
Estadísticas de negocio y de acceso · Pagos electrónicos (QR PagoFácil simulado) + crédito en cuotas + facturación automática.

## Notas
- **JWT** con expiración de **30 min** (`firebase/php-jwt`), `rol` en el payload. El front guarda el token en `localStorage` y rehidrata la sesión.
- **PagoFácil simulado:** el QR es un placeholder; se confirma con `POST /api/pagos/{pago}/simular-confirmacion` (equivale al callback).
- **Ventana de 20 min** de encomienda y **cleanup de cotizaciones vencidas** implementados en backend.
