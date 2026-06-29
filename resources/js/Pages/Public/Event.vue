<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    participant: { type: Object, default: null },
});

const page = usePage();
const justUploaded = computed(() => page.props.flash?.uploaded === true);

const methodLabels = {
    yape: 'Yape',
    plin: 'Plin',
    bank_transfer: 'Transferencia',
};

const badges = {
    pending: { label: 'En revisión', cls: 'bg-amber-100 text-amber-800' },
    confirmed: { label: 'Confirmado', cls: 'bg-teal-100 text-teal-800' },
    review: { label: 'Revisar', cls: 'bg-red-100 text-red-800' },
};
const badge = computed(() =>
    props.participant && props.participant.badge !== 'none'
        ? badges[props.participant.badge]
        : null,
);

const shareSoles = computed(() => (props.event.share_cents / 100).toFixed(2));

function formatDate(iso) {
    const [y, m, d] = iso.split('-');
    const months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return `${parseInt(d, 10)} ${months[parseInt(m, 10) - 1]}`;
}

// One-screen upload: name (only for new participants) + photo.
const form = useForm({
    name: '',
    image: null,
});
const preview = ref(null);

function onFile(e) {
    const file = e.target.files[0] ?? null;
    form.image = file;
    preview.value = file ? URL.createObjectURL(file) : null;
}

function submit() {
    form.post(`/e/${props.event.slug}/receipts`, {
        forceFormData: true,
        onSuccess: () => {
            form.reset('image');
            preview.value = null;
        },
    });
}
</script>

<template>
    <Head :title="event.name" />

    <main class="mx-auto flex min-h-full max-w-md flex-col px-4 py-8">
        <header class="mb-6">
            <p class="text-sm font-semibold text-teal-700">CuentaClara</p>
            <h1 class="mt-1 text-2xl font-bold">{{ event.name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ formatDate(event.event_date) }}</p>
        </header>

        <!-- Success confirmation after an upload -->
        <div v-if="justUploaded" class="mb-5 rounded-2xl bg-teal-50 px-5 py-4 text-center">
            <p class="text-2xl">✅</p>
            <p class="mt-1 font-semibold text-teal-800">¡Listo! Gracias{{ participant ? `, ${participant.name}` : '' }}.</p>
            <p class="mt-1 text-sm text-teal-700">El organizador confirmará tu pago.</p>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-slate-500">Tu parte</p>
                    <p class="text-3xl font-bold text-teal-700">S/ {{ shareSoles }}</p>
                </div>
                <span v-if="badge" :class="['rounded-full px-2.5 py-1 text-xs font-medium', badge.cls]">
                    {{ badge.label }}
                </span>
            </div>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Pagar a</dt>
                    <dd class="font-medium">
                        {{ event.recipient_name }}
                        <span v-if="event.recipient_handle" class="text-slate-500">({{ event.recipient_handle }})</span>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Métodos</dt>
                    <dd class="font-medium">
                        {{ event.accepted_methods.map((m) => methodLabels[m] ?? m).join(' · ') }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Pagar antes del</dt>
                    <dd class="font-medium">{{ formatDate(event.pay_deadline) }}</dd>
                </div>
            </dl>
        </section>

        <!-- Closed event: no more uploads -->
        <div v-if="event.status !== 'active'" class="mt-6 rounded-2xl bg-slate-100 px-4 py-5 text-center">
            <p class="font-medium text-slate-700">Este evento está cerrado</p>
            <p class="mt-1 text-sm text-slate-500">El organizador ya no recibe vouchers.</p>
        </div>

        <!-- Upload form -->
        <form v-else class="mt-6 space-y-4" @submit.prevent="submit">
            <p class="text-base font-semibold">
                {{ participant ? 'Subir otro voucher' : 'Ya pagué, subir voucher' }}
            </p>

            <div v-if="!participant">
                <label class="block text-sm font-medium" for="name">Tu nombre</label>
                <input id="name" v-model="form.name" type="text" placeholder="José"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="image"
                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-white px-4 py-8 text-center">
                    <template v-if="!preview">
                        <span class="text-3xl">📷</span>
                        <span class="mt-2 text-sm font-medium text-slate-600">Tomar foto o elegir de galería</span>
                    </template>
                    <img v-else :src="preview" alt="Vista previa del voucher" class="max-h-56 rounded-lg" />
                </label>
                <input id="image" type="file" accept="image/*" capture="environment" class="sr-only" @change="onFile" />
                <p v-if="form.errors.image" class="mt-1 text-sm text-red-600">{{ form.errors.image }}</p>
            </div>

            <button type="submit" :disabled="form.processing || !form.image"
                class="w-full rounded-xl bg-teal-600 px-4 py-3.5 text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-50">
                {{ form.processing ? 'Enviando…' : 'Enviar voucher' }}
            </button>
        </form>
    </main>
</template>
