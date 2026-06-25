# Servicargo | Sistema Web de Logística y Encomiendas — Especificación de Desarrollo

> **Propósito:** contexto completo y autoritativo para el desarrollo del sistema web
> (Claude Code). Cubre el flujo de **Captura de Requisitos** del PUDS (actores, casos
> de uso, priorización y los 8 CU detallados), el stack, la arquitectura MVC-MVVM y el
> modelo de datos.
>
> **Fuente de verdad del negocio:** `ESPECIFICACION_CASOS_DE_USO.md` (especificación
> funcional de Servicargo, independiente del lenguaje). Este documento la traduce a un
> sistema **100% web**.
>
> **Estructura de carpetas y convenciones:** se replica la organización del
> *proyecto-ejemplo* (Laravel 12 + Inertia + Vue + JWT) que tuvo buenas revisiones —
> mismo stack y mismos requisitos funcionales, distinta idea de negocio.
>
> **Todo es web.** La especificación original menciona "correo" como transporte de
> comandos; aquí **no se usa correo para nada**. Toda interacción es por **interfaz web**
> (vistas Vue) y **endpoints HTTP REST**. Donde la spec dice "notificar por correo",
> aquí se resuelve con **respuesta HTTP / estado en pantalla**.
>
> Lo puramente gráfico (diagramas de colaboración, secuencia, despliegue, clases) se
> obtendrá luego por **ingeniería inversa** sobre el código implementado.

---

## 0. Visión General

**El sistema.** Es un **sitio web** de gestión logística de encomiendas con base de
datos, **arquitectura de tres capas** y patrón **MVC-MVVM**, organizado en torno a **8
casos de uso** de negocio e incorporando los requisitos web (multi-tema, accesibilidad,
bitácora, matriz de acceso, contador de visitas, búsqueda, estadísticas y pagos
electrónicos con plan de pagos en cuotas).

**El negocio.** "Servicargo": empresa de logística que gestiona el **ciclo completo de
envío de encomiendas**. El flujo de negocio es:

```
Cotización  →  (Cliente aprueba)  →  Encomienda  →  Nota de Venta  →  Pago(s)  →  Factura(s)
```

Con módulos de soporte: **Usuarios**, **Categorías**, **Productos** (servicios/ítems de
envío), **Almacenes**, **Inventario** y **Reportes**. Problema a resolver: digitalizar y
controlar el ciclo cotización→encomienda→venta→pago, el seguimiento de envíos y el
control de inventario por almacén.

> **⚠️ Este documento es la especificación de planificación.** El estado real
> implementado y todas las decisiones tomadas durante el desarrollo están en la
> **§9 "Estado de Implementación y Decisiones"** (al final) y en `CLAUDE.md`. Ante
> cualquier diferencia, manda la §9.

**Stack objetivo (idéntico al proyecto-ejemplo):**
- **PHP 8.4 + Laravel 12** — backend, capa de negocio + datos (MVC).
- **Inertia.js 2** — puente SPA entre Laravel y Vue
- **Vue 3** — frontend reactivo (MVVM)
- **JWT** (`tymon/jwt-auth`) — autenticación por token con `rol` en el payload y
  **expiración de 30 minutos** (equivale a la "sesión de 30 min" de la spec)
- **Ziggy** — rutas Laravel disponibles en JS
- **dompdf** — generación de reportes PDF
- **PostgreSQL** — base de datos
- **CSS único** con 3 temas + accesibilidad

---

## 1. Estructura de Carpetas (convención del proyecto-ejemplo)

