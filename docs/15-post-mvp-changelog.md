# 15 — Cambios post-MVP, estado actual y pendientes

> Documento vivo. Resume todo lo construido **después** del MVP original
> (docs 01–14) y deja anotado qué falta. Pensado para retomar sin perder
> contexto.

Última actualización: **2026-06-30**. Rama: `main`. Repo:
`github.com/carlos3434/cuentaClara`.

---

## 1. Despliegue y CI/CD

- **Dockerfile** multi-stage (Node compila assets → imagen PHP 8.3 + Tesseract +
  `pdo_pgsql` + `gosu`). Defaults de prod por `ENV`: `APP_ENV=production`,
  `APP_DEBUG=false`, `QUEUE_CONNECTION=sync`, `AI_DRIVER=ocr`. La conexión de BD
  y el disco de recibos **no** se fijan en la imagen: los decide el env de Render.
- **`docker/entrypoint.sh`**: arranca como **root** → si hay `RECEIPTS_DISK_ROOT`,
  crea ese dir en el disco persistente y lo `chown` a `www-data` → **baja a
  `www-data` con `gosu`** → (solo si SQLite) ensure file → `config/route/view:cache`
  → `migrate --force` → **crea admin desde `ADMIN_EMAIL`/`ADMIN_PASSWORD`** →
  `storage:link` → `php artisan serve --host=0.0.0.0 --port=$PORT`. La app **nunca
  corre como root**; root solo se usa para preparar el disco recién montado.
- **`APP_KEY` NO** se genera en el Dockerfile (se inyecta como env en Render).
- **Proxy TLS de Render**: `bootstrap/app.php` confía en proxies
  (`X-Forwarded-Proto`) para generar URLs `https` (si no, *mixed content*).
- **CI** (`.github/workflows/ci.yml`): job `test` (Vitest+cobertura → build →
  `php artisan test --coverage --min=90`) y job `deploy` (solo en push a `main`,
  tras tests verdes) que llama a la **API de Render**, crea el deploy y hace
  *poll* hasta `live`. Secrets: `RENDER_API_KEY`, `RENDER_SERVICE_ID`.
- **Persistencia de datos**: ver sección 10 (Postgres administrado + Render
  Persistent Disk para las imágenes).

## 2. Revisión manual vs. automática (`review_mode`)

- Antes la IA falsa auto-validaba todo. Ahora **`review_mode`** (config
  `cuentaclara.review_mode`, env `REVIEW_MODE`, **default `manual`**; el admin lo
  cambia en runtime vía un *setting* global que sobrescribe el config).
- **`manual`**: cada upload queda `submitted` y espera el **"Confirmar pago"** del
  organizador. El OCR solo asiste (llena los campos).
- **`auto`**: el `ReceiptRuleEngine` puede auto-aprobar (monto + método + confianza).
- El upload **siempre** despacha `ValidateReceiptJob` (extracción); el job solo
  aplica el veredicto en modo `auto`.

## 3. Las dos secciones de la revisión (sin solaparse)

- **"Por revisar"** = solo pagos **pendientes** (`submitted`/`needs_review`).
- **"Participantes"** = solo pagos **resueltos**: **Aprobado** (validated/cash) o
  **Rechazado**, con el badge de estado al costado. Un pendiente ya **no** aparece
  en ambas; al resolverlo pasa a "Participantes".
- **Fix**: al aprobar/rechazar/marcar efectivo, la lista de "Participantes" se
  **actualiza al instante** (antes solo salía de "Por revisar" y no reaparecía sin
  recargar). `Review.vue` re-sincroniza la lista con un `watch` sobre la prop y las
  acciones hacen recarga parcial de `['review','participants','summary']`.

## 4. Lectura OCR de comprobantes (Tesseract)

- Driver **`TesseractReceiptVision`** (`AI_DRIVER=ocr`): corre `tesseract -l spa`
  (instalado en la imagen) y delega a **`ReceiptTextParser`** (clase pura).
- El parser está afinado a **5 templates reales** (`resources/payments-examples/`,
  excluidos del build Docker): Yape, Plin BBVA/Interbank/Scotiabank, transferencia
  BCP. Extrae **monto** (ignora la comisión `S/ 0.00`, tolera "5/"), **fecha**
  (meses ES, año opcional, "Jun2026" sin espacio), **método**, **destinatario** y
  **N° de operación** (label + fallback punteado, tolera "N*").
