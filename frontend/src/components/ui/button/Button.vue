<script setup lang="ts">
import { computed } from 'vue';
import { Motion } from '@motionone/vue';
import type { ButtonProps } from './types';
import { buttonHover } from '@/design-system/animations/hover';

const props = withDefaults(defineProps<ButtonProps>(), {
  variant: 'primary',
  size: 'md',
  isLoading: false,
  disabled: false,
  isFullWidth: false,
  isShiny: false,
  type: 'button'
});

const emit = defineEmits<{
  (e: 'click', event: MouseEvent): void
}>();

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center gap-2 whitespace-nowrap font-bold transition-colors outline-none focus:outline-none focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 select-none cursor-pointer relative overflow-hidden shadow-none hover:shadow-none focus:shadow-none active:shadow-none';
  
  const variants = {
    primary: 'bg-primary text-white hover:bg-primary/95',
    secondary: 'bg-neutral-ivory text-primary hover:bg-neutral-ivory/80',
    outline: 'border border-neutral-ivory bg-white text-primary hover:bg-neutral-background/60',
    ghost: 'text-primary hover:bg-neutral-background',
    destructive: 'bg-secondary text-white hover:bg-secondary/95',
    gold: 'bg-accent-gold text-primary hover:bg-accent-gold/90 font-bold border border-accent-gold/30',
    success: 'bg-success text-white hover:bg-success/90',
    link: 'text-primary hover:underline bg-transparent p-0 h-auto border-none shadow-none hover:bg-transparent'
  };

  const sizes = {
    sm: 'h-8 px-3 text-xs rounded-md',
    md: 'h-9 px-4 py-2 text-sm rounded-md',
    lg: 'h-11 px-8 text-base rounded-lg',
    icon: 'h-9 w-9 p-0 rounded-md'
  };

  const widthClass = props.isFullWidth ? 'w-full' : '';
  const shinyClass = props.isShiny ? 'animate-shimmer-hover' : '';

  return [
    base,
    variants[props.variant],
    sizes[props.size],
    widthClass,
    shinyClass
  ].join(' ');
});

const motionClasses = computed(() => {
  const roundedClass = {
    sm: 'rounded-md',
    md: 'rounded-md',
    lg: 'rounded-lg',
    icon: 'rounded-md',
  }[props.size];

  return [
    props.isFullWidth ? 'w-full block' : 'inline-block',
    roundedClass,
    'overflow-hidden',
    'shadow-none',
  ].join(' ');
});

const handleClick = (e: MouseEvent) => {
  if (!props.isLoading && !props.disabled) {
    emit('click', e);
  }
};
</script>

<template>
  <Motion
    :hover="disabled || isLoading ? {} : buttonHover.hover"
    :press="disabled || isLoading ? {} : buttonHover.tap"
    :transition="buttonHover.transition"
    as="div"
    :class="motionClasses"
  >
    <button
      :type="type"
      :class="[buttonClasses, 'w-full h-full']"
      :disabled="disabled || isLoading"
      @click="handleClick"
      :aria-busy="isLoading"
      aria-live="polite"
    >
      <span v-if="isLoading" class="mr-1.5 inline-flex items-center">
        <svg class="animate-spin h-4 w-4 text-current" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </span>
      
      <span v-if="!isLoading" class="inline-flex items-center justify-center">
        <slot name="left-icon"></slot>
      </span>
      
      <span :class="{ 'opacity-80': isLoading }">
        <slot></slot>
      </span>

      <span v-if="!isLoading" class="inline-flex items-center justify-center">
        <slot name="right-icon"></slot>
      </span>
    </button>
  </Motion>
</template>