```
servicargo-web/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # un controller REST por entidad/recurso
│   │   │   ├── AuthController.php         # register, login (JWT 30 min), logout
│   │   │   ├── UsuarioController.php
│   │   │   ├── CategoriaController.php
│   │   │   ├── ProductoController.php
│   │   │   ├── AlmacenController.php
│   │   │   ├── InventarioController.php   # movimientos INGRESO/SALIDA + stock
│   │   │   ├── CotizacionController.php   # CRUD + cleanup vencidas + aprobar/rechazar
│   │   │   ├── EncomiendaController.php   # crear desde cotización + historial estados
│   │   │   ├── VentaController.php        # nota de venta (contado/crédito)
│   │   │   ├── PagoController.php         # EFECTIVO/QR + PagoFácil + factura automática
│   │   │   ├── FacturaController.php      # consulta/listado (se generan solas)
│   │   │   ├── ReporteController.php      # REPVEN/REPENC/REPINV/REPCOT/REPFAC + acceso
│   │   │   ├── BuscarController.php       # búsqueda del negocio (encabezado)
│   │   │   └── BitacoraController.php     # auditoría
│   │   └── Middleware/
│   │       ├── CheckRole.php              # control de acceso por rol (JWT)
│   │       ├── RegistrarBitacora.php      # log de accesos/acciones
│   │       ├── ContadorVisitas.php        # incrementa visitas por ruta
│   │       └── HandleInertiaRequests.php  # shared props (usuario, tema, visitas)
│   ├── Models/                   # Eloquent, tabla singular, sin timestamps
│   │   ├── Usuario.php           # implements JWTSubject; rol al payload
│   │   ├── Categoria.php
│   │   ├── Producto.php
│   │   ├── Almacen.php
│   │   ├── Inventario.php        # PK compuesta (producto_id, almacen_id)
│   │   ├── Cotizacion.php
│   │   ├── DetalleCotizacion.php # PK compuesta (cotizacion_id, producto_id)
│   │   ├── Encomienda.php
│   │   ├── HistorialEstado.php   # auditoría de estados de encomienda
│   │   ├── Venta.php
│   │   ├── DetalleVenta.php      # PK compuesta (venta_id, producto_id)
│   │   ├── Pago.php
│   │   ├── Factura.php           # una por pago
│   │   ├── Reporte.php           # registro de reportes generados
│   │   ├── Recurso.php           # matriz de acceso
│   │   ├── Permiso.php           # matriz de acceso (rol × recurso)
│   │   ├── Bitacora.php          # auditoría web
│   │   └── Visita.php            # contador por página
│   └── Providers/
├── database/
│   ├── migrations/               # una migración por tabla
│   ├── seeders/
│   │   └── DatabaseSeeder.php     # admin por defecto, recursos, permisos, datos demo
│   └── factories/
├── resources/
│   ├── css/
│   │   └── app.css               # estilo único + variables de tema
│   └── js/
│       ├── app.js
│       ├── bootstrap.js
│       ├── Plantillas/            # (antes Layouts)
│       │   └── AppLayout.vue      # header (búsqueda), footer (contador visitas), selector de tema
│       ├── Vistas/                # (antes Pages) — una página Vue por vista
│       │   ├── Landing.vue
│       │   ├── Login.vue
│       │   ├── Register.vue              # auto-registro de cliente
│       │   ├── Home.vue
│       │   ├── MisCotizaciones.vue       # cliente: listar/ver/aprobar/rechazar las suyas
│       │   ├── CotizacionDetalle.vue
│       │   ├── MisEncomiendas.vue        # cliente: seguimiento de sus envíos
│       │   ├── EncomiendaSeguimiento.vue # historial de estados (guía de rastreo)
│       │   ├── MisPagos.vue              # cliente: pagar con QR (cuotas)
│       │   ├── PagoQR.vue                # modal QR
│       │   ├── Dashboard.vue             # panel admin/vendedor
│       │   ├── DashboardUsuarios.vue
│       │   ├── DashboardCatalogo.vue     # categorías + productos (tabs)
│       │   ├── DashboardAlmacenes.vue
│       │   ├── DashboardInventario.vue
│       │   ├── DashboardCotizaciones.vue
│       │   ├── DashboardEncomiendas.vue
│       │   ├── DashboardVentas.vue
│       │   ├── DashboardPagos.vue
│       │   ├── DashboardFacturas.vue
│       │   ├── DashboardReportes.vue
│       │   ├── DashboardPermisos.vue     # matriz de acceso (rol × recurso)
│       │   └── DashboardBitacora.vue
│       ├── Componentes/           # (antes Components) — ToastHost, ConfirmDialog
│       └── servicios/             # (antes composables)
│           ├── useApi.js          # cliente HTTP con token JWT
│           ├── useAuth.js          # sesión/permiso, rehidratación
│           └── useUI.js            # toasts + confirm (sin alert/confirm nativos)
├── routes/
│   ├── web.php                    # solo Inertia::render de cada página
│   ├── api.php                    # endpoints REST (públicos + auth:api + CheckRole)
│   └── console.php
├── public/
│   ├── css/styles.css
│   └── images/productos/
└── composer.json
```

**Convenciones heredadas del ejemplo:**
- `web.php` **solo** hace `Inertia::render('Pagina')`. **Toda** la lógica vive en
  `api.php` con controllers REST.
- Autenticación **JWT** (`auth:api`) con expiración de **30 min**; el `rol` viaja en el
  payload del token.
- Control de acceso por **middleware `CheckRole`** parametrizado con los roles
  permitidos por ruta (ej. `->middleware('checkRole:admin')`).
- Modelos Eloquent con `protected $table` en **singular**, `public $timestamps = false`
  donde aplica, `protected $primaryKey = 'id'` (o PK compuesta donde corresponda).
- Frontend consume la API con el composable `useApi.js` (inyecta el token JWT).
- Contador de visitas: endpoint POST `/api/visitas/{pagina}` que hace upsert del
  contador.

---

## 2. Flujo de Trabajo: Captura de Requisitos

### 2.1 Identificar Actores

| Actor | Tipo | Descripción | Responsabilidades |
|-------|------|-------------|-------------------|
| **Administrador** | Humano (rol `admin`) | Control gerencial absoluto. Sinónimos: `admin`, `administrador`, `propietario`. | Gestionar usuarios y almacenes, administrar catálogo (categorías/productos), inventario, cotizaciones, encomiendas, ventas, pagos, reportes y la matriz de acceso. |
| **Vendedor** | Humano (rol `vendedor`) | Motor operativo. Sinónimo: `operador`. | Registrar inventario, crear cotizaciones, generar encomiendas y notas de venta, registrar pagos, consultar reportes. |
| **Cliente** | Humano (rol `cliente`) | Consumidor del servicio de envío. | Consultar **sus** cotizaciones, **aprobar/rechazar** cotizaciones, seguir **sus** encomiendas y pagar con **QR** (incluido en cuotas). |
| **Sistema PagoFácil** | Externo no humano | Pasarela de pagos (callback/webhook). | Procesar el QR, validar la transacción y confirmar el pago de forma asíncrona. |

> **Requisito web 2** ("administrador no es un rol del negocio"): el rol `admin` hace de
> administrador de la plataforma. Roles del negocio = **Vendedor** y **Cliente** →
> cumple el mínimo de dos roles del negocio.

### 2.2 Casos de Uso

