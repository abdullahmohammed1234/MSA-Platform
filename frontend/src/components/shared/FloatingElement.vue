<script setup lang="ts">
import { Motion } from '@motionone/vue';

interface Props {
  delay?: number;
}

withDefaults(defineProps<Props>(), {
  delay: 0
});
</script>

<template>
  <Motion
    :animate="{
      y: [0, -15, 0],
      rotate: [0, 2, -2, 0],
    }"
    :transition="{
      duration: 5,
      repeat: Infinity,
      easing: 'ease-in-out',
      delay: delay,
    }"
  >
    <slot></slot>
  </Motion>
</template>
