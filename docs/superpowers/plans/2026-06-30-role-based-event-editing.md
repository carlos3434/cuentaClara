# Role-Based Event Editing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin edit every field of any event, and an organizer edit only the event date, pay deadline and total amount of their own events, through one role-aware edit screen.

**Architecture:** Reuse the existing `Events/Edit.vue` + `GET /events/{event}/edit` + `PUT /events/{event}` flow. Authorization is broadened in `EventPolicy@manage` (admin OR owner). The per-role field whitelist is enforced server-side via role-conditional rules in `UpdateEventRequest` — `validated()` only returns keys the role may edit, so a crafted request can't touch forbidden fields. The frontend receives a `can_edit_all` flag and renders admin-only fields conditionally.

**Tech Stack:** Laravel 13 (Form Requests, Policies, Inertia), Vue 3 + Inertia, PHPUnit, Vitest + Vue Test Utils.

## Global Constraints

- Work in the existing worktree branch `worktree-edit-event-and-participants-fix`. The `vendor/` and `node_modules/` are already installed there.
- If backend tests return unexpected `404` on the edit/update routes, run `php artisan route:clear` first (a stale route cache causes this).
- Amounts are stored in cents; the form edits soles (PEN). `total_amount` (soles) → `total_cents = round(total_amount * 100)`.
- Recompute `share_cents = Event::shareFor($totalCents, $headcount)` whenever `total_amount` or `headcount` changes.
- Organizer-editable fields: `event_date`, `pay_deadline`, `total_amount`. Admin-editable: those plus `name`, `headcount`, `recipient_name`, `recipient_handle`, `accepted_methods`, `slug`.
- Non-permitted fields sent by an organizer are ignored silently (never rejected).
- Route names stay as-is: `organizer.events.edit`, `organizer.events.update`.
- Run backend tests with `php artisan test`, frontend with `npm run test:js`.

---

### Task 1: Broaden `EventPolicy@manage` to admin-or-owner

**Files:**
- Modify: `app/Policies/EventPolicy.php`
- Test: `tests/Feature/EditEventTest.php` (created in Task 2; the policy is exercised end-to-end there — this task ships the policy change plus one focused HTTP test)

