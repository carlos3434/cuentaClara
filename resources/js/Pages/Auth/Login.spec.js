import { mount } from '@vue/test-utils';

const m = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    useForm: (data) => ({ ...data, errors: {}, processing: false, post: m.post, reset: () => {} }),
}));

import Login from './Login.vue';

beforeEach(() => m.post.mockClear());

describe('Auth/Login', () => {
    it('renders the login form with a link to register', () => {
        const w = mount(Login);

        expect(w.find('#email').exists()).toBe(true);
        expect(w.find('#password').exists()).toBe(true);
        expect(w.text()).toContain('Ingresar');
        expect(w.findAll('a').some((a) => a.attributes('href') === '/register')).toBe(true);
    });

    it('submits to /login', async () => {
        const w = mount(Login);

        await w.find('form').trigger('submit.prevent');

        expect(m.post.mock.calls[0][0]).toBe('/login');
    });
});
