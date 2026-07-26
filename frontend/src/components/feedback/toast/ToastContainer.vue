<script setup lang="ts">
import { TransitionGroup } from 'vue';
import { useToastStore } from './toastStore';
import Toast from './Toast.vue';
import { animate } from 'motion';
import { slideInRight } from '@/design-system/animations/slide';

const store = useToastStore();

const onEnter = (el: Element, done: () => void) => {
  animate(
    el as any,
    {
      opacity: [slideInRight.initial.opacity, slideInRight.animate.opacity],
      x: [slideInRight.initial.x, slideInRight.animate.x],
    } as any,
    {
      duration: slideInRight.transition.duration,
      easing: slideInRight.transition.easing as any,
    } as any
  ).then(done);
};

const onLeave = (el: Element, done: () => void) => {
  animate(
    el as any,
    {
      opacity: slideInRight.exit.opacity,
      x: slideInRight.exit.x,
    } as any,
    {
      duration: slideInRight.transition.duration,
      easing: slideInRight.transition.easing as any,
    } as any
  ).then(done);
};
</script>

<template>
  <div class="fixed bottom-5 right-5 z-[100] flex flex-col gap-3 w-full max-w-sm pointer-events-none">
    <TransitionGroup
      :css="false"
      @enter="onEnter"
      @leave="onLeave"
    >
      <div
        v-for="toast in store.toasts"
        :key="toast.id"
        class="pointer-events-auto"
      >
        <Toast :toast="toast" />
      </div>
    </TransitionGroup>
  </div>
</template>
