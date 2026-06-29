<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    event: Object,
    public_url: String,
});

const copied = ref(false);

const shareSoles = computed(() => (props.event.share_cents / 100).toFixed(2));
const totalSoles = computed(() => (props.event.total_cents / 100).toFixed(2));

const whatsappText = computed(() =>
    `Hola 👋 Te invito a pagar tu parte de "${props.event.name}". ` +
    `Tu parte: S/ ${shareSoles.value}. Sube tu voucher aquí: ${props.public_url}`,
);
const whatsappUrl = computed(
    () => `https://wa.me/?text=${encodeURIComponent(whatsappText.value)}`,
);

async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.public_url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (e) {
        // Clipboard API may be unavailable; the link stays selectable below.
    }
}
</script>

<template>
    <Head title="Evento creado" />

    <main class="mx-auto flex min-h-full max-w-md flex-col px-4 py-8">
        <div class="mb-6 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-teal-100 text-3xl">🎉</div>
            <h1 class="mt-4 text-2xl font-bold">¡Evento creado!</h1>
            <p class="mt-1 text-sm text-slate-500">Comparte este link con tu grupo.</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-base font-semibold">{{ event.name }}</p>
            <p class="mt-1 text-sm text-slate-500">
                Total S/ {{ totalSoles }} · {{ event.headcount }} personas
            </p>
            <p class="mt-3 rounded-xl bg-teal-50 px-4 py-3 text-sm text-teal-800">
                Cada persona paga <span class="font-bold">S/ {{ shareSoles }}</span>
            </p>

            <label class="mt-5 block text-sm font-medium">Link para compartir</label>
            <div class="mt-1 flex items-stretch gap-2">
                <input :value="public_url" readonly
                    class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-3 text-sm text-slate-700"
                    @focus="(e) => e.target.select()" />
                <button type="button" @click="copyLink"
                    class="flex shrink-0 items-center gap-1.5 rounded-xl border border-teal-600 px-4 text-sm font-semibold text-teal-700 active:scale-95">
                    <Icon name="copy" class="h-4 w-4" />
                    {{ copied ? 'Copiado' : 'Copiar' }}
                </button>
            </div>
            <p v-if="copied" class="mt-1 text-xs text-teal-700">Link copiado</p>
        </div>

        <a :href="whatsappUrl" target="_blank" rel="noopener"
            class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-3.5 text-base font-semibold text-white shadow-sm active:scale-[0.99]">
            <Icon name="whatsapp" class="h-5 w-5" />
            Compartir por WhatsApp
        </a>

        <Link href="/events"
            class="mt-4 block text-center text-sm font-medium text-teal-700">
            Volver a mis eventos
        </Link>
        <Link href="/events/create"
            class="mt-2 block text-center text-sm font-medium text-slate-500">
            Crear otro evento
        </Link>
    </main>
</template>
