# Servicargo — Sistema Web de Logística y Encomiendas

Laravel 13 + Inertia 2 + Vue 3 + JWT + PostgreSQL. Implementa el flujo:
**Cotización → (cliente aprueba) → Encomienda → Nota de Venta → Pago(s) → Factura(s)**.

## Requisitos
- PHP **8.3+** con extensiones: `pdo_pgsql`, `pgsql`, `mbstring`, `fileinfo`, `curl`, `openssl`, `gd`
- Node 20+ y npm
- PostgreSQL 16 (local o remoto)

> Si tu PHP del sistema es más viejo (p. ej. XAMPP trae 8.0), no hace falta desinstalar
> nada: bajá el ZIP "Non Thread Safe" de PHP 8.3 desde https://windows.php.net/download/,
> descomprimilo en una carpeta (ej. `C:\tools\php83`), copiá `php.ini-development` a
> `php.ini` y activá (sacale el `;`) las extensiones de arriba en ese `php.ini`. Para usarlo
> en una terminal puntual: `set PATH=C:\tools\php83;%PATH%` (cmd) o
> `$env:Path = "C:\tools\php83;$env:Path"` (PowerShell) antes de correr `composer`/`php artisan`.

---

## Puesta en marcha — LOCAL (PostgreSQL en tu máquina)

El `.env` del proyecto ya viene configurado con el bloque **LOCAL activo** y el bloque
**remoto comentado** (ver más abajo). Solo faltan estos pasos:

```bash
composer install
npm install
```

### 1. Crear la base de datos local
Necesitás un PostgreSQL corriendo en `127.0.0.1:5432` (si usás el que se instala como
servicio de Windows, ya debería estar arriba — comprobalo con `Get-Service *postgres*`
en PowerShell). Creá la base con el usuario que prefieras:

```bash
# con psql (ajustá el usuario a tu instalación, normalmente "postgres")
psql -U postgres -h 127.0.0.1 -c "CREATE DATABASE servicargo_local;"
```

### 2. Completar el `.env`
Abrí `servicargo/.env` y reemplazá `DB_PASSWORD=CAMBIAR_PASSWORD_LOCAL` por la contraseña
real de tu usuario de Postgres (`DB_USERNAME` ya está en `postgres`, cambialo si usás otro
rol). El resto del bloque `DB_*` local ya apunta a `servicargo_local`.

### 3. Generar key, migrar y sembrar
```bash
php artisan key:generate   # solo si APP_KEY está vacío
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```
Abrir **http://127.0.0.1:8000**

### Desarrollo (recompila el front al guardar)
```bash
npm run start   # = concurrently: "vite build --watch" + "php artisan serve"
```
No hay HMR real (ver nota de Vite más abajo): tras guardar un `.vue`, refrescá el navegador.

---

## Cambiar a la base de datos remota (servidor de la materia)

El `.env` trae comentado el bloque de conexión a `db_grupo12sa` en
`www.tecnoweb.org.bo`. Para usarlo: comentá el bloque **LOCAL** y descomentá el bloque
**REMOTA**, dejando algo así:

```env
DB_CONNECTION=pgsql
DB_HOST=www.tecnoweb.org.bo
DB_PORT=5432
DB_DATABASE=db_grupo12sa
DB_USERNAME=grupo12sa
DB_PASSWORD=grup012grup012*
```

Esa base **ya tiene las 19 tablas de este proyecto migradas y sembradas** (se reseteó y
recreó con `php artisan migrate --seed --force` el 2026-06-30 — se eliminaron las 13 tablas
de un esquema anterior que tenía la base, sin tocar la base de datos en sí). No hace falta
volver a migrar salvo que agregues una migración nueva.

---

## Usuarios demo (contraseña: `password`)
| Rol | Correo |
|-----|--------|
| Administrador | admin@servicargo.bo |
| Vendedor | vendedor@servicargo.bo |
| Cliente | cliente@servicargo.bo |

---

## Arquitectura (3 capas, MVC-MVVM)
- **Presentación (MVVM):** `resources/js` — Vue 3 + Inertia. Carpetas: `Vistas/`, `Plantillas/`, `Componentes/`, `servicios/`.
- **Negocio (MVC):** `app/Http/Controllers` (REST), middleware `jwt`/`rol`/`bitacora`, validación en español.
- **Datos:** PostgreSQL, 19 tablas (14 core + 5 web: recurso, permiso, bitacora, visitas, metodo_pago).

## Requisitos web cubiertos
Roles + menú dinámico (BD) · Matriz de acceso + Bitácora · 4 temas + accesibilidad (letra/contraste) ·
Validación en español · Contador de visitas por página · Búsqueda del negocio (header) ·
Estadísticas de negocio y de acceso · Pagos electrónicos (QR PagoFácil real) + crédito en cuotas + facturación automática.

