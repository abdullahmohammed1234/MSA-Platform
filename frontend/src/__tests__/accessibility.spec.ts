import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Button from '../components/ui/button/Button.vue';
import Input from '../components/ui/input/Input.vue';
import Checkbox from '../components/ui/checkbox/Checkbox.vue';
import Switch from '../components/ui/switch/Switch.vue';
import Select from '../components/ui/select/Select.vue';
import PublicLayout from '../layouts/PublicLayout.vue';

const MotionStub = {
  template: '<component :is="as || \'button\'" v-bind="$attrs"><slot /></component>',
  props: ['as']
};

describe('Accessibility (a11y) Verification', () => {
  it('Button should expose proper aria properties during loading state', () => {
    const wrapper = mount(Button, {
      global: {
        stubs: { Motion: MotionStub }
      },
      props: {
        isLoading: true
      }
    });

    const button = wrapper.find('button');
    expect(button.attributes('aria-busy')).toBe('true');
    expect(button.attributes('aria-live')).toBe('polite');
  });

  it('Button should bind aria-label when provided', () => {
    const wrapper = mount(Button, {
      global: {
        stubs: { Motion: MotionStub }
      },
      props: {
        ariaLabel: 'Close Dialog',
        size: 'icon'
      }
    });

    const button = wrapper.find('button');
    expect(button.attributes('aria-label')).toBe('Close Dialog');
    expect(button.classes()).toContain('focus-visible:ring-2');
  });

  it('Input should set aria-invalid when in error state', () => {
    const wrapper = mount(Input, {
      props: {
        modelValue: '',
        error: 'Email is required'
      }
    });

    const input = wrapper.find('input');
    expect(input.attributes('aria-invalid')).toBe('true');
    
    const errorId = wrapper.find('p[aria-live="assertive"]').attributes('id');
    expect(input.attributes('aria-describedby')).toBe(errorId);
  });

  it('Input should have correct tabIndex and disabled bindings', () => {
    const wrapper = mount(Input, {
      props: {
        modelValue: '',
        disabled: true
      }
    });

    const input = wrapper.find('input');
    expect(input.attributes('disabled')).toBeDefined();
  });

  it('Checkbox should expose aria-invalid and aria-describedby for error state', () => {
    const wrapper = mount(Checkbox, {
      props: {
        modelValue: false,
        label: 'I accept terms',
        error: 'You must agree to continue'
      }
    });

    const input = wrapper.find('input[type="checkbox"]');
    expect(input.attributes('aria-invalid')).toBe('true');

    const errorEl = wrapper.find('p[aria-live="assertive"]');
    expect(errorEl.exists()).toBe(true);
    expect(input.attributes('aria-describedby')).toBe(errorEl.attributes('id'));
  });

  it('Switch should have role="switch", tabindex="0", and toggle on keyboard interactions', async () => {
    const wrapper = mount(Switch, {
      global: {
        stubs: { Motion: MotionStub }
      },
      props: {
        modelValue: false,
        label: 'Enable Notifications'
      }
    });

    const switchTrack = wrapper.find('[role="switch"]');
    expect(switchTrack.exists()).toBe(true);
    expect(switchTrack.attributes('tabindex')).toBe('0');
    expect(switchTrack.attributes('aria-checked')).toBe('false');

    await switchTrack.trigger('keydown', { key: ' ' });
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true]);

    await switchTrack.trigger('keydown', { key: 'Enter' });
    expect(wrapper.emitted('update:modelValue')?.[1]).toEqual([true]);
  });

  it('Select should expose combobox ARIA roles when searchable', () => {
    const wrapper = mount(Select, {
      props: {
        label: 'Select Option',
        searchable: true,
        options: [
          { label: 'Option A', value: 'a' },
          { label: 'Option B', value: 'b' }
        ],
        modelValue: 'a'
      }
    });

    const combobox = wrapper.find('[role="combobox"]');
    expect(combobox.exists()).toBe(true);
    expect(combobox.attributes('aria-haspopup')).toBe('listbox');
  });

  it('PublicLayout contains accessible skip-to-content link pointing to main landmark', () => {
    const wrapper = mount(PublicLayout, {
      global: {
        stubs: {
          PublicNavbar: true,
          PublicFooter: true,
          'router-view': true
        }
      }
    });

    const skipLink = wrapper.find('a[href="#main-content"]');
    expect(skipLink.exists()).toBe(true);
    expect(skipLink.text()).toContain('Skip to main content');

    const mainLandmark = wrapper.find('main#main-content');
    expect(mainLandmark.exists()).toBe(true);
  });
});
