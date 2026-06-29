import { mount } from '@vue/test-utils';
import Icon from './Icon.vue';

describe('Icon', () => {
    it('renders the chevron-down path', () => {
        const w = mount(Icon, { props: { name: 'chevron-down' } });
        expect(w.find('svg').exists()).toBe(true);
        expect(w.find('path').attributes('d')).toContain('19.5 8.25');
    });

    it('renders the WhatsApp brand glyph as a filled path', () => {
        const w = mount(Icon, { props: { name: 'whatsapp' } });
        expect(w.find('path').attributes('fill')).toBe('currentColor');
    });

    it('renders the camera icon as two paths', () => {
        const w = mount(Icon, { props: { name: 'camera' } });
        expect(w.findAll('path')).toHaveLength(2);
    });

    it('renders nothing for an unknown name', () => {
        const w = mount(Icon, { props: { name: 'nope' } });
        expect(w.findAll('path')).toHaveLength(0);
    });

    it('inherits size/color classes from the caller', () => {
        const w = mount(Icon, { props: { name: 'copy' }, attrs: { class: 'h-4 w-4' } });
        expect(w.classes()).toContain('h-4');
        expect(w.classes()).toContain('w-4');
    });
});
