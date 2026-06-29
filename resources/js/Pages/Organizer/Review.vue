<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    summary: Object,
    review: { type: Array, default: () => [] },
    participants: { type: Array, default: () => [] },
    expenses: { type: Array, default: () => [] },
    share_url: String,
});

const methodLabels = { yape: 'Yape', plin: 'Plin', bank_transfer: 'Transferencia' };
const reasonLabels = {
    amount_mismatch: 'Monto no coincide',
    low_confidence: 'Baja confianza',
    not_a_receipt: 'No parece un voucher',
    amount_unreadable: 'Monto ilegible',
    ai_unavailable: 'IA no disponible',
    organizer_rejected: 'Rechazado',
};
const statusBadge = {
    paid: { label: 'Pagó', cls: 'bg-teal-100 text-teal-800' },
    review: { label: 'En revisión', cls: 'bg-amber-100 text-amber-800' },
    rejected: { label: 'Rechazado', cls: 'bg-red-100 text-red-800' },
    pending: { label: 'Pendiente', cls: 'bg-slate-100 text-slate-600' },
};

const soles = (c) => (c / 100).toFixed(2);

const recipient = computed(() => {
    const e = props.event;
    return e.recipient_handle ? `${e.recipient_name} (${e.recipient_handle})` : e.recipient_name;
});

function whatsappUrl(text) {
    return `https://wa.me/?text=${encodeURIComponent(text)}`;
}
const groupReminderUrl = computed(() =>
    whatsappUrl(
        `¡Hola! 👋 Recordatorio de "${props.event.name}": cada uno paga S/ ${soles(props.event.share_cents)} ` +
        `a ${recipient.value}. Sube tu voucher aquí: ${props.event.public_url}`,
    ),
);
function participantReminderUrl(p) {
    return whatsappUrl(
        `Hola ${p.name} 👋 Te recuerdo tu parte de "${props.event.name}": S/ ${soles(props.event.share_cents)} ` +
        `a ${recipient.value}. Sube tu voucher aquí: ${props.event.public_url}`,
    );
}

const progress = computed(() =>
    props.event.total_cents > 0
        ? Math.min(100, Math.round((props.summary.collected_cents / props.event.total_cents) * 100))
        : 0,
);

function approve(r) {
    router.post(`/events/${props.event.slug}/receipts/${r.id}/approve`, {}, { preserveScroll: true });
}
function reject(r) {
    router.post(`/events/${props.event.slug}/receipts/${r.id}/reject`, {}, { preserveScroll: true });
}
function markCash(p) {
    router.post(`/events/${props.event.slug}/participants/${p.id}/cash`, {}, { preserveScroll: true });
}

// Organizer's own expense receipt (store-only)
const expenseForm = useForm({ image: null, note: '' });
const expensePreview = ref(null);

function onExpenseFile(e) {
    const file = e.target.files[0] ?? null;
    expenseForm.image = file;
    expensePreview.value = file ? URL.createObjectURL(file) : null;
}
function submitExpense() {
    expenseForm.post(`/events/${props.event.slug}/expenses`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            expenseForm.reset();
            expensePreview.value = null;
        },
    });
}
function deleteExpense(ex) {
    router.delete(`/events/${props.event.slug}/expenses/${ex.id}`, { preserveScroll: true });
}

const expanded = ref(new Set());
function toggleVoucher(id) {
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
}

