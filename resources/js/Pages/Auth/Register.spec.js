import { mount } from '@vue/test-utils';

const m = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    useForm: (data) => ({ ...data, errors: {}, processing: false, post: m.post, reset: () => {} }),
}));

import Register from './Register.vue';

beforeEach(() => m.post.mockClear());

describe('Auth/Register', () => {
    it('renders the registration form with a link to login', () => {
        const w = mount(Register);

        expect(w.find('#name').exists()).toBe(true);
        expect(w.find('#email').exists()).toBe(true);
        expect(w.find('#password').exists()).toBe(true);
        expect(w.find('#password_confirmation').exists()).toBe(true);
        expect(w.text()).toContain('Crear cuenta');
        expect(w.findAll('a').some((a) => a.attributes('href') === '/login')).toBe(true);
    });

    it('submits to /register', async () => {
        const w = mount(Register);

        await w.find('form').trigger('submit.prevent');

        expect(m.post.mock.calls[0][0]).toBe('/register');
    });
});