- Validado end-to-end en Docker: los 5 ejemplos extraen todo correctamente.
- `tests/Unit/ReceiptTextParserTest.php` cubre cada template (texto transcrito).

## 5. Totales con montos reales

- "Cobrado" = **suma de los montos reales** de los pagos aprobados
  (`extracted_amount_cents`, o el share si es efectivo/ilegible), no `count × share`.
- "Falta = total − cobrado". Cada fila de "Participantes" muestra su monto.

## 6. Detección de duplicados

- Si dos participantes (mismo evento) suben un voucher con el **mismo N° de
  operación** → "Posible duplicado", `needs_review`, **nunca auto-aprueba**.
- Se compara por **hash** del N° de operación (ver §8), normalizando puntuación.

## 7. Rol Administrador

- `users.role` (`admin`/`organizer`, default organizer) + `users.is_active`.
  Middleware alias **`admin`** (`EnsureUserIsAdmin`).
- Cuentas **inactivas no pueden iniciar sesión**; un admin entra directo a `/admin`.
- **Panel `/admin`**: Dashboard (interruptor global manual/auto + pagos por evento)
  y Usuarios (listar organizadores, **crear**, **activar/desactivar**).
- Tabla **`settings`** (key/value) + `Setting::get/put`. `review_mode` se lee de ahí
  con fallback al config.
- Comando **`php artisan admin:make {email} --name= --password=`** (promueve o crea).
- Vistas: `resources/js/Pages/Admin/Dashboard.vue`, `Users.vue`.
- El admin puede **editar cualquier evento** (todos los campos) desde el enlace
  "Editar" del dashboard — ver §11.

## 11. Edición de eventos por rol

- **Organizador:** edita **fecha del evento, fecha límite y monto** de sus propios
  eventos (el comprobante del gasto se sigue gestionando en la pantalla de revisión).
- **Admin:** edita **todos** los campos de **cualquier** evento: nombre, fechas,
  monto, N° de personas, destinatario, métodos y el **enlace/slug** (con aviso de
  que el link anterior deja de funcionar).
- **Seguridad — whitelist en el servidor**: `UpdateEventRequest::rules()` es
  condicional al rol; como `validated()` devuelve solo las claves con regla, un
  organizador que fuerce campos extra (slug/nombre/personas) los ve **ignorados en
  silencio**, nunca aplicados. El frontend (`can_edit_all`) es solo UX.
- `EventPolicy@manage` ampliada a **admin o dueño** (habilita también revisar/cerrar
  cualquier evento al admin — oversight de plataforma).
- Al cambiar monto o N° de personas se **recalcula la cuota** (`share_cents`); los
  pagos ya aprobados conservan su monto real, así "Cobrado" no se altera.
- Rutas: `GET /events/{event}/edit`, `PUT /events/{event}`. Vista única
  `resources/js/Pages/Events/Edit.vue` (render según `can_edit_all`).

## 8. Sensibilidad de datos (minimización)

Decisiones tomadas (datos de pago = PII financiera, Ley 29733):

- **Borrar la imagen del voucher al resolver el pago** (aprobar/rechazar):
  `ReviewController::purgeImage()` elimina el archivo y limpia `s3_key`.
- **N° de operación hasheado** (`receipts.operation_hash`, SHA-256, normalizado):
  permite detectar duplicados **sin guardar el valor en claro**. Se eliminó la
  columna `extracted_operation` y la fila "N° de operación" de la UI.
  Helper: `Receipt::hashOperation()`.
- **No se persiste el texto OCR crudo** (`ai_raw` ya no guarda `text`).
- **Aviso de privacidad** en la pantalla de subida (`Public/Event.vue`).
- Lo que **sigue en claro**: nombre del participante, nombre del destinatario,
  monto, fecha, método. El voucher está en **disco privado** (nunca público) tras
  `ReceiptStorage`, servido solo al organizador dueño (policy).
- **No** elegido (pendiente si se quiere): cifrado en reposo S3 (SSE).

## 9. Estado de pruebas