**Interfaces:**
- Produces: `EventPolicy::manage(User $user, Event $event): bool` now returns `true` for any admin, or the owner.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/EditEventTest.php` with just this test for now:

```php
<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_edit_screen_of_any_event(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($admin)
            ->get("/events/{$event->slug}/edit")
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_admin_can_open_the_edit_screen_of_any_event`
Expected: FAIL — 403 (the current policy only allows the owner).

- [ ] **Step 3: Broaden the policy**

Replace the `manage` method body in `app/Policies/EventPolicy.php`:

```php
    /**
     * An admin may manage any event (platform oversight); an organizer may
     * manage only their own. Auto-discovered by Laravel for the Event model.
     */
    public function manage(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->user_id === $user->id;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_admin_can_open_the_edit_screen_of_any_event`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Policies/EventPolicy.php tests/Feature/EditEventTest.php
git commit -m "feat: admin can manage (edit/review) any event"
```

---

### Task 2: Role-based validation + update (backend)

**Files:**
- Modify: `app/Http/Requests/UpdateEventRequest.php` (full rewrite — currently validates all fields for everyone)
- Modify: `app/Http/Controllers/Organizer/EventController.php` (methods `edit` and `update`)
- Test: `tests/Feature/EditEventTest.php` (extend)

**Interfaces:**
- Consumes: `EventPolicy::manage` (Task 1), `Event::shareFor(int $totalCents, int $headcount): int`, `App\Enums\PaymentMethod::selectableValues(): array`, `User::isAdmin(): bool`.
- Produces:
  - `UpdateEventRequest::rules(): array` — role-conditional; the admin-only block IS the field whitelist.
  - `EventController::edit(Request $request, Event $event): Response` — now passes `can_edit_all: bool`.
  - `EventController::update(UpdateEventRequest $request, Event $event): RedirectResponse` — applies only validated (role-permitted) keys, recomputes `share_cents`, redirects to `organizer.events.review`.

- [ ] **Step 1: Write the failing tests**

Replace the whole body of `tests/Feature/EditEventTest.php` with (keeps the Task 1 test, adds the rest):

```php
<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditEventTest extends TestCase
{
    use RefreshDatabase;

    private function organizerPayload(array $overrides = []): array
    {
        return array_merge([
            'event_date' => now()->addDays(10)->toDateString(),
            'pay_deadline' => now()->addDays(8)->toDateString(),
            'total_amount' => '600',
        ], $overrides);
    }

    private function adminPayload(array $overrides = []): array
    {
        return array_merge($this->organizerPayload(), [
            'name' => 'Cena de fin de año',
            'headcount' => 5,
            'recipient_name' => 'Caro',
            'recipient_handle' => '999111222',
            'accepted_methods' => ['yape', 'plin'],
            'slug' => 'nuevo-enlace',
        ], $overrides);
    }

    public function test_admin_can_open_the_edit_screen_of_any_event(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($admin)
            ->get("/events/{$event->slug}/edit")
            ->assertOk();
    }

    public function test_organizer_can_edit_dates_and_amount_and_share_recomputes(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->for($owner)->create([
            'total_cents' => 48000,
            'headcount' => 12,
            'share_cents' => 4000,
        ]);

        $this->actingAs($owner)
            ->put("/events/{$event->slug}", $this->organizerPayload(['total_amount' => '600']))
            ->assertRedirect("/events/{$event->slug}/review");

        $event->refresh();
        $this->assertSame(60000, $event->total_cents);
        // headcount unchanged (12) → 60000 / 12 = 5000
        $this->assertSame(12, $event->headcount);
        $this->assertSame(5000, $event->share_cents);
    }

    public function test_organizer_cannot_change_admin_only_fields(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->for($owner)->create([
            'name' => 'Original',
            'slug' => 'original-slug',
            'headcount' => 12,
        ]);

        // Craft a request with forbidden fields; they must be ignored.
        $this->actingAs($owner)
            ->put("/events/{$event->slug}", $this->organizerPayload([
                'name' => 'Hackeado',
                'slug' => 'hackeado',
                'headcount' => 1,
            ]))
            ->assertRedirect("/events/{$event->slug}/review");

        $event->refresh();
        $this->assertSame('Original', $event->name);
        $this->assertSame('original-slug', $event->slug);
        $this->assertSame(12, $event->headcount);
    }

    public function test_organizer_cannot_edit_another_organizers_event(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $intruder = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->put("/events/{$event->slug}", $this->organizerPayload())
            ->assertForbidden();
    }

    public function test_admin_can_edit_all_fields_including_the_link(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->for($owner)->create([
            'total_cents' => 48000,
            'headcount' => 12,
        ]);

        $this->actingAs($admin)
            ->put("/events/{$event->slug}", $this->adminPayload(['total_amount' => '600', 'headcount' => 5]))
            ->assertRedirect("/events/nuevo-enlace/review");

        $event->refresh();
        $this->assertSame('nuevo-enlace', $event->slug);
        $this->assertSame('Cena de fin de año', $event->name);
        $this->assertSame(60000, $event->total_cents);
        $this->assertSame(5, $event->headcount);
        // 60000 / 5 = 12000
        $this->assertSame(12000, $event->share_cents);
    }

    public function test_admin_slug_must_be_unique(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Event::factory()->for($owner)->create(['slug' => 'taken-slug']);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($admin)
            ->put("/events/{$event->slug}", $this->adminPayload(['slug' => 'taken-slug']))
            ->assertSessionHasErrors('slug');
    }

    public function test_deadline_in_the_past_is_allowed_when_editing(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->put("/events/{$event->slug}", $this->organizerPayload([
                'pay_deadline' => now()->subDays(3)->toDateString(),
            ]))
            ->assertRedirect("/events/{$event->slug}/review");
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=EditEventTest`
Expected: FAIL — the current `UpdateEventRequest` requires admin-only fields (`name`, `slug`, …) for everyone, so the organizer payloads fail validation; `can_edit_all` isn't passed yet.

- [ ] **Step 3: Rewrite `UpdateEventRequest`**

Replace the whole file `app/Http/Requests/UpdateEventRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    /**
     * Admin (any event) or the owning organizer may edit — via the policy.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && $this->user() !== null
            && $this->user()->can('manage', $event);
    }

    /**
     * Role-conditional rules. Because validated() returns only ruled keys,
     * this block IS the server-side field whitelist: an organizer's crafted
     * name/slug/headcount/etc. is never validated and never applied.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');

        // Both roles: dates + amount. On edit the deadline may already be past,
        // so no `after_or_equal:today` (that guard lives in the create flow).
        $rules = [
            'event_date' => ['required', 'date'],
            'pay_deadline' => ['required', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
        ];

        if ($this->user()->isAdmin()) {
            $rules += [
                'name' => ['required', 'string', 'max:120'],
                'headcount' => ['required', 'integer', 'min:1', 'max:1000'],
                'recipient_name' => ['required', 'string', 'max:120'],
                'recipient_handle' => ['nullable', 'string', 'max:60'],
                'accepted_methods' => ['required', 'array', 'min:1'],
                'accepted_methods.*' => [Rule::in(PaymentMethod::selectableValues())],
                'slug' => [
                    'required', 'string', 'min:4', 'max:40', 'regex:/^[a-z0-9-]+$/',
                    Rule::unique('events', 'slug')->ignore($event->id),
                ],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accepted_methods.required' => 'Selecciona al menos un método de pago.',
            'slug.regex' => 'El enlace solo puede tener minúsculas, números y guiones.',
            'slug.unique' => 'Ese enlace ya está en uso, elige otro.',
            'slug.min' => 'El enlace es demasiado corto.',
        ];
    }
}
```

- [ ] **Step 4: Update `EventController@edit` and `@update`**

In `app/Http/Controllers/Organizer/EventController.php`, replace the `edit` method so it also passes `can_edit_all` (add the `Request $request` parameter):

```php
    /**
     * Edit form, prefilled with the event's current values. `can_edit_all`
     * tells the view whether to show admin-only fields.
     */
    public function edit(Request $request, Event $event): Response
    {
        $this->authorize('manage', $event);

        return Inertia::render('Events/Edit', [
            'event' => [
                'slug' => $event->slug,
                'name' => $event->name,
                'event_date' => $event->event_date->toDateString(),
                'total_amount' => number_format($event->total_cents / 100, 2, '.', ''),
                'headcount' => $event->headcount,
                'recipient_name' => $event->recipient_name,
                'recipient_handle' => $event->recipient_handle,
                'accepted_methods' => $event->accepted_methods,
                'pay_deadline' => $event->pay_deadline->toDateString(),
                'public_url' => route('public.events.show', $event),
            ],
            'can_edit_all' => $request->user()->isAdmin(),
        ]);
    }
```

Replace the `update` method:

```php
    /**
     * Persist edits. Only the keys the role may edit reach here (validated()
     * already dropped the rest). Changing total/headcount recomputes the
     * per-person share; already-approved payments keep their real amount.
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $data = $request->validated();

        // Direct-copy fields present for this role. Normalize the few that need it.
        $changes = collect($data)->except(['total_amount', 'headcount'])->all();
        if (isset($changes['accepted_methods'])) {
            $changes['accepted_methods'] = array_values($changes['accepted_methods']);
        }
        if (array_key_exists('recipient_handle', $data)) {
            $changes['recipient_handle'] = $data['recipient_handle'] ?? null;
        }

        // Amount/headcount → recompute share. Missing keys keep current values
        // (an organizer can't change headcount).
        $totalCents = isset($data['total_amount'])
            ? (int) round($data['total_amount'] * 100)
            : $event->total_cents;
        $headcount = isset($data['headcount']) ? (int) $data['headcount'] : $event->headcount;

        if (isset($data['total_amount'])) {
            $changes['total_cents'] = $totalCents;
        }
        if (isset($data['headcount'])) {
            $changes['headcount'] = $headcount;
        }
        if (isset($data['total_amount']) || isset($data['headcount'])) {
            $changes['share_cents'] = Event::shareFor($totalCents, $headcount);
        }

        $event->update($changes);

        return redirect()->route('organizer.events.review', $event);
    }
```

Note: `Illuminate\Http\Request` is already imported in this controller; no new import needed.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=EditEventTest`
Expected: PASS (all 7 tests). If any route returns 404, run `php artisan route:clear` and re-run.

- [ ] **Step 6: Run the full backend suite (no regressions)**

Run: `php artisan test`
Expected: PASS. (The broadened policy grants admins access to review/close too; existing "another organizer cannot…" tests use non-admin intruders, so they still pass.)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/UpdateEventRequest.php app/Http/Controllers/Organizer/EventController.php tests/Feature/EditEventTest.php
git commit -m "feat: role-based event edit (organizer=dates/amount, admin=all)"
```

---

### Task 3: Role-aware `Events/Edit.vue`

**Files:**
- Modify: `resources/js/Pages/Events/Edit.vue`
- Test: `resources/js/Pages/Events/Edit.spec.js` (create)

**Interfaces:**
- Consumes: props `event` (object with `slug, name, event_date, total_amount, headcount, recipient_name, recipient_handle, accepted_methods, pay_deadline, public_url`) and `can_edit_all: Boolean`.
- Produces: submits `form.put('/events/{originalSlug}')`. Organizer view shows only `#event_date`, `#pay_deadline`, `#total_amount`. Admin view additionally shows `#name`, `#headcount`, `#recipient_name`, `#recipient_handle`, method pills and `#slug`.

- [ ] **Step 1: Write the failing test**

Create `resources/js/Pages/Events/Edit.spec.js`:

```js
import { mount } from '@vue/test-utils';

const m = vi.hoisted(() => ({ formPut: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await vi.importActual('vue');
    return {
        Head: { template: '<div><slot /></div>' },
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        useForm: (data) => reactive({ ...data, errors: {}, processing: false, put: m.formPut }),
    };
});

import Edit from './Edit.vue';

function eventProp(overrides = {}) {
    return {
        slug: 'mi-evento',
        name: 'BBQ Caro',
        event_date: '2026-07-10',
        total_amount: '480.00',
        headcount: 12,
        recipient_name: 'Caro',
        recipient_handle: '999888777',
        accepted_methods: ['yape'],
        pay_deadline: '2026-07-08',
        public_url: 'http://localhost/e/mi-evento',
        ...overrides,
    };
}

beforeEach(() => m.formPut.mockClear());

describe('Events/Edit', () => {
    it('organizer sees only dates and amount', () => {
        const w = mount(Edit, { props: { event: eventProp(), can_edit_all: false } });

        expect(w.find('#event_date').exists()).toBe(true);
        expect(w.find('#pay_deadline').exists()).toBe(true);
        expect(w.find('#total_amount').exists()).toBe(true);

        expect(w.find('#name').exists()).toBe(false);
        expect(w.find('#headcount').exists()).toBe(false);
        expect(w.find('#recipient_name').exists()).toBe(false);
        expect(w.find('#slug').exists()).toBe(false);
    });

    it('admin sees all fields including the link', () => {
        const w = mount(Edit, { props: { event: eventProp(), can_edit_all: true } });

        ['#name', '#event_date', '#pay_deadline', '#total_amount', '#headcount', '#recipient_name', '#recipient_handle', '#slug']
            .forEach((sel) => expect(w.find(sel).exists()).toBe(true));
        expect(w.text()).toContain('Yape');
    });

    it('submits to the original slug', async () => {
        const w = mount(Edit, { props: { event: eventProp({ slug: 'mi-evento' }), can_edit_all: true } });

        await w.find('#event-form').trigger('submit.prevent');

        expect(m.formPut).toHaveBeenCalledWith('/events/mi-evento');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:js -- Edit.spec.js`
Expected: FAIL — the current `Edit.vue` always renders admin fields (no `can_edit_all` gating), so "organizer sees only dates and amount" fails.

- [ ] **Step 3: Rewrite `Events/Edit.vue`**

Replace the whole file `resources/js/Pages/Events/Edit.vue`:

```vue
<script setup>
import { computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    // Admin edits all fields; organizer only dates + amount.
    can_edit_all: { type: Boolean, default: false },
});

const methods = [
    { value: 'yape', label: 'Yape' },
    { value: 'plin', label: 'Plin' },
    { value: 'bank_transfer', label: 'Transferencia' },
];

// The route binding resolves the event by its *current* slug, so the PUT must
// target the original slug even if an admin edits it in the form.
const originalSlug = props.event.slug;

const form = useForm({
    name: props.event.name,
    event_date: props.event.event_date,
    total_amount: props.event.total_amount,
    headcount: props.event.headcount,
    recipient_name: props.event.recipient_name,
    recipient_handle: props.event.recipient_handle ?? '',
    accepted_methods: [...(props.event.accepted_methods ?? [])],
    pay_deadline: props.event.pay_deadline,
    slug: props.event.slug,
});

// Uses the current headcount (not editable by the organizer) for the preview.
const sharePreview = computed(() => {
    const total = parseFloat(form.total_amount);
    const count = parseInt(form.headcount, 10);
    if (!total || !count || count < 1) return null;
    return (total / count).toFixed(2);
});

const slugChanged = computed(() => form.slug !== originalSlug);
const linkOrigin = computed(() => props.event.public_url.replace(/\/e\/.*$/, ''));

function toggleMethod(value) {
    const i = form.accepted_methods.indexOf(value);
    if (i === -1) form.accepted_methods.push(value);
    else form.accepted_methods.splice(i, 1);
}

function submit() {
    // The server re-enforces the per-role whitelist, so sending the full form
    // is safe: forbidden fields are ignored for an organizer.
    form.put(`/events/${originalSlug}`);
}
</script>

<template>
    <Head title="Editar evento" />

    <main class="mx-auto flex min-h-full max-w-md flex-col px-4 pb-28 pt-6">
        <div class="mb-4 flex items-center justify-between">
            <Link :href="`/events/${originalSlug}/review`" class="text-sm font-medium text-slate-500">← Volver</Link>
            <p class="text-sm font-semibold text-teal-700">CuentaClara</p>
        </div>

        <header class="mb-6">
            <h1 class="text-2xl font-bold">Editar evento</h1>
            <p class="mt-1 text-sm text-slate-500">Actualiza los datos del evento.</p>
        </header>

        <form id="event-form" class="space-y-5" @submit.prevent="submit">
            <div v-if="can_edit_all">
                <label class="block text-sm font-medium" for="name">Nombre del evento</label>
                <input id="name" v-model="form.name" type="text" placeholder="BBQ Cumpleaños Caro"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium" for="event_date">Fecha del evento</label>
                <input id="event_date" v-model="form.event_date" type="date"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.event_date" class="mt-1 text-sm text-red-600">{{ form.errors.event_date }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium" for="pay_deadline">Pagar antes del</label>
                <input id="pay_deadline" v-model="form.pay_deadline" type="date"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.pay_deadline" class="mt-1 text-sm text-red-600">{{ form.errors.pay_deadline }}</p>
            </div>

            <div class="grid gap-3" :class="can_edit_all ? 'grid-cols-2' : 'grid-cols-1'">
                <div>
                    <label class="block text-sm font-medium" for="total_amount">Monto total (S/)</label>
                    <input id="total_amount" v-model="form.total_amount" type="number" inputmode="decimal" step="0.01" min="0" placeholder="480"
                        class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                    <p v-if="form.errors.total_amount" class="mt-1 text-sm text-red-600">{{ form.errors.total_amount }}</p>
                </div>
                <div v-if="can_edit_all">
                    <label class="block text-sm font-medium" for="headcount">N° personas</label>
                    <input id="headcount" v-model="form.headcount" type="number" inputmode="numeric" min="1" placeholder="12"
                        class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                    <p v-if="form.errors.headcount" class="mt-1 text-sm text-red-600">{{ form.errors.headcount }}</p>
                </div>
            </div>

            <div v-if="sharePreview" class="rounded-xl bg-teal-50 px-4 py-3 text-sm text-teal-800">
                Cada persona paga <span class="font-bold">S/ {{ sharePreview }}</span>
            </div>

            <div v-if="can_edit_all">
                <label class="block text-sm font-medium" for="recipient_name">¿Quién recibe el pago?</label>
                <input id="recipient_name" v-model="form.recipient_name" type="text" placeholder="Caro"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.recipient_name" class="mt-1 text-sm text-red-600">{{ form.errors.recipient_name }}</p>
            </div>

            <div v-if="can_edit_all">
                <label class="block text-sm font-medium" for="recipient_handle">Número Yape / Plin (opcional)</label>
                <input id="recipient_handle" v-model="form.recipient_handle" type="text" inputmode="numeric" placeholder="999 888 777"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.recipient_handle" class="mt-1 text-sm text-red-600">{{ form.errors.recipient_handle }}</p>
            </div>

            <div v-if="can_edit_all">
                <span class="block text-sm font-medium">Métodos de pago aceptados</span>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button v-for="mth in methods" :key="mth.value" type="button" @click="toggleMethod(mth.value)"
                        :class="[
                            'rounded-full border px-4 py-2 text-sm font-medium transition',
                            form.accepted_methods.includes(mth.value)
                                ? 'border-teal-600 bg-teal-600 text-white'
                                : 'border-slate-300 bg-white text-slate-700',
                        ]">
                        {{ mth.label }}
                    </button>
                </div>
                <p v-if="form.errors.accepted_methods" class="mt-1 text-sm text-red-600">{{ form.errors.accepted_methods }}</p>
            </div>

            <div v-if="can_edit_all">
                <label class="block text-sm font-medium" for="slug">Enlace público</label>
                <div class="mt-1 flex items-center rounded-xl border border-slate-300 px-3 focus-within:border-teal-600 focus-within:ring-1 focus-within:ring-teal-600">
                    <span class="shrink-0 text-sm text-slate-400">{{ linkOrigin }}/e/</span>
                    <input id="slug" v-model="form.slug" type="text" inputmode="url" autocapitalize="off" autocomplete="off"
                        class="w-full border-0 bg-transparent py-3 text-base focus:ring-0" />
                </div>
                <p v-if="form.errors.slug" class="mt-1 text-sm text-red-600">{{ form.errors.slug }}</p>
                <p v-else-if="slugChanged" class="mt-1 text-sm text-amber-700">
                    ⚠️ Al cambiar el enlace, el link anterior dejará de funcionar para quienes ya lo tengan.
                </p>
                <p v-else class="mt-1 text-xs text-slate-500">Solo minúsculas, números y guiones.</p>
            </div>
        </form>

        <div class="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white/90 px-4 py-3 backdrop-blur">
            <div class="mx-auto max-w-md">
                <button type="submit" form="event-form" :disabled="form.processing"
                    class="w-full rounded-xl bg-teal-600 px-4 py-3.5 text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60">
                    {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                </button>
            </div>
        </div>
    </main>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:js -- Edit.spec.js`
Expected: PASS (3 tests).

- [ ] **Step 5: Build to confirm the SFC compiles**

Run: `npm run build`
Expected: builds without errors (an `Edit-*.js` chunk is emitted).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Events/Edit.vue resources/js/Pages/Events/Edit.spec.js
git commit -m "feat: role-aware edit form (hide admin-only fields from organizer)"
```

---

### Task 4: Admin dashboard "Editar" entry point

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php` (add `slug` to the events payload)
- Modify: `resources/js/Pages/Admin/Dashboard.vue` (add an "Editar" link per event)
- Test: `resources/js/Pages/Admin/Dashboard.spec.js` (extend)

**Interfaces:**
- Consumes: existing `events` array items; each now also carries `slug: string`.
- Produces: each event row renders a link `href="/events/{slug}/edit"` labelled "Editar".

- [ ] **Step 1: Write the failing test**

Add this test inside the `describe('Admin/Dashboard', …)` block in `resources/js/Pages/Admin/Dashboard.spec.js`, and add `slug: 'bbq-caro'` to the event object in `makeProps`:

First, update the event object in `makeProps` to include the slug:

```js
        events: [
            { id: 1, slug: 'bbq-caro', name: 'BBQ Caro', organizer: 'Caro', status: 'active', headcount: 12, paid_count: 3, collected_cents: 12000, total_cents: 48000 },
        ],
```

Then add the test:

```js
    it('links each event to its edit screen', () => {
        const w = mount(Dashboard, { props: makeProps() });

        const link = w.findAll('a').find((a) => a.text() === 'Editar');
        expect(link).toBeTruthy();
        expect(link.attributes('href')).toBe('/events/bbq-caro/edit');
    });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:js -- Dashboard.spec.js`
Expected: FAIL — no "Editar" link exists yet.

- [ ] **Step 3: Add `slug` to the dashboard payload**

In `app/Http/Controllers/Admin/DashboardController.php`, add `'slug' => $e->slug,` to the `events` map (right after `'id' => $e->id,`):

```php
            'events' => $events->map(fn (Event $e) => [
                'id' => $e->id,
                'slug' => $e->slug,
                'name' => $e->name,
                'organizer' => $e->user?->name,
                'status' => $e->status,
                'headcount' => $e->headcount,
                'paid_count' => $e->paid_count,
                'collected_cents' => $e->paid_count * $e->share_cents,
                'total_cents' => $e->total_cents,
            ])->all(),
```

- [ ] **Step 4: Add the "Editar" link in the dashboard**

In `resources/js/Pages/Admin/Dashboard.vue`, inside the per-event `<li>`, replace the status row so it includes an edit link. Change this block:

```vue
                    <div class="flex items-center justify-between gap-2">
                        <span class="min-w-0 flex-1 truncate font-medium">{{ e.name }}</span>
                        <span class="shrink-0 text-xs text-slate-400">{{ statusLabels[e.status] ?? e.status }}</span>
                    </div>
```

to:

```vue
                    <div class="flex items-center justify-between gap-2">
                        <span class="min-w-0 flex-1 truncate font-medium">{{ e.name }}</span>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="text-xs text-slate-400">{{ statusLabels[e.status] ?? e.status }}</span>
                            <Link :href="`/events/${e.slug}/edit`" class="text-sm font-semibold text-teal-700">Editar</Link>
                        </div>
                    </div>
```

(`Link` is already imported in `Dashboard.vue`.)

- [ ] **Step 5: Run test to verify it passes**

Run: `npm run test:js -- Dashboard.spec.js`
Expected: PASS.

- [ ] **Step 6: Run both full suites**

Run: `npm run test:js`
Expected: PASS for all specs touched (Edit, Dashboard). Pre-existing `Login.spec.js` failures are unrelated to this work and may remain.

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php resources/js/Pages/Admin/Dashboard.vue resources/js/Pages/Admin/Dashboard.spec.js
git commit -m "feat: admin dashboard links each event to its edit screen"
```

---

## Notes for the implementer

- The worktree already contains an earlier "organizer edits everything" version of `UpdateEventRequest.php`, `EventController` `edit`/`update`, and `Events/Edit.vue`. Tasks 2 and 3 **replace** that behavior — do not preserve the all-fields organizer edit.
- There is an existing `tests/Feature/EditEventTest.php` in the worktree from the earlier version; Task 2 Step 1 replaces its contents entirely.
- The organizer's expense receipt ("comprobante del gasto") is intentionally **not** part of this plan — it is already editable on the Review screen and stays there.
