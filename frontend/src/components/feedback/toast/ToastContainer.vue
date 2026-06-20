<script setup lang="ts">
import { useToastStore } from './toastStore';
import { Presence, Motion } from '@motionone/vue';
import Toast from './Toast.vue';
import { slideInRight } from '@/design-system/animations/slide';

const store = useToastStore();
</script>

<template>
  <div class="fixed bottom-5 right-5 z-[100] flex flex-col gap-3 w-full max-w-sm pointer-events-none">
    <Presence>
      <Motion
        v-for="toast in store.toasts"
        :key="toast.id"
        :initial="slideInRight.initial"
        :animate="slideInRight.animate"
        :exit="slideInRight.exit"
        :transition="slideInRight.transition"
        class="pointer-events-auto"
      >
        <Toast :toast="toast" />
      </Motion>
    </Presence>
  </div>
</template>
