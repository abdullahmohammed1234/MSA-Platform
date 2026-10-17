<template>
  <button
    :type="type"
    :class="buttonClasses"
    :disabled="disabled || isLoading"
    :aria-busy="isLoading"
    @click="handleClick"
  >
    <svg
      v-if="isLoading"
      class="animate-spin -ml-1 mr-2 h-4 w-4 text-current"
      fill="none"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      ></path>
    </svg>
    <slot name="left-icon" v-if="!isLoading"></slot>
    <slot></slot>
    <slot name="right-icon" v-if="!isLoading"></slot>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'gold' | 'destructive';
  size?: 'sm' | 'md' | 'lg' | 'icon';
  type?: 'button' | 'submit' | 'reset';
  isLoading?: boolean;
  disabled?: boolean;
  block?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  type: 'button',
  isLoading: false,
  disabled: false,
  block: false,
});

const emit = defineEmits<{
  (e: 'click', event: MouseEvent): void;
}>();

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center font-medium rounded-xl transition-all duration-200 select-none cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#640c0e] focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

  const variants = {
    primary: 'bg-[#640c0e] hover:bg-[#7a0f12] text-white shadow-sm hover:shadow active:scale-[0.99]',
    secondary: 'bg-[#ebe8de] hover:bg-[#ded9cb] text-[#640c0e] font-semibold active:scale-[0.99]',
    outline: 'border border-[#ebe8de] hover:border-[#640c0e]/30 bg-white hover:bg-[#fffbf4] text-[#640c0e] active:scale-[0.99]',
    ghost: 'hover:bg-[#ebe8de]/50 text-[#640c0e] active:scale-[0.99]',
    gold: 'bg-[#ffdc83] hover:bg-[#e5b955] text-[#640c0e] font-bold shadow-sm active:scale-[0.99]',
    destructive: 'bg-[#ea2128] hover:bg-red-700 text-white shadow-sm active:scale-[0.99]',
  };

  const sizes = {
    sm: 'h-8 px-3 text-xs gap-1.5',
    md: 'h-10 px-4 text-sm gap-2',
    lg: 'h-12 px-6 text-base gap-2.5',
    icon: 'h-10 w-10 p-0',
  };

  const width = props.block ? 'w-full' : '';

  return `${base} ${variants[props.variant]} ${sizes[props.size]} ${width}`;
});

function handleClick(e: MouseEvent) {
  if (!props.isLoading && !props.disabled) {
    emit('click', e);
  }
}
</script>
