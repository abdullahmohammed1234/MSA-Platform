import { defineComponent, h } from 'vue';

export const Motion = defineComponent({
  name: 'Motion',
  props: ['as'],
  setup(props, { slots }) {
    return () => h(props.as || 'div', {}, slots.default?.());
  }
});
