<template>
  <span :class="badgeClasses">
    <slot></slot>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  variant?: 'burgundy' | 'gold' | 'green' | 'neutral' | 'outline' | 'red' | 'blue';
  size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'burgundy',
  size: 'md',
});

const badgeClasses = computed(() => {
  const base = 'inline-flex items-center font-medium rounded-full select-none tracking-tight';

  const variants = {
    burgundy: 'bg-[#640c0e]/10 text-[#640c0e] border border-[#640c0e]/20',
    gold: 'bg-[#ffdc83]/40 text-[#640c0e] font-semibold border border-[#e5b955]/60',
    green: 'bg-emerald-50 text-emerald-800 border border-emerald-200/80',
    blue: 'bg-sky-50 text-sky-800 border border-sky-200/80',
    neutral: 'bg-[#ebe8de]/70 text-neutral-700 border border-[#ebe8de]',
    outline: 'bg-transparent text-[#640c0e] border border-[#640c0e]/30',
    red: 'bg-rose-50 text-[#b02e32] border border-rose-200/80',
  };

  const sizes = {
    sm: 'text-[11px] px-2.5 py-0.5 leading-tight gap-1',
    md: 'text-xs px-3 py-1 leading-normal gap-1.5',
  };

  return `${base} ${variants[props.variant]} ${sizes[props.size]}`;
});
</script>
