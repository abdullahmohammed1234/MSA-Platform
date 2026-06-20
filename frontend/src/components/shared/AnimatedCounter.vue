<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

interface Props {
  value: number;
  duration?: number; // In seconds
  suffix?: string;
  prefix?: string;
}

const props = withDefaults(defineProps<Props>(), {
  duration: 1.5,
  suffix: '',
  prefix: ''
});

const counterRef = ref<HTMLElement | null>(null);
const displayValue = ref(props.prefix + '0' + props.suffix);
let observer: IntersectionObserver | null = null;

const animateValue = (start: number, end: number, durationMs: number) => {
  let startTimestamp: number | null = null;
  const step = (timestamp: number) => {
    if (!startTimestamp) startTimestamp = timestamp;
    const progress = Math.min((timestamp - startTimestamp) / durationMs, 1);
    
    // Easing out cubic
    const easeProgress = 1 - Math.pow(1 - progress, 3);
    const currentValue = Math.round(easeProgress * (end - start) + start);
    
    displayValue.value = props.prefix + Intl.NumberFormat('en-US').format(currentValue) + props.suffix;
    
    if (progress < 1) {
      window.requestAnimationFrame(step);
    }
  };
  window.requestAnimationFrame(step);
};

onMounted(() => {
  observer = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting) {
      animateValue(0, props.value, props.duration * 1000);
      if (counterRef.value && observer) {
        observer.unobserve(counterRef.value);
      }
    }
  }, { threshold: 0.1, rootMargin: '-50px 0px' });

  if (counterRef.value) {
    observer.observe(counterRef.value);
  }
});

onUnmounted(() => {
  if (observer && counterRef.value) {
    observer.unobserve(counterRef.value);
  }
});
</script>

<template>
  <span ref="counterRef">{{ displayValue }}</span>
</template>
