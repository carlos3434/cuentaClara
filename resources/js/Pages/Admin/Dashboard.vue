<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    review_mode: { type: String, default: 'manual' },
    totals: { type: Object, default: () => ({ events: 0, organizers: 0, shown: 0 }) },
    events: { type: Array, default: () => [] },
});

const soles = (c) => (c / 100).toFixed(2);

const statusLabels = { active: 'Activo', draft: 'Borrador', closed: 'Cerrado' };

const isAuto = computed(() => props.review_mode === 'auto');

function setMode(mode) {
    if (mode === props.review_mode) return;
    router.post('/admin/settings', { review_mode: mode }, { preserveScroll: true });
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head title="Administración" />

    <main class="mx-auto flex min-h-full max-w-md flex-col px-4 pb-10 pt-6">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Administración</h1>
            <button type="button" @click="logout" class="text-sm font-medium text-slate-500">Salir</button>
        </div>

        <nav class="mb-6 flex gap-2">
            <span class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Resumen</span>
            <Link href="/admin/users" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600">
                Usuarios
            </Link>
        </nav>

        <!-- Review mode -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Revisión de pagos</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ isAuto
                    ? 'Automática: la IA valida los pagos que coinciden; solo las excepciones van a revisión.'
                    : 'Manual: el organizador confirma cada pago subido.' }}
            </p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" @click="setMode('manual')"
                    :class="['rounded-xl px-4 py-3 text-sm font-semibold', !isAuto ? 'bg-teal-600 text-white' : 'border border-slate-300 text-slate-600']">
                    Manual
                </button>
                <button type="button" @click="setMode('auto')"
                    :class="['rounded-xl px-4 py-3 text-sm font-semibold', isAuto ? 'bg-teal-600 text-white' : 'border border-slate-300 text-slate-600']">
                    Automática
                </button>
            </div>
        </section>

        <!-- Totals -->
        <section class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Eventos</p>
                <p class="text-2xl font-bold">{{ totals.events }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Organizadores</p>
                <p class="text-2xl font-bold">{{ totals.organizers }}</p>
            </div>
        </section>

        <!-- Payments per event -->
        <section class="mt-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Pagos por evento
            </h2>

            <p v-if="events.length === 0" class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                Todavía no hay eventos.
            </p>

            <ul v-else class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white">
                <li v-for="e in events" :key="e.id" class="px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="min-w-0 flex-1 truncate font-medium">{{ e.name }}</span>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="text-xs text-slate-400">{{ statusLabels[e.status] ?? e.status }}</span>
                            <Link :href="`/events/${e.slug}/edit`" class="text-sm font-semibold text-teal-700">Editar</Link>
                        </div>
                    </div>
                    <p class="text-sm text-slate-500">Organizador: {{ e.organizer ?? '—' }}</p>
                    <div class="mt-1 flex items-center justify-between text-sm">
                        <span class="text-slate-600">{{ e.paid_count }} de {{ e.headcount }} pagaron</span>
                        <span class="font-medium text-teal-700">S/ {{ soles(e.collected_cents) }}
                            <span class="text-slate-400">/ {{ soles(e.total_cents) }}</span>
                        </span>
                    </div>
                </li>
            </ul>

            <p v-if="totals.events > totals.shown" class="mt-2 text-center text-xs text-slate-400">
                Mostrando {{ totals.shown }} de {{ totals.events }} eventos.
            </p>
        </section>
    </main>
</template>
