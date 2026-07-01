# Edición de eventos con permisos por rol

**Fecha:** 2026-06-30
**Estado:** Aprobado (diseño)

## Contexto

En la rama `worktree-edit-event-and-participants-fix` ya existe una primera
versión de "editar evento" que permite al **organizador** editar *todos* los
campos (incluido el enlace/slug). El requisito nuevo cambia eso: la edición
debe respetar el rol del usuario.

- **Admin:** puede editar **todos** los campos de cualquier evento.
- **Organizador:** puede editar solo **fecha del evento, fecha límite de pago y
  monto total** de sus propios eventos. El **comprobante del gasto** (llamado
  "voucher" por el usuario) ya es editable en la pantalla de revisión y no se
  toca aquí.

Este diseño **reemplaza** la edición amplia del organizador por una edición
restringida por rol, reutilizando una sola pantalla.

## Objetivo

Una pantalla de edición única, consciente del rol, con la lista de campos
editables **forzada en el servidor** (nunca se confía en el cliente).

## Alcance de campos por rol

| Campo | Admin | Organizador |
|---|---|---|
| `name` (nombre) | ✅ | ❌ |
| `event_date` (fecha del evento) | ✅ | ✅ |
| `pay_deadline` (fecha límite) | ✅ | ✅ |
| `total_amount` → `total_cents` (monto) | ✅ | ✅ |
| `headcount` (n° personas) | ✅ | ❌ |
| `recipient_name` / `recipient_handle` | ✅ | ❌ |
| `accepted_methods` (métodos) | ✅ | ❌ |
| `slug` (enlace público) | ✅ | ❌ |
| Comprobante del gasto | (se mantiene en Revisión) | ✅ (ya existe en Revisión) |

Recalcular `share_cents` cuando cambien `total_amount` o `headcount`. Los pagos
ya aprobados conservan su monto real (leído del voucher), así que "Cobrado" no
se ve afectado.

## Autorización

- **`EventPolicy@manage`**: se amplía de "solo el dueño" a **"admin O dueño"**.
  - Admin → puede gestionar/editar cualquier evento.
  - Organizador → solo sus propios eventos (403 en ajeno).
- **Whitelist de campos por rol (servidor):** en la actualización se construye
  el array de cambios **solo** con los campos permitidos para el rol del
  usuario. Si un organizador envía campos fuera de su lista (ej. `slug`,
  `name`) en una petición fabricada, esos campos se **ignoran silenciosamente**
  (no se validan ni se aplican). Decisión de producto aprobada: ignorar, no
  rechazar, porque el frontend no los muestra.

## Backend

### `UpdateEventRequest`
- `authorize()`: `user()->can('manage', $event)`.
- `rules()`: condicionales por rol.
  - Reglas comunes (ambos roles): `event_date` (date), `pay_deadline` (date,
    sin `after_or_equal:today` — en edición se permite un plazo ya vencido),
    `total_amount` (numeric, 0.01–9999999.99).
  - Solo admin: `name`, `headcount`, `recipient_name`, `recipient_handle`,
    `accepted_methods` (+ `accepted_methods.*`), `slug` (min 4, max 40,
    `regex:/^[a-z0-9-]+$/`, único ignorando el propio evento).
- `editableKeys()`: helper que devuelve la lista de claves permitidas según
  rol, usada tanto por `rules()` como por el controlador al aplicar cambios.

### `EventController@edit`
- Autoriza `manage`.
- Renderiza `Events/Edit` con los valores actuales **y** `can_edit_all`
  (booleano = `auth()->user()->isAdmin()`), para que la vista sepa qué mostrar.

### `EventController@update`
- Recibe `UpdateEventRequest` (ya validado).
- Arma `$changes` **solo** con las claves de `editableKeys()`.
- Si `total_amount` o `headcount` están entre los cambios → recalcula
  `share_cents = Event::shareFor($totalCents, $headcount)`.
- `redirect()->route('organizer.events.review', $event)`.

## Frontend (`Events/Edit.vue`)

- Nueva prop `can_edit_all: Boolean`.
- Organizador (`can_edit_all = false`): muestra solo **fecha del evento, fecha
  límite y monto**. La vista previa de "cada persona paga" usa el headcount
  actual (no editable).
- Admin (`can_edit_all = true`): muestra todos los campos, incluido el enlace
  con su aviso de que el link anterior dejará de funcionar.
- El envío (`form.put`) manda solo los campos visibles; el servidor igual
  reaplica la whitelist.

## Punto de entrada del admin

- En el dashboard de admin de eventos (`Admin/Dashboard.vue` +
  `Admin\DashboardController`) se agrega un enlace **"Editar"** por evento que
  apunta a `/events/{slug}/edit` (misma ruta compartida). Requiere exponer el
  `slug` en el payload de eventos del dashboard si aún no está.
- El organizador ya tiene su enlace "Editar" en la pantalla de revisión.

## Ruta

Se reutiliza lo existente (grupo `auth`, nombre `organizer.`):
- `GET /events/{event}/edit` → `edit`
- `PUT /events/{event}` → `update`

No se gatea por rol en la ruta (solo `auth`); la Policy `manage` decide el
acceso. El admin usando una ruta con nombre `organizer.` es aceptable porque no
hay middleware de rol separado.

## Casos borde / pruebas

1. Organizador edita su evento: cambia fechas y monto → OK; `share_cents`
   recalculado con headcount existente.
2. Organizador intenta cambiar `name`/`slug`/`headcount` vía petición
   fabricada → esos campos se ignoran; el resto (si válido) se aplica.
3. Organizador intenta editar evento ajeno → 403.
4. Admin edita cualquier evento (propio o ajeno) → puede cambiar todos los
   campos, incluido `slug` (con unicidad).
5. Admin cambia `slug` a uno ya usado → error de validación.
6. `pay_deadline` en el pasado en edición → permitido.
7. Cambiar `total_amount` (organizador) → recalcula cuota; pagos aprobados
   conservan su monto.

## Fuera de alcance

- Gestión de comprobante del gasto desde la pantalla de edición (sigue en
  Revisión).
- Cualquier refactor no relacionado.
- Auditoría/registro de cambios (posible v2).
