import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { PublicFaq } from '@/types/site';
import LandingFaqSection from './LandingFaqSection.vue';

function makeFaqs(): PublicFaq[] {
    return [
        {
            id: 1,
            question: 'Como agendar?',
            answer: 'Pelo formulário.',
            category: null,
        },
        {
            id: 2,
            question: 'Quais os horários?',
            answer: 'Seg a sex, 8h-18h.',
            category: null,
        },
    ];
}

describe('LandingFaqSection', () => {
    it('does not render the section when there are no faqs', () => {
        const wrapper = mount(LandingFaqSection, { props: { faqs: [] } });

        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('renders every question as a real button, initially collapsed', () => {
        const wrapper = mount(LandingFaqSection, {
            props: { faqs: makeFaqs() },
        });

        const buttons = wrapper.findAll('button');
        expect(buttons).toHaveLength(2);
        expect(buttons[0].text()).toContain('Como agendar?');
        expect(buttons[0].attributes('aria-expanded')).toBe('false');
    });

    it('links each trigger to its panel via aria-controls/id, and expands on click', async () => {
        const wrapper = mount(LandingFaqSection, {
            props: { faqs: makeFaqs() },
        });

        const trigger = wrapper.findAll('button')[0];
        await trigger.trigger('click');

        // O id do painel só é atribuído pela reka-ui quando o Collapsible
        // renderiza pela primeira vez com conteúdo presente — por isso
        // conferimos o vínculo aria-controls/id depois do primeiro clique.
        const controlsId = trigger.attributes('aria-controls');
        expect(controlsId).toBeTruthy();
        expect(trigger.attributes('aria-expanded')).toBe('true');
        expect(wrapper.find(`#${controlsId}`).exists()).toBe(true);
        expect(wrapper.find(`#${controlsId}`).text()).toContain(
            'Pelo formulário.',
        );
    });

    it('collapses again on a second click', async () => {
        const wrapper = mount(LandingFaqSection, {
            props: { faqs: makeFaqs() },
        });
        const trigger = wrapper.findAll('button')[0];

        await trigger.trigger('click');
        expect(trigger.attributes('aria-expanded')).toBe('true');

        await trigger.trigger('click');
        expect(trigger.attributes('aria-expanded')).toBe('false');
    });

    it('keeps each question independently expandable', async () => {
        const wrapper = mount(LandingFaqSection, {
            props: { faqs: makeFaqs() },
        });
        const [first, second] = wrapper.findAll('button');

        await first.trigger('click');

        expect(first.attributes('aria-expanded')).toBe('true');
        expect(second.attributes('aria-expanded')).toBe('false');
    });

    it('preserves line breaks in long answers', async () => {
        const wrapper = mount(LandingFaqSection, {
            props: {
                faqs: [
                    {
                        id: 1,
                        question: 'Pergunta?',
                        answer: 'Linha um.\nLinha dois.',
                        category: null,
                    },
                ],
            },
        });

        await wrapper.find('button').trigger('click');

        const content = wrapper.find('[class*="whitespace-pre-line"]');
        expect(content.exists()).toBe(true);
        expect(content.text().replace(/\s+/g, ' ')).toContain('Linha um.');
    });

    it('never renders a malicious question/answer as executable markup', async () => {
        const scriptPayload = '<script>alert(1)</script>';
        const imgPayload = '<img src=x onerror=alert(1)>';

        const wrapper = mount(LandingFaqSection, {
            props: {
                faqs: [
                    {
                        id: 1,
                        question: scriptPayload,
                        answer: imgPayload,
                        category: null,
                    },
                ],
            },
        });

        expect(wrapper.findAll('button')[0].text()).toContain(scriptPayload);
        expect(wrapper.find('script').exists()).toBe(false);

        await wrapper.find('button').trigger('click');

        expect(wrapper.text()).toContain(imgPayload);
        expect(wrapper.find('img').exists()).toBe(false);
    });
});
