<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Ingresar" />

    <main class="mx-auto flex min-h-full max-w-md flex-col justify-center px-4 py-10">
        <header class="mb-8 text-center">
            <p class="text-sm font-semibold text-teal-700">CuentaClara</p>
            <h1 class="mt-1 text-2xl font-bold">Ingresa a tu cuenta</h1>
        </header>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium" for="email">Correo</label>
                <input id="email" v-model="form.email" type="email" autocomplete="email" inputmode="email"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium" for="password">Contraseña</label>
                <input id="password" v-model="form.password" type="password" autocomplete="current-password"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-600" />
                Recuérdame
            </label>

            <button type="submit" :disabled="form.processing"
                class="w-full rounded-xl bg-teal-600 px-4 py-3.5 text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60">
                {{ form.processing ? 'Ingresando…' : 'Ingresar' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            ¿No tienes cuenta?
            <Link href="/register" class="font-semibold text-teal-700">Crear cuenta</Link>
        </p>
    </main>
</template>
