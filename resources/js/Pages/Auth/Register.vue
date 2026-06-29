<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Crear cuenta" />

    <main class="mx-auto flex min-h-full max-w-md flex-col justify-center px-4 py-10">
        <header class="mb-8 text-center">
            <p class="text-sm font-semibold text-teal-700">CuentaClara</p>
            <h1 class="mt-1 text-2xl font-bold">Crea tu cuenta</h1>
            <p class="mt-1 text-sm text-slate-500">Para organizar y cobrar tus eventos.</p>
        </header>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium" for="name">Nombre</label>
                <input id="name" v-model="form.name" type="text" autocomplete="name"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium" for="email">Correo</label>
                <input id="email" v-model="form.email" type="email" autocomplete="email" inputmode="email"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium" for="password">Contraseña</label>
                <input id="password" v-model="form.password" type="password" autocomplete="new-password"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium" for="password_confirmation">Confirmar contraseña</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
            </div>

            <button type="submit" :disabled="form.processing"
                class="w-full rounded-xl bg-teal-600 px-4 py-3.5 text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60">
                {{ form.processing ? 'Creando…' : 'Crear cuenta' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            ¿Ya tienes cuenta?
            <Link href="/login" class="font-semibold text-teal-700">Ingresar</Link>
        </p>
    </main>
</template>
