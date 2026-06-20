<template>
  <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft flex flex-col justify-between transition-all duration-300 hover:shadow-premium hover:-translate-y-0.5">
    <div>
      <div class="flex justify-between items-start">
        <span class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted">{{ title }}</span>
        <span 
          v-if="change !== undefined"
          class="text-xs font-semibold px-2 py-0.5 rounded-full flex items-center gap-0.5"
          :class="change >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
        >
          <span class="font-sans">{{ change >= 0 ? '↑' : '↓' }}</span>
          <span>{{ Math.abs(change) }}%</span>
        </span>
      </div>
      <p class="text-3xl font-display font-semibold text-primary mt-3">
        {{ formattedValue }}
      </p>
    </div>
    <div class="mt-4 text-xs text-neutral-muted border-t border-neutral-ivory/50 pt-3">
      {{ description }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  title: string;
  value: number | string;
  change?: number;
  description: string;
}>();

const formattedValue = computed(() => {
  if (typeof props.value === 'number') {
    return new Intl.NumberFormat().format(props.value);
  }
  return props.value;
});
</script>
