import { mount, flushPromises } from '@vue/test-utils';

const h = vi.hoisted(() => ({ post: vi.fn(), del: vi.fn(), formPost: vi.fn(), get: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: h.post, delete: h.del },
    useForm: (data) => ({ ...data, errors: {}, processing: false, post: h.formPost, reset: vi.fn() }),
}));

vi.mock('axios', () => ({ default: { get: h.get } }));

import Review from './Review.vue';

function participantsPage(data, next_page = null, total = data.length) {
    return { data, next_page, total };
}

function makeProps(overrides = {}) {
    return {
        event: {
            slug: 'abc', name: 'BBQ Caro', total_cents: 48000, share_cents: 4000, headcount: 12,
            status: 'active', recipient_name: 'Caro', recipient_handle: '999',
            pay_deadline: '2026-07-30', public_url: 'http://x/e/abc',
        },
        summary: { collected_cents: 4000, pending_cents: 44000, paid_count: 1, headcount: 12, review_count: 1 },
        review: [{
            id: 5, participant: 'Lucía', status: 'needs_review', reason_code: 'amount_mismatch',
            amount_cents: 3000, date: '2026-06-24', method: 'yape', recipient: 'Caro',
            confidence: 0.72, explanation: '', image_url: 'http://x/img/5', created_at: '2026-06-24T00:00:00Z',
        }],
        participants: participantsPage([
            { id: 1, name: 'Ana', status: 'paid', receipt: { id: 9, image_url: 'http://x/img/9', amount_cents: 4000, date: '2026-06-24', method: 'yape', recipient: 'Caro', confidence: 0.95, status: 'validated', reason_code: null } },
            { id: 2, name: 'Beto', status: 'pending', receipt: null },
        ], null, 2),
        expenses: [{ id: 3, note: 'Cancha', image_url: 'http://x/exp/3', created_at: '2026-06-24T00:00:00Z' }],
        share_url: 'http://x/events/abc/created',
        ...overrides,
    };
}

beforeEach(() => {
    h.post.mockClear();
    h.del.mockClear();
    h.formPost.mockClear();
    h.get.mockReset();
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:preview');
});

