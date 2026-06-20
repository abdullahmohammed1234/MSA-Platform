<script setup lang="ts">
import { computed } from 'vue';
import { Motion } from '@motionone/vue';
import type { CardProps } from './types';
import { cardHover } from '@/design-system/animations/hover';

const props = withDefaults(defineProps<CardProps>(), {
  variant: 'default',
  hoverable: false
});

const cardClasses = computed(() => {
  const base = 'rounded-2xl overflow-hidden transition-all duration-300';
  
  const variants = {
    default: 'bg-white border border-neutral-ivory shadow-soft',
    glass: 'bg-white/70 backdrop-blur-md border border-white/20 shadow-premium-md',
    premium: 'bg-white border-2 border-neutral-ivory shadow-brand',
    flat: 'bg-neutral-ivory/40 border border-neutral-ivory',
    feature: 'bg-white border border-neutral-ivory shadow-soft group',
    interactive: 'bg-white border border-neutral-ivory shadow-soft cursor-pointer hover:border-primary/50'
  };

  const interactiveClasses = props.variant === 'interactive' || props.hoverable
    ? 'hover:shadow-premium-md'
    : '';

  return `${base} ${variants[props.variant]} ${interactiveClasses}`;
});
</script>

<template>
  <Motion
    :hover="hoverable || variant === 'interactive' ? cardHover.hover : {}"
    :transition="cardHover.transition"
    as="div"
    :class="cardClasses"
  >
    <!-- Card Header -->
    <div v-if="$slots.header" class="px-6 py-4 border-b border-neutral-ivory/40">
      <slot name="header"></slot>
    </div>

    <!-- Card Body/Content -->
    <div class="p-6">
      <div v-if="variant === 'feature'" class="flex items-start gap-4">
        <!-- Feature icon container -->
        <div class="flex-shrink-0 h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center text-primary group-hover:scale-110 transition-transform duration-300">
          <slot name="feature-icon"></slot>
        </div>
        <div class="flex-1 min-w-0">
          <slot></slot>
        </div>
      </div>
      <slot v-else></slot>
    </div>

    <!-- Card Footer -->
    <div v-if="$slots.footer" class="px-6 py-4 border-t border-neutral-ivory/40 bg-neutral-background/10">
      <slot name="footer"></slot>
    </div>
  </Motion>
</template>
