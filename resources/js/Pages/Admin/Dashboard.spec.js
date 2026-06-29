import { mount } from '@vue/test-utils';

const h = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: h.post },
}));

import Dashboard from './Dashboard.vue';

function makeProps(overrides = {}) {
    return {
        review_mode: 'manual',
        totals: { events: 1, organizers: 2, shown: 1 },
        events: [
            { id: 1, name: 'BBQ Caro', organizer: 'Caro', status: 'active', headcount: 12, paid_count: 3, collected_cents: 12000, total_cents: 48000 },
        ],
        ...overrides,
    };
}

beforeEach(() => h.post.mockClear());

describe('Admin/Dashboard', () => {
    it('renders the per-event payments', () => {
        const w = mount(Dashboard, { props: makeProps() });

        expect(w.text()).toContain('BBQ Caro');
        expect(w.text()).toContain('3 de 12 pagaron');
        expect(w.text()).toContain('S/ 120.00');
    });

    it('switches the review mode to auto', async () => {
        const w = mount(Dashboard, { props: makeProps() });

        await w.findAll('button').find((b) => b.text() === 'Automática').trigger('click');

        expect(h.post).toHaveBeenCalledWith('/admin/settings', { review_mode: 'auto' }, expect.anything());
    });

    it('does not re-post when the current mode is clicked', async () => {
        const w = mount(Dashboard, { props: makeProps({ review_mode: 'auto' }) });

        await w.findAll('button').find((b) => b.text() === 'Automática').trigger('click');

        expect(h.post).not.toHaveBeenCalled();
    });
});
