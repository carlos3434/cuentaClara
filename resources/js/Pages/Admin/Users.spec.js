import { mount } from '@vue/test-utils';

const h = vi.hoisted(() => ({ post: vi.fn(), formPost: vi.fn(), reset: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: h.post },
    useForm: (data) => ({ ...data, errors: {}, processing: false, post: h.formPost, reset: h.reset }),
}));

import Users from './Users.vue';

function makeProps(overrides = {}) {
    return {
        users: [
            { id: 7, name: 'Caro', email: 'caro@x.test', is_active: true, events_count: 4 },
        ],
        ...overrides,
    };
}

beforeEach(() => {
    h.post.mockClear();
    h.formPost.mockClear();
});

describe('Admin/Users', () => {
    it('lists organizers with their event counts and active state', () => {
        const w = mount(Users, { props: makeProps() });

        expect(w.text()).toContain('Caro');
        expect(w.text()).toContain('caro@x.test');
        expect(w.text()).toContain('4 evento(s)');
        expect(w.text()).toContain('Activo');
        expect(w.text()).toContain('Desactivar');
    });

    it('creates an organizer through the form', async () => {
        const w = mount(Users, { props: makeProps() });

        await w.find('form').trigger('submit.prevent');

        expect(h.formPost).toHaveBeenCalledWith('/admin/users', expect.anything());
    });

    it('toggles an organizer active state', async () => {
        const w = mount(Users, { props: makeProps() });

        await w.findAll('button').find((b) => b.text() === 'Desactivar').trigger('click');

        expect(h.post).toHaveBeenCalled();
        expect(h.post.mock.calls[0][0]).toBe('/admin/users/7/toggle');
    });

    it('shows the activate label for an inactive organizer', () => {
        const w = mount(Users, { props: makeProps({ users: [{ id: 9, name: 'Inactivo', email: 'i@x.test', is_active: false, events_count: 0 }] }) });

        expect(w.text()).toContain('Inactivo');
        expect(w.text()).toContain('Activar');
    });
});