| ID | Caso de Uso | Módulos de la spec |
|----|-------------|--------------------|
| CU1 | Gestión de Usuarios | Usuarios |
| CU2 | Gestión de Catálogo (Categorías + Productos) | Categorías, Productos |
| CU3 | Gestión de Almacenes e Inventario | Almacenes, Inventario |
| CU4 | Gestión de Cotizaciones (incluye aprobación del cliente) | Cotizaciones |
| CU5 | Gestión de Encomiendas (incluye seguimiento/historial) | Encomiendas |
| CU6 | Gestión de Ventas (Nota de Venta) | Ventas |
| CU7 | Gestión de Pagos y Facturación (electrónicos + cuotas) | Pagos, Facturas |
| CU8 | Reportes y Estadísticas | Reportes |

> Los requisitos web transversales (bitácora, matriz de acceso, visitas, temas,
> búsqueda) son funcionalidades de plataforma, **no** CU de negocio.

### 2.3 Priorizar Casos de Uso

| ID | Caso de Uso | Actores | Prioridad |
|----|-------------|---------|-----------|
| CU1 | Gestión de Usuarios | Administrador | Alta |
| CU2 | Gestión de Catálogo | Administrador, Vendedor | Alta |
| CU3 | Gestión de Almacenes e Inventario | Administrador, Vendedor | Media |
| CU4 | Gestión de Cotizaciones | Vendedor, Administrador, Cliente | Alta |
| CU5 | Gestión de Encomiendas | Vendedor, Administrador, Cliente | Alta |
| CU6 | Gestión de Ventas | Vendedor, Administrador | Alta |
| CU7 | Gestión de Pagos y Facturación | Vendedor, Administrador, Cliente, PagoFácil | Alta |
| CU8 | Reportes y Estadísticas | Administrador, Vendedor | Baja |

---

## 3. Detalle de los 8 Casos de Uso (web)

> Cada CU describe **acciones de interfaz web** (formularios/vistas Vue) que llaman a
> **endpoints REST** de Laravel, protegidos por `auth:api` (JWT 30 min) + `CheckRole`.

### CU1 — Gestión de Usuarios
- **Descripción:** registrar, modificar, listar, ver o dar de baja usuarios (admin,
  vendedor, cliente) con control de acceso por rol.
- **Actores:** Administrador (gestión completa); Cliente (auto-registro público).
- **Precondición:** acción sobre `usuarios` requiere rol `admin`; el auto-registro de
  cliente es público.
- **Flujo principal:**
  1. El actor abre el formulario (DashboardUsuarios.vue o Register.vue).
  2. Completa datos: CI (solo dígitos, único), nombre/apellido (solo letras), correo
     (único, formato email), teléfono (opcional, solo dígitos), rol, contraseña.
  3. El backend valida (mensajes en español) y la unicidad de CI y correo.
  4. Persiste con `contrasena` **hasheada** (`Hash::make`) y el `rol` canónico.
  5. Registra en **bitácora** y responde JSON de éxito.
- **Postcondición:** usuario activo, puede loguearse.
- **Excepciones:** CI/correo duplicado, rol inválido, datos con formato inválido o
  permiso insuficiente → error en español sin persistir.
- **Notas:** eliminar usuario se hace por **CI** (identificador). Seed inicial: si no
  existe ningún admin, crear uno por defecto al inicializar la BD.

### CU2 — Gestión de Catálogo (Categorías + Productos)
- **Descripción:** administrar categorías y productos (ítems/servicios de envío) y la
  **búsqueda del negocio**. Productos tienen `tipo` ∈ {carga_general, fragil,
  perecedera, peligrosa}.
- **Actores:** Administrador (crear/editar/eliminar), Vendedor (listar/ver).
- **Precondición:** modificar requiere rol `admin`; listar/ver requiere `admin` o
  `vendedor` (el catálogo **no** es público para clientes).
- **Flujo principal:**
  1. El Administrador crea/edita categorías (nombre, descripción) y productos
     (categoría existente, `codigo` único alfanumérico, nombre, descripción,
     `precio_unitario > 0`, `tipo`).
  2. Vendedor/Administrador listan (todos o por `categoria_id`) y ven por `codigo`.
  3. La búsqueda del header consulta `BuscarController` (productos/cotizaciones/
     encomiendas según rol).
- **Postcondición:** catálogo actualizado, disponible para armar cotizaciones.
- **Excepciones:** categoría inexistente, código duplicado, precio ≤ 0 o tipo inválido
  → validación rechaza.

### CU3 — Gestión de Almacenes e Inventario
- **Descripción:** administrar almacenes (CRUD, solo admin) y registrar **movimientos de
  inventario** (INGRESO/SALIDA) por producto-almacén.
- **Actores:** Administrador (almacenes + inventario), Vendedor (inventario).
- **Precondición:** almacenes → rol `admin`; inventario → `admin` o `vendedor`.
- **Flujo principal:**
  1. Admin registra almacén (nombre, dirección, `capacidad > 0`, `responsable_id`
     usuario existente).
  2. Movimiento de inventario (DashboardInventario.vue): producto_id, almacen_id,
     `cantidad > 0`, tipo INGRESO/SALIDA.
     - Si **no existe** el registro (producto, almacén): INGRESO crea con `cantidad`;
       SALIDA → error "no hay stock registrado".
     - Si **existe**: INGRESO suma; SALIDA resta y si queda < 0 → error "stock
       insuficiente".
  3. Listar inventario: todo o por `almacen_id`.
- **Postcondición:** stock actualizado por almacén.
- **Excepciones:** producto/almacén inexistente, stock insuficiente → error en español.

