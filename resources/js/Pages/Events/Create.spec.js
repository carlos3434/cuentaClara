import { mount } from '@vue/test-utils';

// Reactive useForm so v-model + the share preview computed react to input.
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await vi.importActual('vue');
    return {
        Head: { template: '<div><slot /></div>' },
        router: { post: () => {} },
        usePage: () => ({ props: { auth: { user: { name: 'Caro' } } } }),
        useForm: (data) => reactive({ ...data, errors: {}, processing: false, post() {}, reset() {} }),
    };
});

import Create from './Create.vue';

describe('Events/Create', () => {
    it('renders all the event fields and method pills', () => {
        const w = mount(Create);

        ['#name', '#event_date', '#total_amount', '#headcount', '#recipient_name', '#recipient_handle', '#pay_deadline', '#expense_image']
            .forEach((sel) => expect(w.find(sel).exists()).toBe(true));

        expect(w.text()).toContain('Yape');
        expect(w.text()).toContain('Plin');
        expect(w.text()).toContain('Transferencia');
        expect(w.text()).toContain('Crear evento y obtener link');
    });

    it('previews the per-person share from total and headcount', async () => {
        const w = mount(Create);

        await w.find('#total_amount').setValue('480');
        await w.find('#headcount').setValue('12');

        expect(w.text()).toContain('Cada persona paga');
        expect(w.text()).toContain('S/ 40.00');
    });

    it('greets the signed-in organizer and offers logout', () => {
        const w = mount(Create);

        expect(w.text()).toContain('Hola Caro');
        expect(w.text()).toContain('Salir');
    });
});
