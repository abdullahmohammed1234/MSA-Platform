<script setup lang="ts">
import { computed } from 'vue';
import { Motion } from '@motionone/vue';

interface Props {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'gold' | 'white';
  size?: 'sm' | 'md' | 'lg';
  isLoading?: boolean;
  type?: 'button' | 'submit' | 'reset';
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  isLoading: false,
  type: 'button',
  disabled: false
});

const buttonClasses = computed(() => {
  const base = 'relative inline-flex items-center justify-center rounded-full font-sans transition-all duration-300 disabled:opacity-50 disabled:pointer-events-none overflow-hidden whitespace-nowrap cursor-pointer select-none';
  
  const variants = {
    primary: 'bg-primary text-white shadow-soft hover:bg-primary-light hover:shadow-glow hover:shadow-primary-light/30',
    secondary: 'bg-secondary text-white shadow-soft hover:bg-secondary-light hover:shadow-glow hover:shadow-secondary-light/30',
    outline: 'border border-primary text-primary hover:bg-primary hover:text-white hover:shadow-glow hover:shadow-primary/20',
    ghost: 'text-primary hover:bg-primary/5',
    gold: 'bg-gradient-to-r from-accent-gold to-yellow-500 text-primary-dark font-bold shadow-soft hover:shadow-glow hover:shadow-accent-gold/45',
    white: 'bg-white/90 backdrop-blur border border-neutral-ivory text-neutral-black hover:bg-white hover:shadow-premium',
  };

  const sizes = {
    sm: 'px-4 py-2 text-xs uppercase tracking-widest font-bold',
    md: 'px-6 py-3.5 text-xs sm:text-sm uppercase tracking-widest font-bold',
    lg: 'px-8 py-4 text-sm sm:text-base uppercase tracking-widest font-extrabold',
  };

  return `${base} ${variants[props.variant]} ${sizes[props.size]}`;
});
</script>

<template>
  <Motion
    :hover="disabled || isLoading ? {} : { scale: 1.02, y: -2 }"
    :press="disabled || isLoading ? {} : { scale: 0.98 }"
    :initial="{ opacity: 0, y: 10 }"
    :animate="{ opacity: 1, y: 0 }"
    :transition="{ type: 'spring', stiffness: 400, damping: 20 }"
    as="div"
    class="inline-block relative overflow-hidden rounded-full"
  >
    <button
      :type="type"
      :class="[buttonClasses, 'w-full h-full']"
      :disabled="disabled || isLoading"
    >
      <!-- Sweep Shimmer Effect -->
      <Motion
        class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -skew-x-[25deg] z-10 pointer-events-none"
        :initial="{ x: '-150%' }"
        :hover="{ x: '150%' }"
        :transition="{ duration: 0.8, easing: 'ease-out' }"
      />
      
      <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center bg-inherit z-20">
        <div class="w-5 h-5 border-2 border-current border-t-transparent rounded-full animate-spin" />
      </div>

      <span :class="['relative z-10 flex items-center gap-2', isLoading ? 'opacity-0' : 'opacity-100']">
        <slot></slot>
      </span>
    </button>
  </Motion>
</template>
