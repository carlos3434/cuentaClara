import { mount, flushPromises } from '@vue/test-utils';

const m = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: () => {} },
    usePage: () => ({ props: { auth: { user: { name: 'Caro' } } } }),
}));

vi.mock('axios', () => ({ default: { get: m.get } }));

import Index from './Index.vue';

const event = (name = 'BBQ Caro', slug = 'abc123') => ({
    slug,
    name,
    event_date: '2026-07-12',
    pay_deadline: '2026-07-30',
    total_cents: 48000,
    share_cents: 4000,
    headcount: 12,
    status: 'active',
    public_url: `http://localhost/e/${slug}`,
    share_url: `http://localhost/events/${slug}/created`,
    review_url: `http://localhost/events/${slug}/review`,
});

beforeEach(() => m.get.mockReset());

describe('Events/Index', () => {
    it('shows an empty state when there are no events', () => {
        const w = mount(Index, { props: { events: { data: [], next_page: null, total: 0 } } });

        expect(w.text()).toContain('Aún no tienes eventos');
    });

    it('renders an event card with totals, status and the review link', () => {
        const w = mount(Index, { props: { events: { data: [event()], next_page: null, total: 1 } } });

        expect(w.text()).toContain('BBQ Caro');
        expect(w.text()).toContain('Activo');
        expect(w.text()).toContain('S/ 480.00');
        expect(w.text()).toContain('S/ 40.00');
        expect(w.text()).toContain('Revisar pagos');
        expect(w.find('a').attributes('href')).toContain('/review');
        expect(w.text()).toContain('Mostrando 1 de 1');
    });

    it('hides "Ver más" when there are no more pages', () => {
        const w = mount(Index, { props: { events: { data: [event()], next_page: null, total: 1 } } });

        expect(w.text()).not.toContain('Ver más');
    });

    it('loads and appends older events on "Ver más"', async () => {
        m.get.mockResolvedValue({ data: { data: [event('Antiguo', 'old1')], next_page: null, total: 11 } });

        const w = mount(Index, { props: { events: { data: [event('Reciente', 'new1')], next_page: 2, total: 11 } } });
        expect(w.text()).toContain('Ver más eventos');

        await w.findAll('button').find((b) => b.text().includes('Ver más')).trigger('click');
        await flushPromises();

        expect(m.get).toHaveBeenCalledWith('/events/more', { params: { page: 2 } });
        expect(w.text()).toContain('Antiguo');          // appended
        expect(w.text()).toContain('Mostrando 2 de 11');
        expect(w.text()).not.toContain('Ver más eventos'); // no further pages
    });
});
