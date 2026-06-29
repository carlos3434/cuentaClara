import { mount } from '@vue/test-utils';

const m = vi.hoisted(() => ({ formPost: vi.fn(), routerPost: vi.fn() }));

// Reactive useForm so v-model + computeds react; spies to assert submit/logout.
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await vi.importActual('vue');
    return {
        Head: { template: '<div><slot /></div>' },
        router: { post: m.routerPost },
        usePage: () => ({ props: { auth: { user: { name: 'Caro' } } } }),
        useForm: (data) => reactive({ ...data, errors: {}, processing: false, post: m.formPost, reset: vi.fn() }),
    };
});

import Create from './Create.vue';

beforeEach(() => {
    m.formPost.mockClear();
    m.routerPost.mockClear();
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:preview');
});

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

    // --- interactions ---

    it('toggles a payment method pill', async () => {
        const w = mount(Create);
        const plin = w.findAll('button').find((b) => b.text() === 'Plin');

        expect(plin.classes()).not.toContain('bg-teal-600');
        await plin.trigger('click');
        expect(plin.classes()).toContain('bg-teal-600');
    });

    it('submits the create-event form', async () => {
        const w = mount(Create);

        await w.find('#event-form').trigger('submit.prevent');

        expect(m.formPost).toHaveBeenCalledWith('/events', expect.objectContaining({ forceFormData: true }));
    });

    it('logs out', async () => {
        const w = mount(Create);

        await w.findAll('button').find((b) => b.text() === 'Salir').trigger('click');

        expect(m.routerPost).toHaveBeenCalledWith('/logout');
    });

    it('previews a selected expense receipt and reveals the note field', async () => {
        const w = mount(Create);
        const input = w.find('#expense_image');
        Object.defineProperty(input.element, 'files', {
            value: [new File(['x'], 'gasto.jpg', { type: 'image/jpeg' })],
            configurable: true,
        });

        await input.trigger('change');

        expect(globalThis.URL.createObjectURL).toHaveBeenCalled();
        expect(w.find('input[placeholder^="Nota"]').exists()).toBe(true);
    });
});