describe('Organizer/Review', () => {
    it('shows collected/pending totals and progress', () => {
        const w = mount(Review, { props: makeProps() });

        expect(w.text()).toContain('S/ 40.00');           // collected
        expect(w.text()).toContain('de S/ 480.00');       // total
        expect(w.text()).toContain('1 de 12 pagaron');
        expect(w.text()).toContain('faltan S/ 440.00');
    });

    it('renders the needs-review card with the AI reading', () => {
        const w = mount(Review, { props: makeProps() });

        expect(w.text()).toContain('Lucía');
        expect(w.text()).toContain('Monto no coincide');   // reason label
        expect(w.text()).toContain('S/ 30.00');            // read amount
        expect(w.text()).toContain('72%');                 // confidence
        expect(w.findAll('img').some((i) => i.attributes('src') === 'http://x/img/5')).toBe(true);
    });

    it('builds a group WhatsApp reminder with the share and public url', () => {
        const w = mount(Review, { props: makeProps() });

        const wa = w.findAll('a').find((a) => a.attributes('href')?.startsWith('https://wa.me/'));
        const decoded = decodeURIComponent(wa.attributes('href'));
        expect(decoded).toContain('S/ 40.00');
        expect(decoded).toContain('http://x/e/abc');
    });

    it('shows participant badges and toggles a read-only voucher panel', async () => {
        const w = mount(Review, { props: makeProps() });

        expect(w.text()).toContain('Pagó');        // Ana
        expect(w.text()).toContain('Pendiente');   // Beto
        // Ana's voucher panel is collapsed initially.
        expect(w.findAll('img').some((i) => i.attributes('alt') === 'Voucher de Ana')).toBe(false);

        await w.findAll('button').find((b) => b.text().includes('Ver voucher')).trigger('click');
        // The panel reveals the voucher but no confirm button — that lives in "Por revisar".
        expect(w.findAll('img').some((i) => i.attributes('alt') === 'Voucher de Ana')).toBe(true);
    });

    it('shows "En revisión" for an upload awaiting confirmation', () => {
        const w = mount(Review, {
            props: makeProps({
                participants: participantsPage([{
                    id: 7, name: 'Nico', status: 'submitted',
                    receipt: { id: 8, image_url: 'http://x/img/8', amount_cents: null, date: null, method: null, recipient: null, confidence: null, status: 'submitted', reason_code: null },
                }], null, 1),
            }),
        });

        expect(w.text()).toContain('En revisión');
    });

    it('confirms a receipt from the review queue through the router', async () => {
        const w = mount(Review, { props: makeProps() });

        await w.findAll('button').find((b) => b.text().includes('Confirmar pago')).trigger('click');

        expect(h.post).toHaveBeenCalled();
        expect(h.post.mock.calls[0][0]).toContain('/receipts/5/approve');
    });

    it('shows close when active and reopen when closed', () => {
        expect(mount(Review, { props: makeProps() }).text()).toContain('Cerrar evento');
        expect(mount(Review, { props: makeProps({ event: { ...makeProps().event, status: 'closed' } }) }).text()).toContain('Reabrir evento');
    });

    // --- interactions ---

    it('rejects a receipt through the router', async () => {
        const w = mount(Review, { props: makeProps() });

        await w.findAll('button').find((b) => b.text().trim() === 'Rechazar').trigger('click');

        expect(h.post.mock.calls[0][0]).toContain('/receipts/5/reject');
    });

    it('marks a pending participant as cash', async () => {
        const w = mount(Review, { props: makeProps() });

        await w.findAll('button').find((b) => b.text() === 'Efectivo').trigger('click');

        expect(h.post.mock.calls[0][0]).toContain('/participants/2/cash');
    });

    it('closes the event', async () => {
        const w = mount(Review, { props: makeProps() });

        await w.findAll('button').find((b) => b.text().includes('Cerrar evento')).trigger('click');

        expect(h.post.mock.calls[0][0]).toContain('/close');
    });

    it('reopens a closed event', async () => {
        const w = mount(Review, { props: makeProps({ event: { ...makeProps().event, status: 'closed' } }) });

        await w.findAll('button').find((b) => b.text().includes('Reabrir evento')).trigger('click');

        expect(h.post.mock.calls[0][0]).toContain('/reopen');
    });

    it('deletes an expense receipt', async () => {
        const w = mount(Review, { props: makeProps() });

        await w.findAll('button').find((b) => b.text() === 'Eliminar').trigger('click');

        expect(h.del.mock.calls[0][0]).toContain('/expenses/3');
    });

    it('uploads an expense receipt', async () => {
        const w = mount(Review, { props: makeProps() });

        const input = w.find('#expense-image');
        Object.defineProperty(input.element, 'files', {
            value: [new File(['x'], 'gasto.jpg', { type: 'image/jpeg' })],
            configurable: true,
        });
        await input.trigger('change');
        await w.find('form').trigger('submit.prevent');

        expect(h.formPost).toHaveBeenCalled();
    });

    it('loads more participants on demand', async () => {
        h.get.mockResolvedValue({ data: participantsPage([{ id: 99, name: 'Zoila', status: 'pending', receipt: null }], null, 3) });

        const w = mount(Review, {
            props: makeProps({
                participants: participantsPage(
                    [{ id: 1, name: 'Ana', status: 'pending', receipt: null }],
                    2,
                    3,
                ),
            }),
        });
        expect(w.text()).toContain('Ver más participantes');

        await w.findAll('button').find((b) => b.text().includes('Ver más participantes')).trigger('click');
        await flushPromises();

        expect(h.get).toHaveBeenCalledWith('/events/abc/participants/more', { params: { page: 2 } });
        expect(w.text()).toContain('Zoila');
        expect(w.text()).not.toContain('Ver más participantes');
    });
});