### CU4 — Gestión de Cotizaciones (incluye aprobación del cliente)
- **Descripción:** crear, listar, ver, editar, eliminar cotizaciones de envío y permitir
  al **cliente aprobar/rechazar** las suyas. Incluye **cleanup de vencidas**.
- **Actores:** Vendedor/Administrador (CRUD), Cliente (listar/ver/aprobar/rechazar lo
  suyo).
- **Precondición:** crear/editar → `admin` o `vendedor`; eliminar → solo `admin`;
  aprobar/rechazar → solo el **cliente dueño**.
- **Flujo principal:**
  1. Antes de operar se ejecuta **cleanup**: elimina cotizaciones `PENDIENTE` vencidas
     (`fecha_emision + validez_dias < ahora`) y su detalle.
  2. El Vendedor arma la cotización (cliente, vendedor, datos de envío: remitente,
     destinatario, contenido, origen, destino, `tipo_envio` ∈ {aereo, maritimo,
     terrestre}, `peso_kg`, `volumen_m3`, fechas, impuestos, `validez_dias` 1–7) y la
     lista de productos (`id:cantidad`).
  3. El backend copia `precio_unitario` de cada producto, calcula
     `subtotal = Σ(cantidad × precio)` y `total_estimado = subtotal + impuestos`.
  4. Inserta `cotizacion` (estado `PENDIENTE`) + `detalle_cotizacion` (transacción
     atómica).
  5. El **Cliente** ve sus cotizaciones (MisCotizaciones.vue) y **aprueba** (`si`) o
     **rechaza** (`no`) con observaciones opcionales.
     - Aprobar → estado `APROBADA` y se **registra el `vendedor_id` que atendió + el
       timestamp** (para la ventana de 20 min de CU5).
     - Rechazar → estado `RECHAZADA`.
- **Postcondición:** cotización `PENDIENTE`/`APROBADA`/`RECHAZADA`. Solo se puede editar
  o eliminar en `PENDIENTE`.
- **Excepciones:** cliente/vendedor con rol incorrecto, producto inexistente, validez
  fuera de 1–7, fechas mal formateadas, lista vacía, o el cliente intenta aprobar una
  cotización ajena → error. Editar/eliminar fuera de `PENDIENTE` → error.

### CU5 — Gestión de Encomiendas (incluye seguimiento/historial)
- **Descripción:** generar la **encomienda** a partir de una cotización **APROBADA**,
  listarla, verla y actualizar su **estado/datos** registrando el **historial**.
- **Actores:** Vendedor/Administrador (crear/actualizar), Cliente (listar/ver lo suyo).
- **Precondición:** la cotización existe, **pertenece al cliente** y está **APROBADA**,
  con todos los datos de envío completos.
- **Flujo principal:**
  1. Crear encomienda: entradas `cliente_id` y `guia_rastreo` (= **id de la cotización
     aprobada**).
  2. **Ventana de asignación de 20 minutos:** si pasaron **menos de 20 min** desde la
     aprobación y el actor **no** es el vendedor que la atendió **ni** un admin → error
     ("reservada a ese vendedor, faltan N min"). Pasados los 20 min, o si es el vendedor
     original / un admin → se permite y se limpia el registro.
  3. Se **copian** todos los datos de envío de la cotización; `guia_rastreo` = id de la
     cotización (única); `fecha_registro` = ahora; estado inicial `REGISTRADA`.
  4. La cotización pasa a **COMPLETADA**.
  5. Actualización: cambio de estado (REGISTRADA → EN_TRANSITO → ENTREGADA, etc.) que
     escribe una fila en `historial_estado` (anterior→nuevo, fecha, observaciones), y/o
     edición de datos de envío. Se pueden combinar.
  6. El Cliente sigue su envío por **guía de rastreo** (EncomiendaSeguimiento.vue), que
     muestra el historial de estados.
- **Postcondición:** encomienda registrada y trazable; cotización `COMPLETADA`.
- **Excepciones:** cotización inexistente/ajena/no aprobada, guía duplicada o ventana de
  20 min vigente para otro actor → error.

### CU6 — Gestión de Ventas (Nota de Venta)
- **Descripción:** generar la **nota de venta** sobre una encomienda, contado o crédito
  en cuotas.
