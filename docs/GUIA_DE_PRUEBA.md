# Guía de prueba end-to-end — Servicargo · Logística y Encomiendas

Recorrido completo **desde que se cotiza un envío hasta que el cliente paga con QR y se
emite la factura**, indicando con qué usuario iniciar sesión en cada paso. Para cambiar de
actor: **cerrar sesión** (icono de salir arriba a la derecha) y entrar con el siguiente.

- App: **http://127.0.0.1:8000** · Login: **/login**
- Si la web no carga, el servidor está apagado: correr `npm run start` en la carpeta
  `servicargo/` (o `php artisan serve --host=127.0.0.1 --port=8000`).
- La BD Docker debe estar arriba: `docker start postgres-db`.

---

## Credenciales (todos los actores)

Contraseña de **todos**: `password`

| Rol | Correo | ¿Se auto-registra? |
|-----|--------|--------------------|
| **Administrador** (admin total) | `admin@servicargo.bo` | No |
| **Vendedor** | `vendedor@servicargo.bo` | No |
| **Cliente** | `cliente@servicargo.bo` | Sí (`/registro` es público) |

> Solo el **Cliente** puede crearse cuenta solo. El personal (admin/vendedor) lo da de alta
> el Administrador en **Usuarios** (`/usuarios`).

El flujo del negocio es:
**Cotización → (cliente aprueba) → Encomienda → Nota de Venta → Pago(s) → Factura(s)**

---

## Flujo completo (pago CONTADO con QR)

### Paso 1 — VENDEDOR: crea la cotización
1. Entrá como **`vendedor@servicargo.bo`**.
2. Andá a **Cotizaciones** (`/cotizaciones`) → **+ Nueva cotización**.
3. Completá:
   - **Cliente** (ej. Carla Cliente), **Tipo de envío** (terrestre/aéreo/marítimo).
   - **Remitente, Destinatario, Origen, Destino** (texto alfanumérico, sin símbolos).
   - **Peso (kg)**, **Volumen (m³)**, **Impuestos (Bs)**, **Validez (1–7 días)**.
   - **Contenido** (solo letras).
   - **Productos / servicios**: elegí uno o más y su cantidad (calcula el total).
4. **Crear cotización**.
   - ✅ Se guarda en la BD con estado **`PENDIENTE`** y total estimado.
5. **Cerrá sesión.**

> Atajo: el seeder ya deja **la cotización #1 PENDIENTE** para `cliente@servicargo.bo`,
> así que podés empezar directo en el Paso 2 si querés.

### Paso 2 — CLIENTE: aprueba la cotización
1. Entrá como **`cliente@servicargo.bo`**.
2. Andá a **Cotizaciones** (`/cotizaciones`) — solo ves **las tuyas**.
3. En la cotización `PENDIENTE`: **Ver** para revisar el detalle, luego **Aprobar**
   (o **Rechazar**).
   - ✅ Al aprobar, pasa a **`APROBADA`** y se abre la **ventana de 20 min** para que el
     vendedor que la atendió genere la encomienda.
4. **Cerrá sesión.**

### Paso 3 — VENDEDOR: genera la encomienda
1. Entrá como **`vendedor@servicargo.bo`**.
2. En **Cotizaciones**, sobre la fila `APROBADA`: **Generar encomienda**.
   - ✅ Se crea la **encomienda** (estado `REGISTRADA`) con la **guía de rastreo**, copiando
     los datos del envío. La cotización pasa a **`COMPLETADA`**.
   - ⏱️ **Ventana de 20 min:** durante los primeros 20 min solo puede generarla el vendedor
     que atendió la cotización (o un admin). Pasado ese tiempo, cualquier vendedor.
3. *(Opcional)* Andá a **Encomiendas** (`/encomiendas`) → **Gestionar** para mover el estado
   (`REGISTRADA → EN_TRANSITO → ENTREGADA`). Cada cambio queda en el **historial**.
4. **Cerrá sesión.**

### Paso 4 — VENDEDOR: crea la nota de venta (cobro)
1. Seguí como **`vendedor@servicargo.bo`** (o entrá de nuevo).
2. Andá a **Ventas** (`/ventas`) → **+ Nueva nota de venta**.
3. Elegí la **Encomienda** (autocompleta el cliente), definí **Impuestos/Descuento** y el
   **Tipo de pago = Contado**.
   - ✅ Se crea la **venta** con código **`tecnoSa-N`** y estado **`PENDIENTE`**.
   - *(Si elegís **Crédito**, pedí **número de cuotas ≥ 2** — ver Variación A.)*
4. **Cerrá sesión.**