- **Backend:** 151 tests (PHPUnit, SQLite en memoria). `php artisan test`.
- **Frontend:** 57 tests (Vitest). `npm run test:js`. Nota: 2 tests de
  `Login.spec.js` fallan por un mock de `usePage` faltante — pendiente menor y
  **no relacionado** con estas features (el resto pasa).
- `phpunit.xml` fija `AI_DRIVER=fake` y `REVIEW_MODE=manual` para que los tests
  sean deterministas sin depender del `.env` local.

## 10. Persistencia en producción (Postgres + disco de Render)

El filesystem de Render es **efímero**: sin esto, cada deploy borraba la BD y las
imágenes subidas. Dos piezas, soportadas en código y **ya configuradas en Render**
(Postgres administrado + Persistent Disk montado y verificado):

- **Blueprint versionado**: `render.yaml` en la raíz describe el web service Docker,
  el Postgres (`cuentaclara-db`, con `DB_URL` inyectado vía `fromDatabase`) y el
  disco `receipts` (mount `/var/data`). Secrets (`APP_KEY`, `APP_URL`,
  `ADMIN_*`) quedan como `sync:false`. Sirve como fuente de verdad reproducible.

- **Base de datos → Postgres administrado.** La imagen trae `pdo_pgsql`. En Render:
  `DB_CONNECTION=pgsql` + `DB_URL` (Internal Connection String del Postgres) — o las
  vars `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`. El entrypoint solo crea el archivo
  SQLite cuando `DB_CONNECTION=sqlite`; con Postgres ese paso se salta y `migrate
  --force` corre contra la BD administrada. Migraciones verificadas portables
  (Blueprint puro, sin SQL crudo ni pragmas).
- **Imágenes de vouchers → Render Persistent Disk.** Disco `persistent` en
  `config/filesystems.php` (driver `local`, raíz = `RECEIPTS_DISK_ROOT`, privado,
  `serve=false`). En Render: adjuntar un disco (mount `/var/data`), `RECEIPTS_DISK=persistent`,
  `RECEIPTS_DISK_ROOT=/var/data/receipts`. **Permisos**: el disco se monta como root,
  por eso el entrypoint corre como root, hace `chown` del dir y baja a `www-data`
  con `gosu` (patrón estándar; Render no documenta el owner del mount). Las imágenes
  se sirven por streaming vía `ReceiptStorage` (route autenticada), nunca por URL
  pública — son PII financiera. Alternativa: `RECEIPTS_DISK=s3` (bucket privado,
  paquete `league/flysystem-aws-s3-v3` ya instalado).
- ⚠️ **Tradeoff del disco**: adjuntar un Persistent Disk **fuerza instancia única**
  (sin escalado horizontal) y **desactiva zero-downtime deploys** (breve corte al
  desplegar). Aceptable para el MVP; para escalar horizontalmente, mover imágenes a S3.
- Nota: con un disco persistente también se podría alojar la **SQLite** ahí y diferir
  Postgres (una pieza menos de infra). Hoy el plan es Postgres + disco para imágenes.

---

## Pendientes / próximos pasos (para el fin de semana)

1. ✅ **Persistencia en Render configurada** (ver §10): Postgres administrado
   (`DB_CONNECTION=pgsql` + `DB_URL`) y Persistent Disk montado en `/var/data`
   (`RECEIPTS_DISK=persistent`, `RECEIPTS_DISK_ROOT=/var/data/receipts`), verificado.
2. **Validación de fecha** del pago contra el rango válido del evento (marcar
   fuera de rango → revisión).
3. **Export CSV** de pagos resueltos para el organizador.
4. **Bitácora de cambios (audit log)** de ediciones de evento — relevante ahora que
   el admin puede mutar cualquier evento (ver §11).
5. **Fix menor**: mock de `usePage` en `Login.spec.js` (2 tests en rojo, ver §9).
6. (Opcional) **Cifrado en reposo** + retención configurable.
7. (Opcional) **Guardrail**: que el driver `fake` no pueda usarse en producción.

## Notas de operación

- El `.env` **local** tiene `REVIEW_MODE=auto` (decisión del dev); los tests no se
  ven afectados (fijado en `phpunit.xml`). En **producción** usar `manual`.
- IDs de modelo Claude (si se activa `anthropic`): `claude-opus-4-8`.
