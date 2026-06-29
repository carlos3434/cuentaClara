import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    useForm: (data) => ({ ...data, errors: {}, processing: false, post: () => {}, reset: () => {} }),
}));

import Login from './Login.vue';

describe('Auth/Login', () => {
    it('renders the login form with a link to register', () => {
        const w = mount(Login);

        expect(w.find('#email').exists()).toBe(true);
        expect(w.find('#password').exists()).toBe(true);
        expect(w.text()).toContain('Ingresar');
        expect(w.findAll('a').some((a) => a.attributes('href') === '/register')).toBe(true);
    });
});
