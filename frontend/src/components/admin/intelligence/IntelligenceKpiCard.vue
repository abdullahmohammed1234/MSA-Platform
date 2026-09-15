<script setup lang="ts">
import { computed } from 'vue';
import { TrendingUp, TrendingDown, ArrowUpRight } from 'lucide-vue-next';

const props = defineProps<{
  title: string;
  value: number | string;
  previousValue?: number | string;
  pctChange?: number;
  prefix?: string;
  suffix?: string;
  subtitle?: string;
  link?: string;
  linkText?: string;
  icon?: any;
}>();

const formattedValue = computed(() => {
  if (typeof props.value === 'number') {
    return (props.prefix || '') + props.value.toLocaleString() + (props.suffix || '');
  }
  return (props.prefix || '') + props.value + (props.suffix || '');
});

const isPositive = computed(() => (props.pctChange ?? 0) > 0);
const isNegative = computed(() => (props.pctChange ?? 0) < 0);
</script>

<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-5 hover:border-neutral-gray transition-all shadow-soft flex flex-col justify-between space-y-3">
    <div class="flex items-center justify-between">
      <div class="text-xs uppercase font-bold tracking-wider text-neutral-muted flex items-center gap-2">
        <component :is="icon" v-if="icon" class="w-4 h-4 text-primary" />
        <span>{{ title }}</span>
      </div>

      <div 
        v-if="pctChange !== undefined"
        :class="[
          'px-2.5 py-0.5 rounded-full text-[11px] font-bold flex items-center gap-1 border',
          isPositive ? 'bg-emerald-50 text-emerald-800 border-emerald-200' :
          isNegative ? 'bg-red-50 text-red-800 border-red-200' :
          'bg-neutral-background text-neutral-muted border-neutral-ivory'
        ]"
      >
        <TrendingUp v-if="isPositive" class="w-3 h-3" />
        <TrendingDown v-if="isNegative" class="w-3 h-3" />
        <span>{{ isPositive ? '+' : '' }}{{ pctChange }}%</span>
      </div>
    </div>

    <div>
      <div class="text-3xl font-display font-bold text-neutral-black tracking-tight">{{ formattedValue }}</div>
      <div v-if="subtitle || previousValue !== undefined" class="text-xs text-neutral-muted mt-1 flex items-center gap-2">
        <span v-if="subtitle">{{ subtitle }}</span>
        <span v-else-if="previousValue !== undefined">Prev: {{ prefix || '' }}{{ previousValue }}{{ suffix || '' }}</span>
      </div>
    </div>

    <div v-if="link" class="pt-2 border-t border-neutral-ivory flex justify-end">
      <router-link :to="link" class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:text-primary-dark transition-colors">
        <span>{{ linkText || 'View details' }}</span>
        <ArrowUpRight class="w-3.5 h-3.5" />
      </router-link>
    </div>
  </div>
</template>
