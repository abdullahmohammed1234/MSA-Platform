<script setup lang="ts">
import { computed } from 'vue';
import type { OperationalRisk } from '@/services/commandCenterService';

const props = defineProps<{
  risk: OperationalRisk;
}>();

const badgeClass = computed(() => {
  switch (props.risk.level) {
    case 'CRITICAL':
      return 'bg-red-100 text-red-800 border-red-300';
    case 'HIGH':
      return 'bg-amber-100 text-amber-800 border-amber-300';
    case 'MEDIUM':
      return 'bg-yellow-100 text-yellow-800 border-yellow-300';
    default:
      return 'bg-emerald-100 text-emerald-800 border-emerald-300';
  }
});
</script>

<template>
  <div class="bg-white rounded-xl border border-neutral-ivory p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <svg class="h-5 w-5 text-neutral-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <h3 class="text-sm font-bold text-neutral-muted uppercase tracking-wider">
          Platform Operational Risk Score
        </h3>
      </div>

      <div class="flex items-center gap-2">
        <span class="text-xs font-mono font-bold text-neutral-muted">
          {{ risk.score }} pts
        </span>
        <span :class="['px-3 py-1 text-xs font-bold uppercase rounded-full border', badgeClass]">
          {{ risk.level }} RISK
        </span>
      </div>
    </div>

    <div class="space-y-2">
      <h4 class="text-xs font-bold text-neutral-black uppercase tracking-wider">
        Contributing Risk Factors:
      </h4>
      <ul class="space-y-1.5 text-xs text-neutral-muted">
        <li
          v-for="(factor, idx) in risk.contributing_factors"
          :key="idx"
          class="flex items-start gap-2"
        >
          <span class="text-primary font-bold">•</span>
          <span>{{ factor }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>
