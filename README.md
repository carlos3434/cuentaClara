# CuentaClara

> Comparte un link, cobra el dinero, y deja que la IA revise los vouchers.

**CuentaClara** es una aplicación web *mobile-first* para organizar pagos
compartidos entre amigos, compañeros de trabajo o grupos. El organizador crea un
evento, comparte un link por WhatsApp, cada participante sube su comprobante de
pago, la IA lo valida, y el organizador ve quién pagó y quién falta — revisando
solo las excepciones.

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
| **Crear evento** | Formulario mobile-first, división equitativa, link público con slug no adivinable |
| **Dashboard** | `/events` — eventos del organizador, más recientes primero |
| **Participante (sin login)** | Abre el link → se identifica (solo nombre) + sube voucher, en una sola pantalla; refleja eventos cerrados |
| **Validación con IA** | `ValidateReceiptJob` asíncrono; drivers `FakeReceiptVision` (dev) y `AnthropicReceiptVision` (Claude vision); el veredicto lo decide un `ReceiptRuleEngine` determinista (monto + confianza). Si la IA falla → *en revisión*, nunca rechazo automático |
| **Cola de revisión** | `/events/{slug}/review` — vouchers por revisar (imagen + lectura de IA), aprobar / rechazar / marcar efectivo, totales cobrado/pendiente |
| **Recordatorios** | Links `wa.me` (al grupo y por participante pendiente) |
| **Comprobante del gasto** | Evidencia del costo real del organizador (solo almacenamiento) |
| **Cerrar / reabrir evento** | Bloquea nuevas subidas cuando está cerrado |

Diferido a v2: división personalizada, pagos parciales/sobrepagos, detección de
duplicados, teléfonos de participantes, IA sobre el comprobante del gasto,
tiempo real, multimoneda. Ver [`docs/13`](docs/13-mvp-critique-and-simplification.md).

## Stack

- **Backend:** Laravel 13 (PHP 8.3) · Eloquent · Queues / Jobs · Form Requests
- **Frontend:** Inertia + Vue 3 · Tailwind CSS v4 (mobile-first)
- **Base de datos:** SQLite (dev) · MySQL/RDS (prod)
- **Almacenamiento:** disco privado local (dev) · S3 (prod) — los vouchers nunca son públicos
- **IA:** Claude vision (`claude-opus-4-8`) para extracción de comprobantes

## Requisitos

PHP 8.3+, Composer, Node 20+, npm.

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
# Almacenamiento de vouchers (privado). Usa s3 en producción.
RECEIPTS_DISK=local
RECEIPTS_MAX_KB=8192

# Validación con IA
AI_DRIVER=fake                 # 'fake' (dev/test) o 'anthropic' (Claude real)
AI_CONFIDENCE_THRESHOLD=0.85
ANTHROPIC_API_KEY=             # requerido cuando AI_DRIVER=anthropic
AI_MODEL=claude-opus-4-8

# Rate limits (peticiones/minuto)
RATE_LIMIT_UPLOADS=20          # POST público /e/{slug}/receipts (por IP)
RATE_LIMIT_LOGIN=10            # POST /login (por email+IP)

# Cola
QUEUE_CONNECTION=database      # 'sync' para correr la validación inline
```

## Tests

```bash
php artisan test
```

**66 tests** (feature + unit) usando SQLite en memoria — sin configuración previa.
Cubren el flujo completo: auth, creación de evento, identificación + subida,
el motor de reglas de IA (vía data providers), la cola de revisión, recordatorios,
comprobantes de gasto y el endurecimiento (rate limiting, cierre de evento).

## Cómo funciona (flujo)

```
Organizador          Sistema / IA                 Participante
    │ crea evento ──────▶                              │
    │ ◀── link público ──                              │
    │ comparte por WhatsApp ─────────────────────────▶ │ abre el link
    │                     ◀──── sube voucher ───────── │ (nombre + foto)
    │                     encola ValidateReceiptJob     │
    │                     extrae + decide veredicto     │
    │ ◀── dashboard / cola de revisión ──               │ "¡Listo!"
    │ aprueba / rechaza / efectivo                      │
    │ recuerda por WhatsApp ──────────────────────────▶ │
    │ cierra el evento                                  │
```

La IA **solo extrae** (monto, fecha, método, destinatario, confianza); el veredicto
lo decide un motor de reglas determinista y unitariamente testeado. El organizador
siempre puede sobrescribir la decisión de la IA.

## Documentación

El análisis de producto e ingeniería vive en [`docs/`](docs/):

- [`docs/14`](docs/14-running-the-app.md) — **qué está implementado + cómo correrlo** (empieza aquí)
- [`docs/13`](docs/13-mvp-critique-and-simplification.md) — alcance del MVP lean y diferidos a v2
- [`docs/07`](docs/07-prd.md) — PRD · [`docs/08`](docs/08-business-rules.md) — reglas de negocio
- [`docs/09`](docs/09-database-model.md) — modelo de datos · [`docs/10`](docs/10-api-proposal.md) — API
- [`docs/06`](docs/06-ai-validation.md) — pipeline de validación con IA

---

Construido sobre [Laravel](https://laravel.com) (MIT).
