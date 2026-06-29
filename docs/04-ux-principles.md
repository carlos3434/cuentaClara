# docs/04-ux-principles.md

# CuentaClara — UX Principles & Mobile UX Proposal

> **Lean MVP note:** v1 collapses participant screens **P2 (Identify) + P3 (Upload)
> into one screen** (first name + photo), **drops the phone field**, and replaces
> the **P4 processing / P5 result** screens with an instant "¡Listo!" confirmation
> plus a status badge on return. See `13-mvp-critique-and-simplification.md`.

## 1. Principles

1. **Mobile-first, thumb-first.** Designed for 360px width, one-handed use, on 3G.
   Primary actions sit at the bottom of the screen.
2. **Zero friction for participants.** No login, no install. Open link → prove
   payment in under 30 seconds.
3. **One clear action per screen.** Each screen has a single primary CTA.
4. **Honest, calm states.** Every async action has explicit loading / success /
   error / empty states. The AI says what it found in plain Spanish.
5. **Don't replace WhatsApp.** Sharing and reminders hand off to WhatsApp.
6. **Trust through transparency.** Show the AI's reading ("Leí S/ 40, Yape,
   24 jun") and always allow human override.
7. **Spanish UI**, Peru context (Yape/Plin/transferencia, S/).

## 2. Participant journey (the critical path)

Mobile screens, top to bottom:

### P1 — Event landing (`/e/{slug}`)
```
┌───────────────────────────────┐
│  BBQ Cumpleaños Caro 🎉        │
│  Sábado 28 jun · S/ 480 total │
│                               │
│  Tu parte:  S/ 40             │
│  Pagar a:   Caro (Yape 999…)  │
│  Métodos:   Yape · Plin       │
│  Válido:    24–30 jun         │
│                               │
│  [  Ya pagué, subir voucher ] │ ← primary, bottom
└───────────────────────────────┘
```

### P2 — Identify
```
│  ¿Quién eres?                 │
│  ( ) Caro                     │  ← if predefined list
│  ( ) José                     │
│  ( ) No estoy en la lista     │
│      Nombre: [__________]     │
│      Celular:[__________]     │
│  [ Continuar ]                │
```

### P3 — Upload receipt
```
│  Sube tu voucher              │
│  ┌─────────────────────────┐ │
│  │   📷  Tomar foto         │ │  ← camera (capture)
│  │   🖼️  Elegir de galería  │ │
│  └─────────────────────────┘ │
│  [thumbnail preview]          │
│  Nota (opcional): [_______]   │
│  [ Enviar voucher ]           │
```

### P4 — Processing
```
│      ⏳ Validando tu pago…    │
│      Esto toma unos segundos  │
```

### P5 — Result
```
✅  ¡Pago confirmado!            ⚠️  Necesita revisión
S/ 40 · Yape · 24 jun           Leí S/ 30 (esperábamos S/ 40)
Tu parte está saldada.          El organizador lo revisará.
                                [ Subir otro voucher ]
```

## 3. Organizer journey

### O1 — Dashboard (event list)
Cards per event: name, date, **collected / total** progress bar, # pending.

### O2 — Create event (wizard, 5 short steps)
Name+date → total+count (auto share preview) → recipient+methods → date range →
split/participants. Each step one screenful, big "Siguiente".

### O3 — Event detail / dashboard
```
┌───────────────────────────────┐
│  BBQ Cumpleaños Caro          │
│  ████████░░  S/ 360 / S/ 480  │
│  Pagado 9 · Pendiente 3 · ⚠ 1 │
│  [ Compartir link ] [ QR ]    │
│  ───────── Participantes ──── │
│  ✅ José      S/ 40            │
│  🕓 Lucía     en revisión  >  │
│  ⏳ Marco     pendiente   [↗] │ ← ↗ = recordar (WhatsApp)
│  💵 Ana       pagó efectivo   │
│  ───────────────────────────  │
│  [ Subir comprobante del gasto ]
└───────────────────────────────┘
```

### O4 — Review a receipt
```
│  [ receipt image, pinch-zoom ]│
│  IA leyó:                     │
│   Monto:   S/ 30  ⚠           │
│   Fecha:   24 jun ✅          │
│   A:       Caro ✅            │
│   Método:  Yape ✅            │
│   Confianza: 72%              │
│   "Monto menor al esperado"   │
│  [ Aprobar ] [ Rechazar ]     │
│  [ Marcar pago parcial ]      │
```

### O5 — Reminders
Tap ↗ on a pending participant → opens WhatsApp with a pre-filled message:
*"Hola Marco 👋 Te recuerdo tu parte del BBQ: S/ 40 por Yape a Caro (999…).
Sube tu voucher aquí: {link}"*.

## 4. States checklist (every screen must define)

- **Loading** — skeletons / spinner with text.
- **Empty** — friendly empty state with the one action to take.
- **Error** — human message + retry; never a raw stack trace.
- **Offline** — uploads queue and retry; tell the user.
- **Success** — explicit confirmation, then next step.

## 5. Visual & interaction guidelines

- Tap targets ≥ 44px; primary CTA full-width, bottom-anchored, sticky.
- Color semantics: green=validated, amber=needs review, red=problem, grey=pending,
  blue=cash. Never rely on color alone — pair with an icon + label.
- Image upload: client-side compression + EXIF orientation fix before upload.
- Progress bar for collected/total is the emotional core of the organizer view.
- Copy is short, warm, Peruvian-neutral Spanish.

## 6. Accessibility

- Contrast AA, scalable text, labelled inputs, focus states, screen-reader labels
  on status icons ("Pago validado").