### Paso 5 — CLIENTE: paga con QR
1. Entrá de nuevo como **`cliente@servicargo.bo`**.
2. Andá a **Pagos** (`/pagos`). En **"Ventas por cobrar"** vas a ver tu venta.
3. Tocá **Pagar** → el método queda fijo en **QR** (el cliente solo paga por QR).
4. **Generar QR** → se muestra el **QR PagoFácil (simulado)**.
5. Tocá **Simular confirmación** (equivale al callback de la pasarela).
   - ✅ El pago queda **`REGISTRADO`**, se **emite la factura** `FAC-<venta>-<pago>` y la
     venta pasa a **`PAGADA`**. 🎉
6. Revisá la factura en **Facturas** (`/facturas`).

**Fin del flujo.**

---

## Variación A — Pago a CRÉDITO (en cuotas)

- En el **Paso 4**, el vendedor crea la venta como **Crédito** e indica **N.º de cuotas ≥ 2**.
- En el **Paso 5**, el cliente en **Pagos** paga un **monto parcial** con QR:
  - Si querés, indicá el **N.º de cuota** en el modal.
  - Tras confirmar, la venta queda en **`PARCIAL`** (se emite factura por ese pago).
- Repetí el pago QR hasta cubrir el **total**: cuando `total_pagado ≥ total_final`, la venta
  pasa a **`PAGADA`**. Cada pago genera **su propia factura**.

## Variación B — Cobro en EFECTIVO (lo registra el vendedor)

- El **cliente solo puede pagar con QR**. Para **efectivo**, lo registra un **vendedor/admin**:
  1. Entrá como **`vendedor@servicargo.bo`** → **Pagos** (`/pagos`).
  2. **Pagar** sobre la venta → método **Efectivo** → **Registrar pago**.
  - ✅ El pago se registra de inmediato (sin QR), se emite la factura y se actualiza el estado.

## Variación C — Seguimiento del envío (cliente)

- El **cliente** entra a **Encomiendas** (`/encomiendas`) → **Seguimiento** y ve la **guía de
  rastreo** y el **historial de estados** que va registrando el vendedor.

---

## Máquinas de estado (referencia)

- **Cotización:** `PENDIENTE → APROBADA → COMPLETADA` · `PENDIENTE → RECHAZADA` ·
  (PENDIENTE vencida → se elimina por *cleanup*)
- **Encomienda:** `REGISTRADA → EN_TRANSITO → EN_DISTRIBUCION → ENTREGADA` (flujo libre, con historial)
- **Venta:** `PENDIENTE → PARCIAL → PAGADA`
- **Pago:** `REGISTRADO` (QR pasa por `PENDIENTE` hasta confirmarse)
- **Factura:** `EMITIDA` (una por cada pago)

## Quién hace qué (resumen)

| Paso | Actor | Dónde |
|------|-------|-------|
| Crear cotización | Vendedor / Admin | `/cotizaciones` → + Nueva cotización |
| Aprobar / Rechazar cotización | **Cliente** (las suyas) | `/cotizaciones` |
| Generar encomienda (ventana 20 min) | Vendedor / Admin | `/cotizaciones` → Generar encomienda |
| Mover estado del envío | Vendedor / Admin | `/encomiendas` → Gestionar |
| Crear nota de venta (cobro) | Vendedor / Admin | `/ventas` → + Nueva nota de venta |
| Pagar con QR | **Cliente** | `/pagos` |
| Cobrar en efectivo | Vendedor / Admin | `/pagos` |
| Ver facturas | Cliente / Vendedor / Admin | `/facturas` |

---

## Módulos de soporte (para probar el resto del sistema)

| Módulo | Ruta | Rol |
|--------|------|-----|
| Usuarios (alta de personal) | `/usuarios` | Admin |
| Catálogo (categorías + productos) | `/catalogo` | Admin (crea) · Vendedor (ve) |
| Almacenes | `/almacenes` | Admin |
| Inventario (movimientos INGRESO/SALIDA) | `/inventario` | Admin · Vendedor |
| Reportes + export **PDF** | `/reportes` | Admin (todos) · Vendedor (la mayoría) |
| Matriz de Acceso (rol × recurso) | `/permisos` | Admin |
| Bitácora (auditoría) | `/bitacora` | Admin |

> **Búsqueda del negocio:** campo en el **encabezado** — buscá por guía de rastreo, destino
> o número de cotización (el cliente solo encuentra lo suyo).
> **Temas y accesibilidad:** botones del encabezado (Día/Noche/Niños/Jóvenes, tamaño de letra
> A-/A/A+, alto contraste). **Contador de visitas:** al pie de cada página.
