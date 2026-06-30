# CuentaClara

[![CI](https://github.com/carlos3434/cuentaClara/actions/workflows/ci.yml/badge.svg)](https://github.com/carlos3434/cuentaClara/actions/workflows/ci.yml)

> Comparte un link, cobra el dinero, y revisa los vouchers con ayuda de OCR.

**CuentaClara** es una aplicación web *mobile-first* para organizar pagos
compartidos entre amigos, compañeros de trabajo o grupos. El organizador crea un
evento, comparte un link por WhatsApp, cada participante sube su comprobante de
pago, el OCR extrae los datos como ayuda, y el organizador confirma quién pagó y
ve cuánto falta del total. La revisión es **manual por defecto** (el organizador
confirma cada pago); la auto-aprobación con IA queda detrás de un interruptor.

Pensado para el contexto peruano: **Yape, Plin y transferencias**, montos en
**soles (S/)**, interfaz en español.

---

## El problema

Cuando un grupo organiza un evento, alguien paga primero y luego tiene que cobrarle
a todos. Los vouchers se pierden en WhatsApp, el organizador revisa Yape/Plin a
mano, y es difícil saber cuánto se juntó y quién debe. CuentaClara se encarga del
seguimiento y la validación; **no reemplaza WhatsApp** (ahí se comparte el link).

## Características (MVP completo ✅)

| Capacidad | Detalle |
|-----------|---------|
| **Auth del organizador** | Registro / login / logout (contraseña, sesión); login con rate limit |
| **Crear evento** | Formulario mobile-first, división equitativa, link público con slug no adivinable; comprobante del gasto opcional al crear |
| **Dashboard** | `/events` — eventos del organizador, más recientes primero, con estado e iconos |
| **Participante (sin login)** | Abre el link → se identifica (solo nombre) + sube voucher, en una sola pantalla; refleja eventos cerrados |
| **Lectura del comprobante (OCR)** | `ValidateReceiptJob` asíncrono lee el voucher con **Tesseract** (`AI_DRIVER=ocr`, default en prod) y extrae **monto, fecha, método, destinatario y N° de operación** de Yape/Plin/transferencias. Solo asiste; **nunca decide el dinero** |
| **Revisión manual / automática** | `REVIEW_MODE=manual` (default): cada pago espera tu confirmación. `auto`: el `ReceiptRuleEngine` determinista puede auto-aprobar (monto + método + confianza). El admin cambia el modo en runtime |
| **Cola de revisión** | `/events/{slug}/review` — **"Por revisar"** lista solo los pendientes; **"Participantes"** lista los resueltos (**Aprobado** / **Rechazado**) con su estado al costado. Aprobar / rechazar / marcar efectivo |
| **Totales reales** | "Cobrado" suma los **montos reales** de los pagos aprobados (lo leído del voucher; efectivo cae al share), y "Falta = total − cobrado" |
| **Detección de duplicados** | Si dos participantes suben un voucher con el mismo N° de operación → se marca "Posible duplicado" y nunca se auto-aprueba |
| **Rol Administrador** | Panel `/admin`: gestión de organizadores (crear / activar-desactivar), pagos por evento, e interruptor global manual/automático |
| **Recordatorios** | Links `wa.me` (al grupo y por participante) |
| **Comprobante del gasto** | Evidencia del costo real del organizador (solo almacenamiento), al crear el evento o desde la revisión |
| **Cerrar / reabrir evento** | Bloquea nuevas subidas cuando está cerrado |
| **Minimización de datos** | El voucher se **borra al resolver el pago**; el N° de operación se guarda **hasheado**; no se persiste el texto OCR crudo; aviso de privacidad en la subida |

Diferido a v2: división personalizada, pagos parciales/sobrepagos, teléfonos de
participantes, IA real de visión sobre el comprobante del gasto, tiempo real,
multimoneda. Ver [`docs/13`](docs/13-mvp-critique-and-simplification.md),
[`docs/15`](docs/15-post-mvp-changelog.md) y [Mejoras a futuro](#mejoras-a-futuro).

## Stack

- **Backend:** Laravel 13 (PHP 8.3) · Eloquent · Queues / Jobs · Form Requests · Policies · Actions · Enums (PHP 8.1)
- **Frontend:** Inertia + Vue 3 · Tailwind CSS v4 (mobile-first) · iconos SVG inline
- **Base de datos:** SQLite (dev) · MySQL/RDS (prod)
- **Almacenamiento:** disco privado local (dev) · **disco persistente de Render** o S3 (prod) — los vouchers nunca son públicos y se borran al resolver el pago
- **Lectura de comprobantes:** **Tesseract OCR** (`AI_DRIVER=ocr`, default en prod) · opcional Claude vision (`AI_DRIVER=anthropic`) · `FakeReceiptVision` (dev/test)
- **Pruebas:** PHPUnit + PCOV (backend) · Vitest + Vue Test Utils + `@vitest/coverage-v8` (frontend)
- **CI/CD:** GitHub Actions (build + ambas suites + cobertura) y, si pasan, deploy automático a **Render** (Docker)

## Requisitos

PHP 8.3+, Composer, Node 20+ (CI usa 22), npm.

## Instalación

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
```

## Ejecutar

```bash
# App
php artisan serve

# Assets con hot reload (en otra terminal, durante desarrollo)
npm run dev

# Worker de cola (la validación con IA corre asíncrona)
php artisan queue:work
```

Abre `http://127.0.0.1:8000` → te redirige al **dashboard** del organizador
(si no has iniciado sesión, a `/login`; registra un organizador para empezar).

> Para ver la validación con IA en una subida local sin worker, corre con
> `QUEUE_CONNECTION=sync` y `AI_DRIVER=fake` — el driver falso auto-valida.

## Configuración (.env)

```ini
# Almacenamiento de vouchers (privado). En prod: 'persistent' (disco de Render)
# o 's3'. Con 'persistent' define también RECEIPTS_DISK_ROOT (ruta del disco).
RECEIPTS_DISK=local
# RECEIPTS_DISK_ROOT=/var/data/receipts   # solo con RECEIPTS_DISK=persistent
RECEIPTS_MAX_KB=8192

# Lectura de comprobantes
AI_DRIVER=ocr                  # 'ocr' (Tesseract, default prod) · 'fake' (dev) · 'anthropic'
TESSERACT_BIN=tesseract        # binario (la imagen Docker lo trae con idioma 'spa')
TESSERACT_LANG=spa
AI_CONFIDENCE_THRESHOLD=0.85
ANTHROPIC_API_KEY=             # requerido solo cuando AI_DRIVER=anthropic
AI_MODEL=claude-opus-4-8

# Revisión de pagos
REVIEW_MODE=manual             # 'manual' (organizador confirma todo) o 'auto'

# Admin por defecto (creado en el arranque del contenedor — ver Despliegue)
ADMIN_EMAIL=admin@cuentaclara.test
ADMIN_PASSWORD=changeme-please
ADMIN_NAME=Admin

# Rate limits (peticiones/minuto)
RATE_LIMIT_UPLOADS=20          # POST público /e/{slug}/receipts (por IP)
RATE_LIMIT_LOGIN=10            # POST /login (por email+IP)

# Cola
QUEUE_CONNECTION=database      # 'sync' para correr la lectura/validación inline
```

> Para crear o promover un admin manualmente:
> `php artisan admin:make correo@dominio.com --name="Nombre" --password="clave"`.

## Calidad: pruebas y cobertura

| Capa | Comando | Tests | Cobertura | Piso CI |
|------|---------|-------|-----------|---------|
| **Backend** (PHPUnit) | `php artisan test` | 142 | **≥ 90 %** líneas (PCOV) | `--min=90` |
| **Frontend** (Vitest) | `npm run test:js` | 53 | **97.9 %** líneas (v8) | `lines ≥ 90` |

```bash
php artisan test                 # backend (SQLite en memoria, sin setup)
npm run test:js                  # frontend (jsdom)
npm run test:js:coverage         # frontend + reporte de cobertura
```

Cubren el flujo completo de punta a punta: auth, creación de evento, identificación
+ subida, el motor de reglas de IA, la cola de revisión, recordatorios, comprobantes
de gasto, endurecimiento (rate limiting, cierre) y **tests de integración** que
ejercitan el pipeline real (cola + job + reglas) sin mocks
(`tests/Feature/Flows/`). En el frontend, cada componente Vue tiene pruebas de
render e interacción con Inertia mockeado.

## Integración continua

[GitHub Actions](.github/workflows/ci.yml) corre en cada push y PR:
`npm run test:js:coverage` → `npm run build` → `php artisan test --coverage --min=90`.
El badge arriba refleja el estado de `main`.

## Cómo funciona (flujo)

```
Organizador          Sistema / OCR                Participante
    │ crea evento ──────▶                              │
    │ ◀── link público ──                              │
    │ comparte por WhatsApp ─────────────────────────▶ │ abre el link
    │                     ◀──── sube voucher ───────── │ (nombre + foto)
    │                     encola ValidateReceiptJob     │
    │                     OCR extrae datos (asiste)     │
    │ ◀── "Por revisar" (pendiente) ──                  │ "En revisión"
    │ Confirmar pago / Rechazar                         │
    │ → "Participantes": Aprobado / Rechazado           │
    │ recuerda por WhatsApp ──────────────────────────▶ │
    │ cierra el evento (se borran los vouchers)         │
```

El OCR **solo extrae** (monto, fecha, método, destinatario, N° de operación); en
modo `manual` el organizador confirma cada pago. En modo `auto` un motor de reglas
determinista y testeado puede auto-aprobar, y el organizador siempre puede
sobrescribirlo. Un duplicado (mismo N° de operación) nunca se auto-aprueba.

## Arquitectura y decisiones

- **Lector con costura intercambiable.** `ReceiptVision` es un contrato con tres
  implementaciones: `TesseractReceiptVision` (OCR, default prod), `FakeReceiptVision`
  (dev/test, determinista) y `AnthropicReceiptVision` (Claude vision). Se elige por
  `AI_DRIVER`. El parser de texto (`ReceiptTextParser`) es puro y unitariamente
  testeado contra los 5 templates reales (Yape, Plin BBVA/Interbank/Scotiabank, BCP).
- **Revisión manual por defecto.** `review_mode` (config + setting global que el
  admin cambia en runtime). En `manual` el OCR solo asiste y el organizador confirma;
  en `auto` el `ReceiptRuleEngine` (puro y testeado) decide por monto + método +
  confianza. Falla de OCR → revisión, nunca rechazo automático.
- **Rol y panel de admin.** `users.role` (admin/organizer) + middleware `admin`;
  `/admin` gestiona organizadores, ve pagos por evento y cambia el modo de revisión.
- **Minimización de datos.** El voucher se borra al resolver el pago; el N° de
  operación se guarda hasheado (SHA-256, para detección de duplicados sin el valor
  en claro); no se persiste el texto OCR crudo. Disco privado tras `ReceiptStorage`.
- **Autorización con Policies.** `EventPolicy::manage` centraliza “el organizador
  solo gestiona sus eventos” (`$this->authorize('manage', $event)`).
- **Storage privado tras un gateway.** `ReceiptStorage` es el único punto de
  acceso al disco de vouchers/gastos (nunca público; URLs por streaming autorizado).
- **Enums de dominio.** Estados/método/razón son enums respaldados
  (`App\Enums\*`) con casts en los modelos — sin strings mágicos.
- **Acciones reutilizables.** p. ej. `StoreExpenseReceipt` comparte la lógica de
  guardar el comprobante entre el alta de evento y la revisión.

## Capturas

<p>
  <img src="docs/screenshots/dashboard.png" width="220" alt="Dashboard del organizador" />
  <img src="docs/screenshots/create.png" width="220" alt="Crear evento" />
  <img src="docs/screenshots/public-event.png" width="220" alt="Landing del participante" />
</p>
<p>
  <img src="docs/screenshots/review.png" width="220" alt="Cola de revisión" />
  <img src="docs/screenshots/created.png" width="220" alt="Link para compartir" />
</p>

De izquierda a derecha: **dashboard**, **crear evento**, **landing del participante**,
**cola de revisión** (cobrado/pendiente, voucher por revisar con lectura de IA,
participantes, comprobante del gasto) y **link para compartir**.

Se regeneran con datos de demo:

```bash
php artisan migrate:fresh && php artisan db:seed --class=DemoSeeder   # login: demo@cuentaclara.test / password
QUEUE_CONNECTION=sync AI_DRIVER=fake php artisan serve &
SLUG=<demo-slug> node scripts/screenshots.mjs                          # → docs/screenshots/
```

(Mockups de cada pantalla en [`docs/04-ux-principles.md`](docs/04-ux-principles.md).)

## Mejoras a futuro

Producto (ver detalle en [`docs/13`](docs/13-mvp-critique-and-simplification.md) y
[`docs/15`](docs/15-post-mvp-changelog.md)):

- **Validación de fecha** del pago contra el rango válido del evento.
- **Export** de pagos resueltos (CSV) para el organizador.
- División **personalizada** por participante (hoy solo equitativa).
- **Pagos parciales y sobrepagos** (acumular abonos hacia la parte).
- **IA real de visión** sobre el comprobante del gasto + alerta si el total no cuadra.
- **Recordatorios automáticos** (WhatsApp Business API) en vez de solo `wa.me`.
- **Tiempo real** (WebSockets/Echo) en vez de recargar para ver el estado.
- **Multimoneda**, **multi-organizador**, reembolsos.

Plataforma / calidad:

- **Cifrado en reposo** y retención configurable de vouchers.
- **E2E** con Playwright (incl. el flujo OCR real con un canvas de verdad).
- **Branch protection** en `main` exigiendo el check verde de CI.

## Despliegue (Render)

La app corre en **Render** como servicio Docker. El [`Dockerfile`](Dockerfile)
es multi-stage (Node compila los assets; la imagen final es PHP 8.3 + Tesseract)
y el [`entrypoint`](docker/entrypoint.sh) corre migraciones, crea el admin por
defecto (`ADMIN_EMAIL`/`ADMIN_PASSWORD`) y levanta `php artisan serve` en `$PORT`.

**Variables en Render:** `APP_KEY`, `APP_URL`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`
(`AI_DRIVER=ocr`, `QUEUE_CONNECTION=sync`, `APP_ENV=production` ya vienen por
defecto en la imagen). **No** generamos `APP_KEY` dentro del Dockerfile.

### Persistencia de datos

La base de datos y las imágenes de vouchers deben sobrevivir a los deploys.

- **Base de datos:** Postgres administrado de Render. Setear `DB_CONNECTION=pgsql`
  y `DB_URL` (Internal Connection String). La imagen trae `pdo_pgsql`.
- **Imágenes de vouchers:** **Render Persistent Disk**. Adjuntar un disco al
  servicio (mount path, p. ej. `/var/data`) y setear `RECEIPTS_DISK=persistent`
  + `RECEIPTS_DISK_ROOT=/var/data/receipts`. Alternativa: `RECEIPTS_DISK=s3`
  con un bucket privado.

> El disco se monta como root, así que la imagen **arranca como root**, el
> entrypoint hace `chown` de `RECEIPTS_DISK_ROOT` a `www-data` y **baja
> privilegios con `gosu`** antes de levantar la app — nunca corre como root.
>
> ⚠️ Adjuntar un disco **fuerza instancia única** (sin escalado horizontal) y
> **desactiva el zero-downtime deploy** (hay un breve corte al desplegar). Para
> escalar horizontalmente más adelante, mover las imágenes a S3.

## Documentación

El análisis de producto e ingeniería vive en [`docs/`](docs/):

- [`docs/15`](docs/15-post-mvp-changelog.md) — **cambios post-MVP + estado actual + pendientes** (empieza aquí)
- [`docs/14`](docs/14-running-the-app.md) — qué está implementado + cómo correrlo
- [`docs/13`](docs/13-mvp-critique-and-simplification.md) — alcance del MVP lean y diferidos a v2
- [`docs/07`](docs/07-prd.md) — PRD · [`docs/08`](docs/08-business-rules.md) — reglas de negocio
- [`docs/09`](docs/09-database-model.md) — modelo de datos · [`docs/10`](docs/10-api-proposal.md) — API
- [`docs/06`](docs/06-ai-validation.md) — pipeline de validación con IA

---

Construido sobre [Laravel](https://laravel.com) (MIT).
