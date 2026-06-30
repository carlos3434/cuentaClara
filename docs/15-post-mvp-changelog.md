# 15 — Cambios post-MVP, estado actual y pendientes

> Documento vivo. Resume todo lo construido **después** del MVP original
> (docs 01–14) y deja anotado qué falta. Pensado para retomar sin perder
> contexto.

Última actualización: **2026-06-30**. Rama: `main`. Repo:
`github.com/carlos3434/cuentaClara`.

---

## 1. Despliegue y CI/CD

- **Dockerfile** multi-stage (Node compila assets → imagen PHP 8.3 + Tesseract).
  Defaults de prod por `ENV`: `APP_ENV=production`, `APP_DEBUG=false`,
  `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=sync`, `AI_DRIVER=ocr`.
- **`docker/entrypoint.sh`**: ensure SQLite file → `config/route/view:cache` →
  `migrate --force` → **crea admin desde `ADMIN_EMAIL`/`ADMIN_PASSWORD`** →
  `storage:link` → `php artisan serve --host=0.0.0.0 --port=$PORT`.
- **`APP_KEY` NO** se genera en el Dockerfile (se inyecta como env en Render).
- **Proxy TLS de Render**: `bootstrap/app.php` confía en proxies
  (`X-Forwarded-Proto`) para generar URLs `https` (si no, *mixed content*).
- **CI** (`.github/workflows/ci.yml`): job `test` (Vitest+cobertura → build →
  `php artisan test --coverage --min=90`) y job `deploy` (solo en push a `main`,
  tras tests verdes) que llama a la **API de Render**, crea el deploy y hace
  *poll* hasta `live`. Secrets: `RENDER_API_KEY`, `RENDER_SERVICE_ID`.
- ⚠️ **SQLite en Render es efímera** (se borra en cada deploy). Por eso el admin
  se recrea desde env en cada arranque. Pendiente: BD persistente (MySQL/RDS).

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

- **Backend:** 142 tests (PHPUnit, SQLite en memoria). `php artisan test`.
- **Frontend:** 53 tests (Vitest). `npm run test:js`. Cobertura líneas ~97.9 %.
- `phpunit.xml` fija `AI_DRIVER=fake` y `REVIEW_MODE=manual` para que los tests
  sean deterministas sin depender del `.env` local.

---

## Pendientes / próximos pasos (para el fin de semana)

1. **Cargar en Render** las env: `APP_KEY`, `APP_URL`, `ADMIN_EMAIL`,
   `ADMIN_PASSWORD`; y los secrets de CI `RENDER_API_KEY` / `RENDER_SERVICE_ID`.
   Dejar `REVIEW_MODE=manual` en prod.
2. **Validación de fecha** del pago contra el rango válido del evento (marcar
   fuera de rango → revisión).
3. **Export CSV** de pagos resueltos para el organizador.
4. **BD persistente** en prod (la SQLite efímera borra todo en cada deploy).
5. (Opcional) **Cifrado en reposo** (S3 SSE) + retención configurable.
6. (Opcional) **Guardrail**: que el driver `fake` no pueda usarse en producción.

## Notas de operación

- El `.env` **local** tiene `REVIEW_MODE=auto` (decisión del dev); los tests no se
  ven afectados (fijado en `phpunit.xml`). En **producción** usar `manual`.
- IDs de modelo Claude (si se activa `anthropic`): `claude-opus-4-8`.
