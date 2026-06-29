import { mount, flushPromises } from '@vue/test-utils';

const mocks = vi.hoisted(() => ({ pageProps: { flash: {}, auth: { user: null } }, post: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    usePage: () => ({ props: mocks.pageProps }),
    useForm: (data) => ({ ...data, errors: {}, processing: false, post: mocks.post, reset: () => {} }),
}));

// Avoid loading the real (heavy) OCR engine during the dynamic import.
vi.mock('tesseract.js', () => ({ recognize: vi.fn().mockResolvedValue({ data: { text: '' } }) }));

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
    mocks.post.mockClear();
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:preview');
    globalThis.createImageBitmap = vi.fn().mockResolvedValue({ width: 100, height: 100 });
});

describe('Public/Event', () => {
    it('shows the share, recipient and accepted methods', () => {
        const w = mount(Event, { props: { event: baseEvent, participant: null } });

        expect(w.text()).toContain('S/ 40.00');
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

    // --- interactions ---

    it('previews and analyzes a selected image', async () => {
        const w = mount(Event, { props: { event: baseEvent, participant: null } });

        const input = w.find('#image');
        Object.defineProperty(input.element, 'files', {
            value: [new File(['x'], 'voucher.jpg', { type: 'image/jpeg' })],
            configurable: true,
        });
        await input.trigger('change');
        await flushPromises();

        expect(globalThis.URL.createObjectURL).toHaveBeenCalled();   // preview built
        expect(globalThis.createImageBitmap).toHaveBeenCalled();      // OCR check ran
        expect(w.find('img').attributes('src')).toBe('blob:preview');
    });

    it('submits the voucher', async () => {
        const w = mount(Event, { props: { event: baseEvent, participant: { name: 'José', badge: 'pending' } } });

        await w.find('form').trigger('submit.prevent');

        expect(mocks.post.mock.calls[0][0]).toBe('/e/abc123/receipts');
    });
});
