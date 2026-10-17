<template>
  <div :class="cardClasses">
    <div v-if="$slots.header || title" class="px-6 py-5 border-b border-[#ebe8de]/70 flex items-center justify-between">
      <div>
        <h3 v-if="title" class="text-lg font-semibold text-[#640c0e]">
          {{ title }}
        </h3>
        <p v-if="subtitle" class="text-xs text-neutral-500 mt-0.5">
          {{ subtitle }}
        </p>
      </div>
      <slot name="header-action"></slot>
    </div>

    <div :class="paddingClass">
      <slot></slot>
    </div>

    <div v-if="$slots.footer" class="px-6 py-4 bg-[#fffbf4]/40 border-t border-[#ebe8de]/70 rounded-b-2xl">
      <slot name="footer"></slot>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  title?: string;
  subtitle?: string;
  variant?: 'default' | 'premium' | 'glass' | 'flat';
  noPadding?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  noPadding: false,
});

const cardClasses = computed(() => {
  const base = 'rounded-2xl transition-all duration-200 overflow-hidden';
  const variants = {
    default: 'bg-white border border-[#ebe8de] shadow-sm',
    premium: 'bg-white border border-[#ebe8de] shadow-[0_10px_35px_-10px_rgba(100,12,14,0.1)] hover:shadow-[0_15px_40px_-10px_rgba(100,12,14,0.18)]',
    glass: 'bg-white/85 backdrop-blur-md border border-white/70 shadow-sm',
    flat: 'bg-[#fffbf4] border border-[#ebe8de]',
  };
  return `${base} ${variants[props.variant]}`;
});

const paddingClass = computed(() => (props.noPadding ? '' : 'p-6'));
</script>
