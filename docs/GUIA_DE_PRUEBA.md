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
| **Asesor** | `asesor@servicargo.bo` | No |
| **Cliente** | `cliente@servicargo.bo` | Sí (`/registro` es público) |

> Solo el **Cliente** puede crearse cuenta solo. El personal (admin/asesor) lo da de alta
> el Administrador en **Usuarios** (`/usuarios`).

El flujo del negocio es:
**Cotización → (cliente aprueba) → Encomienda → Nota de Venta → Pago(s) → Factura(s)**

---

## Flujo completo (pago CONTADO con QR)

### Paso 1 — ASESOR: crea la cotización
1. Entrá como **`asesor@servicargo.bo`**.
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
     asesor que la atendió genere la encomienda.
4. **Cerrá sesión.**

### Paso 3 — ASESOR: genera la encomienda
1. Entrá como **`asesor@servicargo.bo`**.
2. En **Cotizaciones**, sobre la fila `APROBADA`: **Generar encomienda**.
   - ✅ Se crea la **encomienda** (estado `REGISTRADA`) con la **guía de rastreo**, copiando
     los datos del envío. La cotización pasa a **`COMPLETADA`**.
   - ⏱️ **Ventana de 20 min:** durante los primeros 20 min solo puede generarla el asesor
     que atendió la cotización (o un admin). Pasado ese tiempo, cualquier asesor.
3. *(Opcional)* Andá a **Encomiendas** (`/encomiendas`) → **Gestionar** para mover el estado
   (`REGISTRADA → EN_TRANSITO → ENTREGADA`). Cada cambio queda en el **historial**.
4. **Cerrá sesión.**

### Paso 4 — ASESOR: crea la nota de venta (cobro)
1. Seguí como **`asesor@servicargo.bo`** (o entrá de nuevo).
2. Andá a **Ventas** (`/ventas`) → **+ Nueva nota de venta**.
3. Elegí la **Encomienda** (autocompleta el cliente), definí **Impuestos/Descuento** y el
   **Tipo de pago = Contado**.
   - ✅ Se crea la **venta** con código **`tecnoSa-N`** y estado **`PENDIENTE`**.
   - *(Si elegís **Crédito**, pedí **número de cuotas ≥ 2** — ver Variación A.)*
4. **Cerrá sesión.**

### Paso 5 — CLIENTE: registra su método de pago y paga con QR
1. Entrá de nuevo como **`cliente@servicargo.bo`**.
2. Andá a **Pagos** (`/pagos`).
3. **Registra un método de pago** (panel **"Mis métodos de pago"**):
   - **Tipo = QR** (el cliente solo puede pagar por QR), **Alias** (ej. *Mi QR personal*)
     y, opcional, una **Referencia** (alias/cuenta — nunca datos sensibles).
   - **Registrar método** → aparece en la lista. *(El seeder ya deja uno cargado.)*
4. En **"Ventas por cobrar"** vas a ver tu venta. Tocá **Pagar**.
5. En el modal, elegí tu **método registrado** en *"Método registrado"* (queda en **QR**).
6. **Generar QR** → se muestra el **QR PagoFácil (simulado)**.
7. Tocá **Simular confirmación** (equivale al callback de la pasarela).
   - ✅ El pago queda **`REGISTRADO`**, se **emite la factura** `FAC-<venta>-<pago>` y la
     venta pasa a **`PAGADA`**. 🎉
8. Revisá la factura en **Facturas** (`/facturas`).

> **Punto 10 (pagos electrónicos):** "Mis métodos de pago" cubre el **registro de métodos
> de pago por usuario**; el pago QR/efectivo cubre los **pagos únicos**; y la venta a
> **Crédito** cubre el **plan de pagos en cuotas** (Variación A).

**Fin del flujo.**

---

## Variación A — Pago a CRÉDITO (en cuotas)

- En el **Paso 4**, el asesor crea la venta como **Crédito** e indica **N.º de cuotas ≥ 2**.
- En el **Paso 5**, el cliente en **Pagos** paga un **monto parcial** con su **método QR
  registrado**:
  - Si querés, indicá el **N.º de cuota** en el modal.
  - Tras confirmar, la venta queda en **`PARCIAL`** (se emite factura por ese pago).
- Repetí el pago QR hasta cubrir el **total**: cuando `total_pagado ≥ total_final`, la venta
  pasa a **`PAGADA`**. Cada pago genera **su propia factura**.

## Variación B — Cobro en EFECTIVO (lo registra el asesor)

- El **cliente solo puede pagar con QR**. Para **efectivo**, lo registra un **asesor/admin**:
  1. Entrá como **`asesor@servicargo.bo`** → **Pagos** (`/pagos`).
  2. **Pagar** sobre la venta → método **Efectivo** → **Registrar pago**.
  - ✅ El pago se registra de inmediato (sin QR), se emite la factura y se actualiza el estado.

## Variación C — Seguimiento del envío (cliente)

- El **cliente** entra a **Encomiendas** (`/encomiendas`) → **Seguimiento** y ve la **guía de
  rastreo** y el **historial de estados** que va registrando el asesor.

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
| Crear cotización | Asesor / Admin | `/cotizaciones` → + Nueva cotización |
| Aprobar / Rechazar cotización | **Cliente** (las suyas) | `/cotizaciones` |
| Generar encomienda (ventana 20 min) | Asesor / Admin | `/cotizaciones` → Generar encomienda |
| Mover estado del envío | Asesor / Admin | `/encomiendas` → Gestionar |
| Crear nota de venta (cobro) | Asesor / Admin | `/ventas` → + Nueva nota de venta |
| Registrar método de pago | Cliente / Asesor / Admin | `/pagos` → Mis métodos de pago |
| Pagar con QR | **Cliente** | `/pagos` |
| Cobrar en efectivo | Asesor / Admin | `/pagos` |
| Ver facturas | Cliente / Asesor / Admin | `/facturas` |

---

## Módulos de soporte (para probar el resto del sistema)

| Módulo | Ruta | Rol |
|--------|------|-----|
| Usuarios (alta de personal) | `/usuarios` | Admin |
| Catálogo (categorías + productos) | `/catalogo` | Admin (crea) · Asesor (ve) |
| Almacenes | `/almacenes` | Admin |
| Inventario (movimientos INGRESO/SALIDA) | `/inventario` | Admin · Asesor |
| Reportes + export **PDF** | `/reportes` | Admin (todos) · Asesor (la mayoría) |
| Matriz de Acceso (rol × recurso) | `/permisos` | Admin |
| Bitácora (auditoría) | `/bitacora` | Admin |

> **Búsqueda del negocio:** campo en el **encabezado** — buscá por guía de rastreo, destino
> o número de cotización (el cliente solo encuentra lo suyo).
> **Temas y accesibilidad:** botones del encabezado (Día/Noche/Niños/Jóvenes, tamaño de letra
> A-/A/A+, alto contraste). **Contador de visitas:** al pie de cada página.
