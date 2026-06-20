import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Button from '../components/ui/button/Button.vue';
import Input from '../components/ui/input/Input.vue';

// Stub Motion component from @motionone/vue
const MotionStub = {
  template: '<component :is="as || \'div\'" v-bind="$attrs"><slot /></component>',
  props: ['as']
};

describe('Shared UI Components Unit Tests', () => {
  describe('Button.vue', () => {
    it('renders slot content correctly', () => {
      const wrapper = mount(Button, {
        global: {
          stubs: { Motion: MotionStub }
        },
        slots: {
          default: 'Click Me'
        }
      });
      expect(wrapper.text()).toContain('Click Me');
    });

    it('emits click event when clicked', async () => {
      const wrapper = mount(Button, {
        global: {
          stubs: { Motion: MotionStub }
        }
      });
      await wrapper.find('button').trigger('click');
      expect(wrapper.emitted()).toHaveProperty('click');
    });

    it('does not emit click event when disabled', async () => {
      const wrapper = mount(Button, {
        global: {
          stubs: { Motion: MotionStub }
        },
        props: {
          disabled: true
        }
      });
      await wrapper.find('button').trigger('click');
      expect(wrapper.emitted()).not.toHaveProperty('click');
    });

    it('renders loading state indicator and sets aria-busy', () => {
      const wrapper = mount(Button, {
        global: {
          stubs: { Motion: MotionStub }
        },
        props: {
          isLoading: true
        }
      });
      expect(wrapper.find('svg').exists()).toBe(true);
      expect(wrapper.find('button').attributes('aria-busy')).toBe('true');
    });
  });

  describe('Input.vue', () => {
    it('renders input with value and emits input updates', async () => {
      const wrapper = mount(Input, {
        props: {
          modelValue: 'Initial Value',
          placeholder: 'Enter text'
        }
      });
      const input = wrapper.find('input');
      expect(input.element.value).toBe('Initial Value');
      expect(input.attributes('placeholder')).toBe('Enter text');

      await input.setValue('New Value');
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['New Value']);
    });

    it('renders error state and helper text', () => {
      const wrapper = mount(Input, {
        props: {
          modelValue: '',
          error: 'Invalid input'
        }
      });
      expect(wrapper.text()).toContain('Invalid input');
      const input = wrapper.find('input');
      expect(input.attributes('aria-invalid')).toBe('true');
      
      const errorId = wrapper.find('p[aria-live="assertive"]').attributes('id');
      expect(input.attributes('aria-describedby')).toBe(errorId);
    });
  });
});
