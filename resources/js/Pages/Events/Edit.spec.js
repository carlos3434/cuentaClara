import { mount } from '@vue/test-utils';

const m = vi.hoisted(() => ({ formPut: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await vi.importActual('vue');
    return {
        Head: { template: '<div><slot /></div>' },
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        useForm: (data) => reactive({ ...data, errors: {}, processing: false, put: m.formPut }),
    };
});

import Edit from './Edit.vue';

function eventProp(overrides = {}) {
    return {
        slug: 'mi-evento',
        name: 'BBQ Caro',
        event_date: '2026-07-10',
        total_amount: '480.00',
        headcount: 12,
        recipient_name: 'Caro',
        recipient_handle: '999888777',
        accepted_methods: ['yape'],
        pay_deadline: '2026-07-08',
        public_url: 'http://localhost/e/mi-evento',
        ...overrides,
    };
}

beforeEach(() => m.formPut.mockClear());

describe('Events/Edit', () => {
    it('organizer sees only dates and amount', () => {
        const w = mount(Edit, { props: { event: eventProp(), can_edit_all: false } });

        expect(w.find('#event_date').exists()).toBe(true);
        expect(w.find('#pay_deadline').exists()).toBe(true);
        expect(w.find('#total_amount').exists()).toBe(true);

        expect(w.find('#name').exists()).toBe(false);
        expect(w.find('#headcount').exists()).toBe(false);
        expect(w.find('#recipient_name').exists()).toBe(false);
        expect(w.find('#slug').exists()).toBe(false);
    });

    it('admin sees all fields including the link', () => {
        const w = mount(Edit, { props: { event: eventProp(), can_edit_all: true } });

        ['#name', '#event_date', '#pay_deadline', '#total_amount', '#headcount', '#recipient_name', '#recipient_handle', '#slug']
            .forEach((sel) => expect(w.find(sel).exists()).toBe(true));
        expect(w.text()).toContain('Yape');
    });

    it('submits to the original slug', async () => {
        const w = mount(Edit, { props: { event: eventProp({ slug: 'mi-evento' }), can_edit_all: true } });

        await w.find('#event-form').trigger('submit.prevent');

        expect(m.formPut).toHaveBeenCalledWith('/events/mi-evento');
    });
});
