<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';

defineProps({
    users: { type: Array, default: () => [] },
});

const form = useForm({ name: '', email: '', password: '' });

function createUser() {
    form.post('/admin/users', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function toggle(u) {
    router.post(`/admin/users/${u.id}/toggle`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Usuarios — Administración" />

    <main class="mx-auto flex min-h-full max-w-md flex-col px-4 pb-10 pt-6">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Usuarios</h1>
            <Link href="/admin" class="text-sm font-medium text-slate-500">← Resumen</Link>
        </div>

        <!-- Create organizer -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Nuevo organizador</h2>
            <form class="mt-3 space-y-3" @submit.prevent="createUser">
                <div>
                    <input v-model="form.name" type="text" placeholder="Nombre" maxlength="80"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <input v-model="form.email" type="email" placeholder="Correo" maxlength="120"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <input v-model="form.password" type="password" placeholder="Contraseña (mín. 8)"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-base focus:border-teal-600 focus:ring-teal-600" />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>
                <button type="submit" :disabled="form.processing"
                    class="w-full rounded-xl bg-teal-600 px-4 py-3 text-sm font-semibold text-white active:scale-[0.99] disabled:opacity-50">
                    {{ form.processing ? 'Creando…' : 'Crear organizador' }}
                </button>
            </form>
        </section>

        <!-- Organizer list -->
        <section class="mt-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Organizadores ({{ users.length }})
            </h2>

            <p v-if="users.length === 0" class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                Todavía no hay organizadores.
            </p>

            <ul v-else class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white">
                <li v-for="u in users" :key="u.id" class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span :class="['inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium',
                            u.is_active ? 'bg-teal-100 text-teal-800' : 'bg-slate-200 text-slate-600']">
                            <Icon :name="u.is_active ? 'check-circle' : 'x-circle'" class="h-3.5 w-3.5" />
                            {{ u.is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                        <span class="min-w-0 flex-1 truncate font-medium">{{ u.name }}</span>
                    </div>
                    <p class="truncate text-sm text-slate-500">{{ u.email }}</p>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-sm text-slate-500">{{ u.events_count }} evento(s)</span>
                        <button type="button" @click="toggle(u)"
                            :class="['text-sm font-medium', u.is_active ? 'text-red-600' : 'text-teal-700']">
                            {{ u.is_active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </div>
                </li>
            </ul>
        </section>
    </main>
</template>
