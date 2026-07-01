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
