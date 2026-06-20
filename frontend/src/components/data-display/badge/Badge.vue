<script setup lang="ts">
import { computed } from 'vue';
import type { BadgeProps } from './types';

const props = withDefaults(defineProps<BadgeProps>(), {
  variant: 'primary',
  size: 'md',
  isShimmer: false,
  isPulse: false
});

const badgeClasses = computed(() => {
  const base = 'inline-flex items-center justify-center font-bold tracking-wider uppercase rounded-full select-none relative overflow-hidden';
  
  const variants = {
    primary: 'bg-primary/10 text-primary border border-primary/20',
    secondary: 'bg-neutral-ivory text-primary border border-neutral-ivory/50',
    success: 'bg-emerald-100 text-emerald-800 border border-emerald-200',
    warning: 'bg-amber-100 text-amber-800 border border-amber-200',
    error: 'bg-red-100 text-secondary border border-red-200', // secondary is red
    info: 'bg-sky-100 text-sky-800 border border-sky-200',
    gold: 'bg-accent-gold/20 text-primary border border-accent-gold/40',
    outline: 'border border-neutral-ivory bg-transparent text-neutral-muted'
  };

  const sizes = {
    sm: 'text-[9px] px-2 py-0.5',
    md: 'text-[10px] px-2.5 py-1',
    lg: 'text-xs px-3.5 py-1.5'
  };

  const shimmer = props.isShimmer ? 'animate-shimmer-auto' : '';
  
  return [
    base,
    variants[props.variant],
    sizes[props.size],
    shimmer
  ].join(' ');
});
</script>

<template>
  <span :class="badgeClasses">
    <!-- Pulse indicator dot if requested -->
    <span v-if="isPulse" class="relative flex h-1.5 w-1.5 mr-1.5">
      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-current opacity-75"></span>
      <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-current"></span>
    </span>
    <slot></slot>
  </span>
</template>