## Notas
- **JWT** con expiración de **30 min** (`firebase/php-jwt`), `rol` en el payload. El front guarda el token en `localStorage` y rehidrata la sesión.
- **PagoFácil (real):** el QR se genera con la pasarela (`generate-qr`). El pago se confirma por dos vías: el **callback** público `POST /callback` (PagoFácil lo llama al pagar) y el **polling** del front `GET /api/pagos/{pago}/estado-qr` (consulta `query-transaction`; `paymentStatus == 2` = pagado). Credenciales en `.env` (`PAGOFACIL_*`). `/return` muestra la página de retorno del navegador.
- **Ventana de 20 min** de encomienda y **cleanup de cotizaciones vencidas** implementados en backend.
- **Vite + rutas con `#`:** si la ruta del proyecto tiene `#` u otros caracteres conflictivos, no uses `npm run dev` (rompe la resolución de módulos); usá `npm run start`.

---

## Desplegar en un VPS

Dos formas de trabajar contra un servidor remoto (VPS) — comparación rápida antes de
elegir:

| | Editar y auto-subir cada archivo (estilo FileZilla/Zend Studio) | Compilar local y subir el proyecto/carpeta completa |
|---|---|---|
| **Cómo es** | Extensión de VSCode tipo SFTP con "upload on save": cada `Ctrl+S` sube ese archivo al VPS | `npm run build` local, luego se sube todo (o se hace `git pull` / `rsync` en el servidor) |
| **Sirve bien para** | Cambios sueltos de backend PHP (un controller, una ruta) en un sitio ya desplegado | Cualquier cambio de frontend (Vue/Inertia), primer deploy, o cuando cambia `composer.json`/`package.json` |
| **Problema con este proyecto** | El frontend se sirve **compilado** desde `public/build/` (Vite). Subir un `.vue` suelto no cambia nada en el navegador hasta que compiles y subas `public/build/` también. Tampoco corre `composer install` ni `php artisan migrate` por vos. | Requiere acceso por SSH al VPS para correr `composer install --no-dev`, `npm run build` (o subir `public/build/` ya compilado) y `php artisan migrate --force` |
| **Riesgo** | Fácil dejar el sitio en un estado a medio actualizar (código subido sin recompilar) | Ninguno si seguís el checklist de abajo |

**Recomendación:** para *este* proyecto (Laravel + Vite, con build step y migraciones),
subir el proyecto completo (o los archivos que cambiaron) por SFTP **más** acceso SSH para
correr los comandos de servidor es lo más confiable. El "upload on save" tipo Zend Studio
sirve como atajo cómodo mientras editás, pero el build (`npm run build`) y los comandos
`artisan` los tenés que correr sí o sí en algún momento — por FTP solo no se puede.

### Cómo configurar VSCode como tu amigo con Zend Studio (edición con auto-subida)
1. Instalá la extensión **SFTP** (autor `Natizyskunk`) en VSCode.
2. `Ctrl+Shift+P` → `SFTP: Config` → crea `.vscode/sftp.json` en la raíz del proyecto:
   ```jsonc
   {
     "name": "vps-servicargo",
     "host": "TU_HOST_VPS",
     "protocol": "sftp",       // preferí SFTP (cifrado) sobre FTP plano si el VPS tiene SSH
     "port": 22,
     "username": "TU_USUARIO",
     "password": "TU_PASSWORD", // o "privateKeyPath" si usás llave SSH
     "remotePath": "/ruta/al/proyecto/en/el/vps",
     "uploadOnSave": true,
     "ignore": [".vscode", ".git", "node_modules", "vendor", ".env"]
   }
   ```
3. Con `uploadOnSave: true`, cada archivo que guardés se sube solo al VPS — igual que en
   Zend Studio. `.vscode/sftp.json` **no se sube a git** (contiene la contraseña); agregalo
   a `.gitignore` si no está.
4. Para subir el proyecto entero una vez (primer deploy): clic derecho en la carpeta raíz →
   **SFTP: Sync Local -> Remote**.

### Checklist de un deploy completo
```bash
# en el VPS, por SSH:
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env   # solo la primera vez; luego editar con credenciales de producción
php artisan key:generate
php artisan config:clear
php artisan migrate --force
```
`APP_URL` en el `.env` del VPS debe ser la URL real (afecta las imágenes de productos, ver
`Producto::getImagenUrlAttribute()`). `public/images/productos/` debe ser escribible.

Si el VPS **no** tiene acceso SSH (solo FTP), la única forma de que el frontend funcione es
compilar localmente (`npm run build`) y subir la carpeta `public/build/` ya generada junto
con el resto del proyecto — no hay forma de correr `npm`/`composer`/`artisan` sin shell.