- **Actores:** Vendedor, Administrador.
- **Precondición:** existe la encomienda (y su cotización ligada); rol `admin`/`vendedor`.
- **Flujo principal:**
  1. Entradas: cliente_id, vendedor_id, encomienda_id, `fecha_venta` (`*` = hoy),
     `impuestos` (`*` = 0), `descuento` (`*` = 0), `tipo_pago` ∈ {CONTADO, CREDITO},
     `numero_cuotas` (**obligatorio y ≥ 2 si CREDITO**; 1 si CONTADO).
  2. Resuelve `cotizacion_id` desde la encomienda; `subtotal = cotizacion.total_estimado`.
  3. `total_final = subtotal + impuestos − descuento` (si < 0 → error "descuento mayor
     al total").
  4. Inserta `venta` con estado **PENDIENTE** y genera `codigo = "tecnoSa-<id>"`
     (transacción atómica). Opcionalmente puebla `detalle_venta` copiando el detalle de
     la cotización (trazabilidad por producto).
- **Postcondición:** venta `PENDIENTE`, lista para cobrar (CU7).
- **Excepciones:** cliente/vendedor con rol incorrecto, encomienda inexistente,
  descuento mayor al total, o `numero_cuotas < 2` en crédito → error.

### CU7 — Gestión de Pagos y Facturación (Pagos Electrónicos + Cuotas)
- **Descripción:** registrar **pagos** (EFECTIVO o QR), actualizar el estado de la venta
  y **emitir una factura por cada pago**. Integra **PagoFácil** para el QR (callback).
- **Actores:** Vendedor/Administrador (EFECTIVO o QR), Cliente (**solo QR**), Sistema
  PagoFácil.
- **Precondición:** la venta existe y no está `PAGADA`. El Cliente solo paga **sus**
  ventas y **solo por QR**.
- **Flujo principal:**
  1. Entradas: venta_id, `monto > 0`, `metodo_pago` ∈ {EFECTIVO, QR}, `numero_cuota`
     (opcional, ≤ `numero_cuotas` de la venta), `referencia` (opcional).
  2. **Pago EFECTIVO** (vendedor/admin): inserta `pago` (estado `REGISTRADO`),
     recalcula `total_pagado = Σ pagos`, fija estado de venta = `PAGADA` si
     `total_pagado ≥ total_final`, si no `PARCIAL`, y **genera la factura**
     `FAC-<ventaId>-<pagoId>` (subtotal = total = monto). Todo en una transacción.
  3. **Pago QR** (cliente o vendedor): `PagoController` genera el QR vía PagoFácil y
     registra el cobro como pendiente; PagoFácil confirma vía **callback**
     (`PagoController@callback`), que dispara la misma lógica del paso 2 (pago + factura
     + actualización de estado).
- **Postcondición:** pago `REGISTRADO`; factura `EMITIDA`; venta `PARCIAL`/`PAGADA`.
- **Excepciones:** venta inexistente, cliente intentando pagar en efectivo, `numero_cuota`
  mayor a las cuotas de la venta, firma de callback inválida → rechaza.
- **Facturas:** no hay alta manual; se generan solas con cada pago. Solo listar/ver (para
  comprobantes y reportes).

### CU8 — Reportes y Estadísticas
- **Descripción:** análisis gerencial y **estadísticas de acceso** (de la bitácora).
  Export a PDF con dompdf. Cada generación se registra en `reporte`.
- **Actores:** Administrador (todos); Vendedor (ventas, encomiendas, cotizaciones,
  facturas; no inventario en el menú).
- **Precondición:** rol con permiso sobre `reportes`. Rango `fecha_inicio, fecha_fin` o
  `*` (todo el historial).
- **Tipos de reporte:**
  - **REPVEN** (Ventas): total, monto, # pagadas/parciales/pendientes, total cobrado,
    desglose por tipo de pago, últimas 50.
  - **REPENC** (Encomiendas): total, distribución por estado, últimas 50.
  - **REPINV** (Inventario): total de productos, unidades, stock por producto (menor a
    mayor, con nivel). Ignora el rango de fechas (estado actual).
  - **REPCOT** (Cotizaciones): total, **tasa de conversión** (% aprobadas), desglose por
    estado con monto, últimas 50.
  - **REPFAC** (Facturas): total, monto, desglose por método de pago, últimas 50.
  - **Estadísticas de acceso:** de la bitácora (logins, recursos más accedidos).
- **Flujo principal:** selecciona reporte (DashboardReportes.vue) → `ReporteController`
  ejecuta agregaciones sobre PostgreSQL → tablas/gráficos + export PDF. Solo lectura.
- **Postcondición:** informe mostrado y registrado en `reporte`; no altera datos de
  negocio.
- **Excepciones:** rol sin permiso → bloqueo + registro en bitácora.

---

## 4. Requisitos Web Transversales (no son CU de negocio)

1. **Diseño y navegación** — todos los elementos vistos en clase (AppLayout, menús,
   breadcrumbs).
2. **Roles + Menú dinámico** — ≥2 roles del negocio (Vendedor, Cliente); menú de
   navegación construido **desde BD** según el rol (recurso/permiso).
3. **MVC-MVVM** — Laravel (MVC) + Inertia + Vue (MVVM).
4. **Control de Acceso (Matriz) + Bitácora** — ver nota abajo.
5. **Estilo único + 3 temas + accesibilidad** — un CSS; temas *niños*, *jóvenes/adultos*,
   *Día/Noche según horario del cliente*; accesibilidad (tamaño de letra + contraste).
6. **Validación de entradas** — todos los formularios, mensajes en español (FormRequests).
7. **Contador de visitas por página** — tabla `visitas`, mostrado en el pie de cada página.
8. **Estadísticas del negocio y de acceso** — CU8 + analítica de bitácora.
9. **Búsqueda del negocio** — campo de texto en el encabezado (encomiendas por guía,
   cotizaciones, productos según rol).
10. **Pagos electrónicos** — pagos EFECTIVO/QR, facturación automática y crédito en
    cuotas (CU7).

> **Nota sobre el Requisito 4 (matriz de acceso + bitácora):**
> `CheckRole` es la primera barrera por rol (admin/vendedor/cliente). Además se mantienen
> las tablas `recurso` + `permiso` (matriz consultable/configurable, rol × recurso:
> ver/crear/editar/eliminar) para el control fino y para mostrar/administrar la matriz en
> el dashboard, y `bitacora` para auditoría (logins, accesos a recursos, acciones).

---

## 5. Modelo de Datos

> Las **14 tablas del core** (de la spec) se conservan. La ampliación web es
> **incremental**: `recurso`, `permiso`, `bitacora`, `visitas`. En migraciones Laravel:
> una migración por tabla.

### 5.1 Tablas del Core (de la especificación)

| Tabla | Notas para la web |
|-------|-------------------|
| `usuario` | `ci` (único, dígitos), `correo` (único), `contrasena` **hasheada**, `rol` ∈ {admin, vendedor, cliente}; implements JWTSubject; `rol` al payload |
| `categoria` | nombre, descripcion |
| `producto` | `codigo` único alfanumérico, `precio_unitario > 0`, `tipo` ∈ {carga_general, fragil, perecedera, peligrosa} |
| `almacen` | `capacidad > 0`, `responsable_id` → usuario |
| `inventario` | **PK compuesta** (producto_id, almacen_id), `cantidad ≥ 0`, `stock_minimo` |
| `cotizacion` | datos de envío + `estado` ∈ {PENDIENTE, APROBADA, RECHAZADA, VENCIDA, COMPLETADA}; `validez_dias` 1–7; `subtotal`/`total_estimado` calculados |
| `detalle_cotizacion` | **PK compuesta** (cotizacion_id, producto_id); precio copiado; `subtotal = cantidad × precio` |
| `encomienda` | `guia_rastreo` única (= id cotización); datos **copiados** de la cotización; flujo de estados de envío |
| `historial_estado` | auditoría de estados de encomienda (anterior→nuevo, fecha, observaciones) |
| `venta` | `codigo = "tecnoSa-<id>"`; `estado` ∈ {PENDIENTE, PARCIAL, PAGADA}; `tipo_pago` ∈ {CONTADO, CREDITO}; `numero_cuotas`; `total_final = subtotal + impuestos − descuento` |
| `detalle_venta` | **PK compuesta** (venta_id, producto_id); opcional (trazabilidad) |
| `pago` | `estado = REGISTRADO`; `metodo_pago` ∈ {EFECTIVO, QR}; `monto > 0`; `numero_cuota` opcional; `referencia` opcional |
| `factura` | **una por pago**; `numero_factura = "FAC-<ventaId>-<pagoId>"` (único); subtotal = total = monto; `estado = EMITIDA` |
| `reporte` | registro de reportes (`propietario_id`, periodo, `tipo_reporte` ∈ {REPVEN, REPENC, REPINV, REPCOT, REPFAC}) |

### 5.2 Tablas Nuevas (ampliación web)

| Tabla | Propósito |
|-------|-----------|
| `recurso` | Módulos navegables (matriz de acceso) → alimenta el menú dinámico |
| `permiso` | Matriz de acceso (rol × recurso): ver/crear/editar/eliminar |
| `bitacora` | Auditoría: login_ok/fallido/logout/acceso_recurso/accion, ip, user_agent, fecha |
| `visitas` | Contador por página (`pagina` UNIQUE, `contador`) |

### 5.3 Fórmulas de cálculo

**Cotización:**
```
detalle.subtotal            = detalle.cantidad × producto.precio_unitario
cotizacion.subtotal         = Σ detalle.subtotal
cotizacion.total_estimado   = subtotal + impuestos
```
**Venta:**
```
venta.subtotal      = cotizacion.total_estimado   (de la cotización ligada a la encomienda)
venta.total_final   = subtotal + impuestos − descuento     (si < 0 → error)
```
**Pago / estado de venta:**
```
total_pagado = Σ pagos de la venta
estado_venta = (total_pagado ≥ venta.total_final) ? "PAGADA" : "PARCIAL"
```
**Factura (una por pago):**
```
factura.subtotal = factura.total = pago.monto
factura.numero   = "FAC-<ventaId>-<pagoId>"
```
> Cálculos monetarios con **decimal de precisión fija** (no float), redondeo HALF_UP a 2
> decimales.

### 5.4 Máquinas de estado (reglas de negocio)
- **Cotización:** `PENDIENTE` → `APROBADA` → `COMPLETADA`; `PENDIENTE` → `RECHAZADA`;
  `PENDIENTE` vencida → eliminada por cleanup (`/VENCIDA`). Solo se edita/elimina en
  `PENDIENTE`.
- **Encomienda:** `REGISTRADA` → `EN_TRANSITO` → `ENTREGADA` (flujo libre); cada cambio
  va a `historial_estado`.
- **Venta:** `PENDIENTE` → `PARCIAL` → `PAGADA` (o `PENDIENTE` → `PAGADA` si un pago
  cubre el total).
- **Inventario:** stock por (producto, almacén) nunca negativo.

---

## 6. Arquitectura MVC-MVVM (tres capas)

```
┌──────────────────────────────────────────────────────────┐
│ PRESENTACIÓN (MVVM)  — resources/js                      │
│   Vue Vistas + AppLayout  ←→  Inertia  ←→  rutas web       │
│   useApi.js (token JWT) → consume api.php                  │
│   Temas CSS, accesibilidad, búsqueda (header),            │
│   contador de visitas (footer)                            │
└──────────────────────────────────────────────────────────┘
                       │ Inertia (props) / fetch JSON
┌──────────────────────────────────────────────────────────┐
│ NEGOCIO (MVC - Laravel)  — app/Http, app/Providers       │
│   Controllers REST → (Services) → Models                  │
│   Middleware: auth:api (JWT 30 min), CheckRole,           │
│               RegistrarBitacora, ContadorVisitas,         │
│               HandleInertiaRequests                       │
│   FormRequests (validación, mensajes ES)                  │
│   PagoController ↔ PagoFácil + callback                    │
│   Transacciones atómicas: cotización+detalle,             │
│     venta+código, pago+factura+estado                     │
└──────────────────────────────────────────────────────────┘
                       │ Eloquent ORM
┌──────────────────────────────────────────────────────────┐
│ DATOS  — PostgreSQL (18 tablas: 14 core + 4 ampliación)  │
└──────────────────────────────────────────────────────────┘
```

---

## 7. Notas de Implementación para Claude Code

- **Rutas:** `web.php` solo `Inertia::render`. Lógica en `api.php` (públicas →
  `auth:api` → `CheckRole:rol`).
- **Auth JWT (30 min):** `Usuario implements JWTSubject`, `getJWTCustomClaims()` retorna
  `['rol' => ...]`; TTL = 30 minutos. Login registra `login_ok`/`login_fallido` en
  bitácora. **Contraseñas hasheadas** (bcrypt/argon2).
- **Roles canónicos:** normalizar a minúsculas y aceptar sinónimos (`administrador`/
  `propietario` → `admin`; `operador` → `vendedor`).
- **Filtrado por dueño (cliente):** el cliente solo lista/ve/aprueba/paga **lo suyo**
  (filtro por `cliente_id` en cada consulta).
- **Cleanup de cotizaciones vencidas:** ejecutar antes de las operaciones de cotización
  (o como job programado en `console.php`).
- **Ventana de 20 min (encomienda) y sesión de 30 min:** usar timestamps persistidos
  (en BD; opcionalmente Redis) — al aprobar se guarda `vendedor_id` + momento de
  aprobación.
- **Matriz de acceso:** `CheckRole` como primer filtro por rol; consulta a `permiso`
  para control fino por recurso/acción; el Administrador administra la matriz desde
  `DashboardPermisos.vue`.
- **Menú dinámico:** la navegación se construye desde `recurso` + `permiso` según el rol.
- **Bitácora:** middleware `RegistrarBitacora` registra accesos a recursos y acciones
  (con ip + user_agent) para "recursos más accedidos".
- **Contador de visitas:** endpoint POST `/api/visitas/{pagina}` (upsert) + valor
  compartido al footer vía Inertia shared props o fetch desde AppLayout.
- **Validación:** FormRequests con `messages()` en español en TODOS los formularios
  (CI/teléfono solo dígitos; nombre/apellido/contenido solo letras; remitente/
  destinatario/origen/destino alfanuméricos sin símbolos; `validez_dias` 1–7; tipos y
  enums según §4 de la spec).
- **Temas/accesibilidad:** `app.css` con CSS custom properties; selector de tema (niños /
  jóvenes-adultos / día-noche) persistido por usuario o en localStorage; modo Día/Noche
  automático según hora del cliente (JS) con override manual; controles de tamaño de
  letra y contraste.
- **Búsqueda:** input en el header de AppLayout que consulta `BuscarController`
  (encomiendas por guía, cotizaciones, productos — filtrado por rol).
- **Pagos:** integración con PagoFácil en `PagoController` (generarQR + callback +
  consultarEstado). El pago QR del callback dispara pago + factura + actualización de
  estado, igual que el pago EFECTIVO.
- **Transacciones:** atómicas en operaciones compuestas (cotización+detalle,
  venta+código, pago+factura+estado).
- **Reportes:** dompdf para export PDF (ya está en el stack del ejemplo).
- **Seguridad:** credenciales en `.env`, nunca en código.
- **Sin correo:** ninguna funcionalidad depende de email. Donde la spec dice "notificar
  por correo", aquí se devuelve el resultado en la respuesta HTTP / en pantalla.

---

## 8. Qué se obtendrá por Ingeniería Inversa (diagramas)

No se describen aquí en detalle gráfico; se generarán a partir del código:
- Diagramas de **Colaboración** (CU1–CU8)
- Diagramas de **Secuencia** (CU1–CU8)
- Diagrama de **Clases** (modelos Eloquent + relaciones)
- Diagrama de **Despliegue** (servidor web + app Laravel + PostgreSQL + callback PagoFácil)
- Diagrama de **Componentes/Paquetes** (subsistemas)
- Diseño **procedimental** de flujos complejos (cotización→encomienda con ventana de 20
  min, pago QR con callback, crédito en cuotas, cleanup de vencidas)

---

## 9. Estado de Implementación y Decisiones (autoritativo)

> Esta sección refleja **lo realmente construido** y **todas las decisiones tomadas**
> durante el desarrollo. Ante cualquier diferencia con las §1–§8, **manda la §9**.
> Cumple los **10 requisitos** del proyecto y la **arquitectura de 3 capas**.

### 9.1 Entorno real

- **PHP 8.4** + Laravel 12, **PostgreSQL 16** (BD `servicargo_db`).
- **Node 22**, Vite 7. Front: Inertia 2 + Vue 3 + Ziggy + **Lucide** (`lucide-vue-next`).
- Autenticación **JWT con expiración de 30 min** (`tymon/jwt-auth`), `rol` en el payload.

### 9.2 Cómo correr el proyecto (IMPORTANTE)

```bash
# UN solo comando (recomendado): Vite recompila al guardar + Laravel
npm run start          # = concurrently: "vite build --watch" + "php artisan serve"

# o en dos terminales:
npm run watch          # vite build --watch  (recompila al guardar)
php artisan serve      # http://127.0.0.1:8000
```

- Si la ruta del proyecto contiene `#` u otros caracteres conflictivos, **NO usar
  `npm run dev`** (dev server de Vite): rompe la resolución de módulos (ver §9.7). Usar
  `npm run start`.
- Si aparece pantalla blanca: `rm -f public/hot` (apunta al dev server roto).
- **No hay HMR** con `build --watch`: tras guardar, refrescar el navegador.

### 9.3 Pagos — decisiones finales (sobre CU7)

- **Métodos de pago: `EFECTIVO` y `QR`.** El cliente **solo** paga por QR; el efectivo lo
  registra un vendedor/admin.
- **Crédito en cuotas:** `numero_cuotas ≥ 2` (definido por el vendedor al crear la venta).
  Cada pago puede indicar `numero_cuota` (≤ `numero_cuotas`).
- **Una factura por cada pago** (`FAC-<ventaId>-<pagoId>`), generada automáticamente en la
  misma transacción que el pago.
- **PagoFácil corre en modo simulado** (sin credenciales reales en `.env`). El QR es un
  placeholder y se confirma con `POST /api/pagos/{pago}/simular-confirmacion`, que dispara
  la misma lógica del callback (pago + factura + estado de venta).

### 9.4 Catálogo: imágenes y rutas (clave para el despliegue)

- **El frontend nunca arma rutas de imagen.** El backend devuelve la **URL absoluta** en
  `imagen_url` (accessor `Producto::getImagenUrlAttribute()` con `asset()`, basado en
  **`APP_URL`**). Funciona en cualquier dominio/subcarpeta.
- **Al desplegar en el servidor del docente:** poner `APP_URL` con la URL real, luego
  `php artisan config:clear && php artisan migrate --seed && npm run build`.
  `public/images/productos/` debe ser **escribible** (para las subidas).
- **Subida desde el panel** (`DashboardCatalogo.vue`): producto vía `multipart/form-data`
  (con `_method=PUT` al editar). El controlador guarda en `public/images/productos/` como
  `prod-xxxx.{ext}` (JPG/PNG/WEBP, máx 2 MB). Validado en `ProductoRequest`.

### 9.5 UI / Diseño — decisiones

- **Paleta logística** (azul/teal corporativo + acento ámbar para acciones), en
  `resources/css/app.css` (custom properties por tema). **Contraste verificado WCAG AA**
  en los 4 temas (Día/Noche/Niños/Jóvenes).
- **Cero emojis** en la UI: siempre iconos **Lucide**.
- **Favicon** propio (paquete/caja sobre círculo azul) — `public/favicon.ico` +
  `public/favicon.svg` + `theme-color`, referenciados en `resources/views/app.blade.php`.
- **Sin `alert()`/`confirm()`** en ningún lado. UI transversal en
  `servicios/useUI.js`: `useToast()` (éxito/error/info) y `useConfirm()`
  (`await confirmar({...})` → `Promise<boolean>`). Hosts `Componentes/ToastHost.vue` y
  `Componentes/ConfirmDialog.vue`, montados una vez en `AppLayout.vue`.
- **`lang/es.json`** traduce los mensajes agregados de validación de Laravel. En forms se
  muestran errores **por campo**, no el mensaje agregado.

### 9.6 Backend — decisiones

- **Soft delete** en lo que se elimina y debe conservar historial (`Categoria`,
  `Producto`, `Almacen`) usando `SoftDeletes` (`deleted_at`); deja de listarse pero no
  rompe cotizaciones/ventas previas.
- **Sesión persistida:** `servicios/useAuth.js` guarda usuario + permisos en
  `localStorage` (`sc_sesion`) y los **rehidrata de forma síncrona** al cargar la app;
  luego revalida contra `/auth/me`. El token JWT respeta los **30 min** de expiración.
- **Cleanup de cotizaciones vencidas** centralizado en `CotizacionController` (se ejecuta
  antes de crear/listar/ver/editar/aprobar) — o vía comando programado.
- **Ventana de 20 min** de la encomienda: se valida con el `vendedor_id` y el timestamp
  guardados al aprobar la cotización.

### 9.7 Bug conocido: Vite + caracteres especiales en la ruta

- El dev server de Vite (`npm run dev`) **no funciona** si la ruta del proyecto contiene
  `#` (sirve los módulos sin transformar: `@vite/client` 404, "Failed to resolve module
  specifier 'vue'"). No se arregla por config.
- **Solución adoptada:** `npm run start` (Vite `build --watch` + `php artisan serve`).
  Recompila al guardar; el navegador se refresca a mano.
- **Única forma de tener HMR real:** renombrar la carpeta sin `#`.

### 9.8 Páginas: consolidaciones vs. la §1

Algunas vistas listadas en §1 están **consolidadas**, no faltantes:
- **QR de pago** (`PagoQR`) → es un **modal dentro de `MisPagos.vue`**.
- `DashboardCategorias` + `DashboardProductos` → unificados en `DashboardCatalogo.vue`
  (tabs).

### 9.9 Usuarios demo (seeder)

Contraseña de todos: `password`.
`admin@servicargo.bo` (Administrador) · `vendedor@servicargo.bo` (Vendedor) ·
`cliente@servicargo.bo` (Cliente).
Seed inicial: si no existe ningún admin, se crea uno por defecto.

### 9.10 Pendientes (priorizados)

**Alto (entregable / despliegue):**
- **Diagramas por ingeniería inversa** (§8): clases, secuencia y colaboración (CU1–CU8),
  despliegue, componentes. Generar desde el código (Mermaid/PlantUML). *Principal pendiente.*
- **Despliegue** en el servidor del docente (ver §9.4, `APP_URL`).

**Medio:**
- **Breadcrumbs** (elemento de navegación de la rúbrica).
- **Gráficos** en reportes (hoy: tablas + export PDF con dompdf).
- **PagoFácil real** (hoy simulado; cargar credenciales `PAGOFACIL_*` si lo exigen).

**Bajo:**
- Pantalla dedicada de **seguimiento de encomienda** ya disponible
  (`EncomiendaSeguimiento.vue`); pulir el timeline del historial.
- **Tests** (solo existe `ExampleTest`).
