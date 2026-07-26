import { Fragment, defineComponent, h } from 'vue';

export const Motion = defineComponent({
  name: 'Motion',
  props: ['as'],
  setup(props, { slots }) {
    return () => h(props.as || 'div', {}, slots.default?.());
  }
});

/**
 * Presence only orchestrates enter/exit animations, so rendering its children
 * directly is the correct behaviour under test.
 */
export const Presence = defineComponent({
  name: 'Presence',
  setup(_props, { slots }) {
    return () => h(Fragment, slots.default?.());
  }
});
