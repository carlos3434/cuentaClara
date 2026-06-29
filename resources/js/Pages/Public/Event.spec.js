import { mount } from '@vue/test-utils';

// Inertia + page props are mocked so we can mount the page in isolation.
const mocks = vi.hoisted(() => ({ pageProps: { flash: {}, auth: { user: null } } }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    usePage: () => ({ props: mocks.pageProps }),
    useForm: (data) => ({ ...data, errors: {}, processing: false, post: () => {}, reset: () => {} }),
}));

import Event from './Event.vue';

const baseEvent = {
    slug: 'abc123',
    name: 'BBQ Caro',
    event_date: '2026-07-12',
    total_cents: 48000,
    share_cents: 4000,
    recipient_name: 'Caro',
    recipient_handle: '999888777',
    accepted_methods: ['yape', 'plin'],
    pay_deadline: '2026-07-30',
    status: 'active',
};

beforeEach(() => {
    mocks.pageProps = { flash: {}, auth: { user: null } };
});

describe('Public/Event', () => {
    it('shows the share, recipient and accepted methods', () => {
        const w = mount(Event, { props: { event: baseEvent, participant: null } });

        expect(w.text()).toContain('S/ 40.00');                 // 4000 cents
        expect(w.text()).toContain('Caro');
        expect(w.text()).toContain('(999888777)');
        expect(w.text()).toContain('Yape · Plin');
    });

    it('shows the upload form for an active event', () => {
        const w = mount(Event, { props: { event: baseEvent, participant: null } });

        expect(w.find('form').exists()).toBe(true);
        expect(w.text()).toContain('Enviar voucher');
    });

    it('hides the form and shows a notice when the event is closed', () => {
        const w = mount(Event, { props: { event: { ...baseEvent, status: 'closed' }, participant: null } });

        expect(w.find('form').exists()).toBe(false);
        expect(w.text()).toContain('Este evento está cerrado');
    });

    it('renders the participant status badge', () => {
        const w = mount(Event, {
            props: { event: baseEvent, participant: { name: 'José', badge: 'confirmed' } },
        });

        expect(w.text()).toContain('Confirmado');
    });

    it('shows the success confirmation after an upload', () => {
        mocks.pageProps = { flash: { uploaded: true }, auth: { user: null } };

        const w = mount(Event, {
            props: { event: baseEvent, participant: { name: 'José', badge: 'pending' } },
        });

        expect(w.text()).toContain('¡Listo!');
        expect(w.text()).toContain('José');
    });
});
