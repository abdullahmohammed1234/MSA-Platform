<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, useAttrs } from 'vue';
import { Motion } from '@motionone/vue';

defineOptions({ inheritAttrs: false });

interface Props {
  variant?: 'default' | 'glass' | 'premium' | 'flat';
  padding?: 'none' | 'sm' | 'md' | 'lg';
  hoverable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  padding: 'md',
  hoverable: true
});

const attrs = useAttrs();

const cardRef = ref<HTMLElement | null>(null);
const isVisible = ref(false);
let observer: IntersectionObserver | null = null;

onMounted(() => {
  observer = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting) {
      isVisible.value = true;
      if (cardRef.value && observer) {
        observer.unobserve(cardRef.value);
      }
    }
  }, { threshold: 0.1, rootMargin: '-80px 0px' });

  if (cardRef.value) {
    observer.observe(cardRef.value);
  }
});

onUnmounted(() => {
  if (observer && cardRef.value) {
    observer.unobserve(cardRef.value);
  }
});

const cardClasses = computed(() => {
  const base = 'h-full w-full rounded-[2rem] overflow-hidden transition-all duration-500 group flex flex-col';

  const variants = {
    default: 'bg-white border border-neutral-ivory/60 shadow-soft hover:shadow-premium',
    glass: 'bg-white border border-neutral-ivory/60 shadow-soft hover:shadow-premium',
    premium: 'bg-white border border-neutral-ivory rounded-3xl shadow-soft hover:shadow-premium',
    flat: 'bg-neutral-ivory/35 border border-neutral-ivory/60',
  };

  const paddings = {
    none: 'p-0',
    sm: 'p-4',
    md: 'p-6 sm:p-8',
    lg: 'p-8 sm:p-12',
  };

  return `${base} ${variants[props.variant]} ${paddings[props.padding]}`;
});
</script>

<template>
  <div ref="cardRef" class="h-full">
    <Motion
      :initial="{ opacity: 0, y: 15 }"
      :animate="isVisible ? { opacity: 1, y: 0 } : { opacity: 0, y: 15 }"
      :transition="{ duration: 0.5 }"
      as="div"
      :class="[cardClasses, hoverable && 'hover:shadow-premium', attrs.class]"
    >
      <slot></slot>
    </Motion>
  </div>
</template>
