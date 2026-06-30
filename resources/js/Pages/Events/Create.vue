<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? null);

function logout() {
    router.post('/logout');
}

const methods = [
    { value: 'yape', label: 'Yape' },
    { value: 'plin', label: 'Plin' },
    { value: 'bank_transfer', label: 'Transferencia' },
];

const form = useForm({
    name: '',
    event_date: '',
    total_amount: '',
    headcount: '',
    recipient_name: '',
    recipient_handle: '',
    accepted_methods: ['yape'],
    pay_deadline: '',
    expense_image: null,
    expense_note: '',
});

const expensePreview = ref(null);

function onExpenseFile(e) {
    const file = e.target.files[0] ?? null;
    form.expense_image = file;
    expensePreview.value = file ? URL.createObjectURL(file) : null;
}

const sharePreview = computed(() => {
    const total = parseFloat(form.total_amount);
    const count = parseInt(form.headcount, 10);
    if (!total || !count || count < 1) return null;
    return (total / count).toFixed(2);
});

function toggleMethod(value) {
    const i = form.accepted_methods.indexOf(value);
    if (i === -1) form.accepted_methods.push(value);
    else form.accepted_methods.splice(i, 1);
}

function submit() {
    form.post('/events', { forceFormData: true });
}
</script>

<template>
    <Head title="Crear evento" />

    <main class="mx-auto flex min-h-full max-w-md flex-col px-4 pb-28 pt-6">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm font-semibold text-teal-700">CuentaClara</p>
            <button v-if="userName" type="button" @click="logout"
                class="text-sm font-medium text-slate-500">
                Salir
            </button>
        </div>

        <header class="mb-6">
            <h1 class="text-2xl font-bold">Crear evento</h1>
            <p v-if="userName" class="mt-1 text-sm text-slate-500">
                Hola {{ userName }} — crea el evento y comparte el link por WhatsApp.
            </p>
            <p v-else class="mt-1 text-sm text-slate-500">
                Crea el evento y comparte el link por WhatsApp.
            </p>
        </header>

        <form id="event-form" class="space-y-5" @submit.prevent="submit">
            <div>
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

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium" for="total_amount">Monto total (S/)</label>
                    <input id="total_amount" v-model="form.total_amount" type="number" inputmode="decimal" step="0.01" min="0" placeholder="480"
                        class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                    <p v-if="form.errors.total_amount" class="mt-1 text-sm text-red-600">{{ form.errors.total_amount }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium" for="headcount">N° personas</label>
                    <input id="headcount" v-model="form.headcount" type="number" inputmode="numeric" min="1" placeholder="12"
                        class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                    <p v-if="form.errors.headcount" class="mt-1 text-sm text-red-600">{{ form.errors.headcount }}</p>
                </div>
            </div>

            <div v-if="sharePreview" class="rounded-xl bg-teal-50 px-4 py-3 text-sm text-teal-800">
                Cada persona paga <span class="font-bold">S/ {{ sharePreview }}</span>
            </div>

            <div>
                <label class="block text-sm font-medium" for="recipient_name">¿Quién recibe el pago?</label>
                <input id="recipient_name" v-model="form.recipient_name" type="text" placeholder="Caro"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.recipient_name" class="mt-1 text-sm text-red-600">{{ form.errors.recipient_name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium" for="recipient_handle">Número Yape / Plin (opcional)</label>
                <input id="recipient_handle" v-model="form.recipient_handle" type="text" inputmode="numeric" placeholder="999 888 777"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.recipient_handle" class="mt-1 text-sm text-red-600">{{ form.errors.recipient_handle }}</p>
            </div>

            <div>
                <span class="block text-sm font-medium">Métodos de pago aceptados</span>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button v-for="m in methods" :key="m.value" type="button" @click="toggleMethod(m.value)"
                        :class="[
                            'rounded-full border px-4 py-2 text-sm font-medium transition',
                            form.accepted_methods.includes(m.value)
                                ? 'border-teal-600 bg-teal-600 text-white'
                                : 'border-slate-300 bg-white text-slate-700',
                        ]">
                        {{ m.label }}
                    </button>
                </div>
                <p v-if="form.errors.accepted_methods" class="mt-1 text-sm text-red-600">{{ form.errors.accepted_methods }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium" for="pay_deadline">Pagar antes del</label>
                <input id="pay_deadline" v-model="form.pay_deadline" type="date"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.pay_deadline" class="mt-1 text-sm text-red-600">{{ form.errors.pay_deadline }}</p>
            </div>

            <div>
                <span class="block text-sm font-medium">Comprobante del gasto (opcional)</span>
                <p class="mt-0.5 text-xs text-slate-500">Sube la evidencia de lo que pagaste (cancha, restaurante…).</p>
                <label for="expense_image"
                    class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-white px-4 py-6 text-center">
                    <template v-if="!expensePreview">
                        <span class="text-2xl">🧾</span>
                        <span class="mt-1 text-sm font-medium text-slate-600">Agregar comprobante</span>
                    </template>
                    <img v-else :src="expensePreview" alt="Vista previa del comprobante" class="max-h-48 rounded-lg" />
                </label>
                <input id="expense_image" type="file" accept="image/*" class="sr-only" @change="onExpenseFile" />
                <p v-if="form.errors.expense_image" class="mt-1 text-sm text-red-600">{{ form.errors.expense_image }}</p>
                <input v-if="form.expense_image" v-model="form.expense_note" type="text" maxlength="200"
                    placeholder="Nota (opcional): ej. Alquiler de cancha"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
            </div>
        </form>

        <div class="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white/90 px-4 py-3 backdrop-blur">
            <div class="mx-auto max-w-md">
                <button type="submit" form="event-form" :disabled="form.processing"
                    class="w-full rounded-xl bg-teal-600 px-4 py-3.5 text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60">
                    {{ form.processing ? 'Creando…' : 'Crear evento y obtener link' }}
                </button>
            </div>
        </div>
    </main>
</template>
