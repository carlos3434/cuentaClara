import { mount, flushPromises } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import Created from './Created.vue';

const props = {
    event: { name: 'BBQ Caro', share_cents: 4000, total_cents: 48000, headcount: 12 },
    public_url: 'http://localhost/e/abc123',
};

describe('Events/Created', () => {
    it('shows the event summary and the shareable link', () => {
        const w = mount(Created, { props });

        expect(w.text()).toContain('BBQ Caro');
        expect(w.text()).toContain('S/ 40.00');                       // share
        expect(w.text()).toContain('Total S/ 480.00 · 12 personas');
        expect(w.find('input').element.value).toBe('http://localhost/e/abc123');
    });

    it('builds a WhatsApp share link with the share amount and public url', () => {
        const w = mount(Created, { props });

        const wa = w.findAll('a').find((a) => a.attributes('href')?.startsWith('https://wa.me/'));
        expect(wa).toBeTruthy();
        const decoded = decodeURIComponent(wa.attributes('href'));
        expect(decoded).toContain('S/ 40.00');
        expect(decoded).toContain('http://localhost/e/abc123');
    });

    it('copies the link to the clipboard', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });

        const w = mount(Created, { props });
        await w.findAll('button').find((b) => b.text().includes('Copiar')).trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith('http://localhost/e/abc123');
        expect(w.text()).toContain('Copiado');
    });
});
