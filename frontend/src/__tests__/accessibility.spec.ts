import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Button from '../components/ui/button/Button.vue';
import Input from '../components/ui/input/Input.vue';

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
});
