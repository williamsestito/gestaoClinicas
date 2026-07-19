import { Bell } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { h } from 'vue';
import IconDropdownMenu from './IconDropdownMenu.vue';
import { TooltipProvider } from './ui/tooltip';

function mountIconDropdownMenu(
    props: InstanceType<typeof IconDropdownMenu>['$props'],
) {
    return mount(TooltipProvider, {
        slots: {
            default: () => h(IconDropdownMenu, props),
        },
    });
}

describe('IconDropdownMenu', () => {
    it('exposes an accessible label on the trigger button', () => {
        const wrapper = mountIconDropdownMenu({
            icon: Bell,
            label: 'Notificações',
            emptyTitle: 'Nenhuma nova notificação.',
        });

        expect(wrapper.find('button[aria-label="Notificações"]').exists()).toBe(
            true,
        );
    });

    it('does not render an unread indicator when count is zero', () => {
        const wrapper = mountIconDropdownMenu({
            icon: Bell,
            label: 'Notificações',
            emptyTitle: 'Nenhuma nova notificação.',
        });

        expect(wrapper.find('[aria-hidden="true"]').exists()).toBe(false);
    });

    it('renders an unread indicator when count is greater than zero', () => {
        const wrapper = mountIconDropdownMenu({
            icon: Bell,
            label: 'Notificações',
            emptyTitle: 'Nenhuma nova notificação.',
            count: 3,
        });

        expect(wrapper.find('[aria-hidden="true"]').exists()).toBe(true);
    });
});
