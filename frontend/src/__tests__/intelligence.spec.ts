import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import DateRangeSelector from '@/components/admin/intelligence/DateRangeSelector.vue';
import IntelligenceKpiCard from '@/components/admin/intelligence/IntelligenceKpiCard.vue';

describe('Phase 23 Intelligence Frontend Components', () => {
  it('renders DateRangeSelector and emits period change events', async () => {
    const wrapper = mount(DateRangeSelector, {
      props: { period: '30d' },
    });

    expect(wrapper.text()).toContain('30 Days');
    expect(wrapper.text()).toContain('7 Days');

    const buttons = wrapper.findAll('button');
    const btn7d = buttons.find(b => b.text().includes('7 Days'));
    expect(btn7d).toBeDefined();

    await btn7d?.trigger('click');
    expect(wrapper.emitted('change')).toBeTruthy();
    expect(wrapper.emitted('change')![0][0]).toEqual({ period: '7d' });
  });

  it('renders IntelligenceKpiCard with formatted value and percentage change', () => {
    const wrapper = mount(IntelligenceKpiCard, {
      props: {
        title: 'Total Sales Revenue',
        value: 1250.50,
        previousValue: 1000.00,
        pctChange: 25.05,
        prefix: '$',
      },
    });

    expect(wrapper.text()).toContain('Total Sales Revenue');
    expect(wrapper.text()).toContain('$1,250.5');
    expect(wrapper.text()).toContain('+25.05%');
  });
});
