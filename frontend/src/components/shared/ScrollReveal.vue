<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Motion } from '@motionone/vue';

interface Props {
  width?: 'fit-content' | '100%';
  delay?: number;
  direction?: 'up' | 'down' | 'left' | 'right';
  distance?: number;
  duration?: number;
}

const props = withDefaults(defineProps<Props>(), {
  width: 'fit-content',
  delay: 0,
  direction: 'up',
  distance: 50,
  duration: 0.8
});

const containerRef = ref<HTMLElement | null>(null);
const isVisible = ref(false);
let observer: IntersectionObserver | null = null;

onMounted(() => {
  observer = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting) {
      isVisible.value = true;
      if (containerRef.value && observer) {
        observer.unobserve(containerRef.value); // Only reveal once
      }
    }
  }, {
    threshold: 0.1,
    rootMargin: '-100px 0px'
  });

  if (containerRef.value) {
    observer.observe(containerRef.value);
  }
});

onUnmounted(() => {
  if (observer && containerRef.value) {
    observer.unobserve(containerRef.value);
  }
});

const initialStyle = computed(() => {
  const distance = props.distance;
  const map = {
    up: { y: distance, x: 0 },
    down: { y: -distance, x: 0 },
    left: { y: 0, x: distance },
    right: { y: 0, x: -distance }
  };
  return {
    opacity: 0,
    ...map[props.direction]
  };
});

const animateStyle = computed(() => {
  if (isVisible.value) {
    return { opacity: 1, y: 0, x: 0 };
  }
  return initialStyle.value;
});
</script>

<template>
  <div 
    ref="containerRef" 
    :style="{ position: 'relative', width: width, overflow: 'visible' }"
  >
    <Motion
      :initial="initialStyle"
      :animate="animateStyle"
      :transition="{ 
        duration: duration, 
        delay: delay, 
        easing: [0.22, 1, 0.36, 1] 
      }"
    >
      <slot></slot>
    </Motion>
  </div>
</template>