function closeEvent() {
    router.post(`/events/${props.event.slug}/close`, {}, { preserveScroll: true });
}
function reopenEvent() {
    router.post(`/events/${props.event.slug}/reopen`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Revisar — ${event.name}`" />

    <main class="mx-auto flex min-h-full max-w-md flex-col px-4 pb-10 pt-6">
        <div class="mb-4 flex items-center justify-between">
            <Link href="/events" class="text-sm font-medium text-slate-500">← Mis eventos</Link>
            <Link :href="share_url" class="text-sm font-semibold text-teal-700">Compartir link</Link>
        </div>

        <header class="mb-5 flex items-start justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ event.name }}</h1>
            <span v-if="event.status === 'closed'"
                class="mt-1 shrink-0 rounded-full bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700">
                Cerrado
            </span>
        </header>

        <!-- Collected / total -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-end justify-between">
                <div>
                    <p class="text-sm text-slate-500">Cobrado</p>
                    <p class="text-2xl font-bold text-teal-700">S/ {{ soles(summary.collected_cents) }}</p>
                </div>
                <p class="text-sm text-slate-500">de S/ {{ soles(event.total_cents) }}</p>
            </div>
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-teal-600" :style="{ width: progress + '%' }" />
            </div>
            <p class="mt-2 text-sm text-slate-500">
                {{ summary.paid_count }} de {{ summary.headcount }} pagaron · faltan S/ {{ soles(summary.pending_cents) }}
            </p>

            <a v-if="summary.paid_count < summary.headcount" :href="groupReminderUrl" target="_blank" rel="noopener"
                class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-3 text-sm font-semibold text-white active:scale-[0.99]">
                Recordar al grupo por WhatsApp
            </a>
        </section>

        <!-- Needs review -->
        <section class="mt-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Por revisar ({{ summary.review_count }})
            </h2>

            <p v-if="review.length === 0" class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                Nada por revisar 🎉
            </p>

            <div v-for="r in review" :key="r.id" class="mb-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <img :src="r.image_url" :alt="`Voucher de ${r.participant}`" class="max-h-72 w-full bg-slate-50 object-contain" />
                <div class="p-4">
                    <p class="font-semibold">{{ r.participant }}</p>
                    <p v-if="r.reason_code" class="mt-0.5 text-sm font-medium text-amber-700">
                        {{ reasonLabels[r.reason_code] ?? r.reason_code }}
                    </p>

                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Monto leído</dt>
                            <dd class="font-medium">
                                {{ r.amount_cents != null ? 'S/ ' + soles(r.amount_cents) : '—' }}
                                <span class="text-slate-400">(esperado S/ {{ soles(event.share_cents) }})</span>
                            </dd>
                        </div>
                        <div class="flex justify-between"><dt class="text-slate-500">Fecha</dt><dd class="font-medium">{{ r.date ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">A</dt><dd class="font-medium">{{ r.recipient ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Método</dt><dd class="font-medium">{{ methodLabels[r.method] ?? r.method ?? '—' }}</dd></div>
                        <div v-if="r.confidence != null" class="flex justify-between"><dt class="text-slate-500">Confianza IA</dt><dd class="font-medium">{{ Math.round(r.confidence * 100) }}%</dd></div>
                    </dl>

                    <div class="mt-4 flex gap-2">
                        <button type="button" @click="approve(r)"
                            class="flex-1 rounded-xl bg-teal-600 px-4 py-3 text-sm font-semibold text-white active:scale-[0.99]">
                            Aprobar
                        </button>
                        <button type="button" @click="reject(r)"
                            class="flex-1 rounded-xl border border-red-300 px-4 py-3 text-sm font-semibold text-red-700 active:scale-[0.99]">
                            Rechazar
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Participants -->
        <section class="mt-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Participantes ({{ participants.length }})
            </h2>

            <p v-if="participants.length === 0" class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                Nadie ha subido su voucher todavía.
            </p>

            <ul v-else class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white">
                <li v-for="p in participants" :key="p.id" class="px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-2">
                            <span :class="['shrink-0 rounded-full px-2.5 py-1 text-xs font-medium', (statusBadge[p.status] ?? statusBadge.pending).cls]">
                                {{ (statusBadge[p.status] ?? statusBadge.pending).label }}
                            </span>
                            <span class="truncate font-medium">{{ p.name }}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <button v-if="p.receipt && p.receipt.image_url" type="button" @click="toggleVoucher(p.id)"
                                class="text-sm font-medium text-teal-700">
                                {{ expanded.has(p.id) ? 'Ocultar' : 'Ver voucher' }}
                            </button>
                            <a v-if="p.status !== 'paid'" :href="participantReminderUrl(p)" target="_blank" rel="noopener"
                                class="text-sm font-medium text-[#1da851]">
                                Recordar
                            </a>
                            <button v-if="p.status !== 'paid'" type="button" @click="markCash(p)" class="text-sm font-medium text-teal-700">
                                Efectivo
                            </button>
                        </div>
                    </div>

                    <!-- Inspect the participant's uploaded voucher -->
                    <div v-if="expanded.has(p.id) && p.receipt" class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <img v-if="p.receipt.image_url" :src="p.receipt.image_url" :alt="`Voucher de ${p.name}`"
                            class="max-h-72 w-full rounded-lg bg-white object-contain" />
                        <dl class="mt-3 space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Monto leído</dt>
                                <dd class="font-medium">
                                    {{ p.receipt.amount_cents != null ? 'S/ ' + soles(p.receipt.amount_cents) : '—' }}
                                    <span class="text-slate-400">(esperado S/ {{ soles(event.share_cents) }})</span>
                                </dd>
                            </div>
                            <div class="flex justify-between"><dt class="text-slate-500">Fecha</dt><dd class="font-medium">{{ p.receipt.date ?? '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">A</dt><dd class="font-medium">{{ p.receipt.recipient ?? '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Método</dt><dd class="font-medium">{{ methodLabels[p.receipt.method] ?? p.receipt.method ?? '—' }}</dd></div>
                            <div v-if="p.receipt.confidence != null" class="flex justify-between"><dt class="text-slate-500">Confianza IA</dt><dd class="font-medium">{{ Math.round(p.receipt.confidence * 100) }}%</dd></div>
                        </dl>
                        <div class="mt-3 flex gap-2">
                            <button type="button" @click="approve(p.receipt)"
                                class="flex-1 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white active:scale-[0.99]">
                                Confirmar pago
                            </button>
                            <button type="button" @click="reject(p.receipt)"
                                class="flex-1 rounded-xl border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 active:scale-[0.99]">
                                Rechazar
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Organizer's own expense receipt -->
        <section class="mt-6">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Comprobante del gasto
            </h2>
            <p class="mb-3 text-sm text-slate-500">
                Sube la evidencia de lo que pagaste (cancha, restaurante, regalo…).
            </p>

            <ul v-if="expenses.length" class="mb-4 space-y-3">
                <li v-for="ex in expenses" :key="ex.id"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <img :src="ex.image_url" alt="Comprobante del gasto" class="max-h-60 w-full bg-slate-50 object-contain" />
                    <div class="flex items-center justify-between p-3">
                        <span class="text-sm text-slate-600">{{ ex.note || 'Sin nota' }}</span>
                        <button type="button" @click="deleteExpense(ex)" class="text-sm font-medium text-red-600">
                            Eliminar
                        </button>
                    </div>
                </li>
            </ul>

            <form class="space-y-3" @submit.prevent="submitExpense">
                <label for="expense-image"
                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-white px-4 py-6 text-center">
                    <template v-if="!expensePreview">
                        <span class="text-2xl">🧾</span>
                        <span class="mt-1 text-sm font-medium text-slate-600">Agregar comprobante</span>
                    </template>
                    <img v-else :src="expensePreview" alt="Vista previa" class="max-h-48 rounded-lg" />
                </label>
                <input id="expense-image" type="file" accept="image/*" capture="environment" class="sr-only" @change="onExpenseFile" />
                <p v-if="expenseForm.errors.image" class="text-sm text-red-600">{{ expenseForm.errors.image }}</p>

                <input v-model="expenseForm.note" type="text" maxlength="200" placeholder="Nota (opcional): ej. Alquiler de cancha"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />

                <button type="submit" :disabled="expenseForm.processing || !expenseForm.image"
                    class="w-full rounded-xl bg-slate-800 px-4 py-3 text-sm font-semibold text-white active:scale-[0.99] disabled:opacity-50">
                    {{ expenseForm.processing ? 'Subiendo…' : 'Subir comprobante del gasto' }}
                </button>
            </form>
        </section>

        <!-- Close / reopen -->
        <section class="mt-8 border-t border-slate-200 pt-5">
            <button v-if="event.status === 'active'" type="button" @click="closeEvent"
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-600 active:scale-[0.99]">
                Cerrar evento (no más vouchers)
            </button>
            <button v-else type="button" @click="reopenEvent"
                class="w-full rounded-xl border border-teal-300 px-4 py-3 text-sm font-semibold text-teal-700 active:scale-[0.99]">
                Reabrir evento
            </button>
        </section>
    </main>
</template>
