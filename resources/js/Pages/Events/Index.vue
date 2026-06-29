<script setup>
import { computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';

defineProps({
    events: { type: Array, default: () => [] },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? null);

const statusStyles = {
    active: 'bg-teal-100 text-teal-800',
    draft: 'bg-slate-100 text-slate-600',
    closed: 'bg-slate-200 text-slate-700',
};
const statusLabels = {
    active: 'Activo',
    draft: 'Borrador',
    closed: 'Cerrado',
};

function soles(cents) {
    return (cents / 100).toFixed(2);
}

function formatDate(iso) {
    const [y, m, d] = iso.split('-');
    const months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return `${parseInt(d, 10)} ${months[parseInt(m, 10) - 1]} ${y}`;
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head title="Mis eventos" />

    <main class="mx-auto flex min-h-full max-w-md flex-col px-4 pb-28 pt-6">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm font-semibold text-teal-700">CuentaClara</p>
            <button v-if="userName" type="button" @click="logout" class="text-sm font-medium text-slate-500">
                Salir
            </button>
        </div>

        <header class="mb-6">
            <h1 class="text-2xl font-bold">Mis eventos</h1>
            <p v-if="userName" class="mt-1 text-sm text-slate-500">Hola {{ userName }}.</p>
        </header>

        <!-- Empty state -->
        <div v-if="events.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-teal-50 text-2xl">📋</div>
            <p class="mt-3 font-medium">Aún no tienes eventos</p>
            <p class="mt-1 text-sm text-slate-500">Crea el primero y comparte el link con tu grupo.</p>
        </div>

        <!-- Event list -->
        <ul v-else class="space-y-3">
            <li v-for="event in events" :key="event.slug">
                <Link :href="event.review_url"
                    class="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition active:scale-[0.99]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ event.name }}</p>
                            <p class="mt-0.5 text-sm text-slate-500">{{ formatDate(event.event_date) }}</p>
                        </div>
                        <span :class="['shrink-0 rounded-full px-2.5 py-1 text-xs font-medium', statusStyles[event.status] ?? statusStyles.draft]">
                            {{ statusLabels[event.status] ?? event.status }}
                        </span>
                    </div>

                    <div class="mt-3 flex items-end justify-between">
                        <div>
                            <p class="text-xs text-slate-500">Total · {{ event.headcount }} personas</p>
                            <p class="text-base font-semibold">S/ {{ soles(event.total_cents) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">Cada uno paga</p>
                            <p class="text-base font-semibold text-teal-700">S/ {{ soles(event.share_cents) }}</p>
                        </div>
                    </div>

                    <p class="mt-3 text-sm font-medium text-teal-700">Revisar pagos →</p>
                </Link>
            </li>
        </ul>

        <p v-if="events.length" class="mt-4 text-center text-xs text-slate-400">
            El seguimiento de pagos (cobrado / pendiente) llega con la siguiente entrega.
        </p>

        <!-- Sticky create CTA -->
        <div class="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white/90 px-4 py-3 backdrop-blur">
            <div class="mx-auto max-w-md">
                <Link href="/events/create"
                    class="block w-full rounded-xl bg-teal-600 px-4 py-3.5 text-center text-base font-semibold text-white shadow-sm transition active:scale-[0.99]">
                    Crear evento
                </Link>
            </div>
        </div>
    </main>
</template>
