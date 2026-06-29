import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: () => {} },
    usePage: () => ({ props: { auth: { user: { name: 'Caro' } } } }),
}));

import Index from './Index.vue';

const event = {
    slug: 'abc123',
    name: 'BBQ Caro',
    event_date: '2026-07-12',
    pay_deadline: '2026-07-30',
    total_cents: 48000,
    share_cents: 4000,
    headcount: 12,
    status: 'active',
    public_url: 'http://localhost/e/abc123',
    share_url: 'http://localhost/events/abc123/created',
    review_url: 'http://localhost/events/abc123/review',
};

describe('Events/Index', () => {
    it('shows an empty state when there are no events', () => {
        const w = mount(Index, { props: { events: [] } });

        expect(w.text()).toContain('Aún no tienes eventos');
    });

    it('renders an event card with totals, status and the review link', () => {
        const w = mount(Index, { props: { events: [event] } });

        expect(w.text()).toContain('BBQ Caro');
        expect(w.text()).toContain('Activo');
        expect(w.text()).toContain('S/ 480.00');   // total
        expect(w.text()).toContain('S/ 40.00');     // per-person share
        expect(w.text()).toContain('Revisar pagos');
        expect(w.find('a').attributes('href')).toContain('/review');
    });
});
