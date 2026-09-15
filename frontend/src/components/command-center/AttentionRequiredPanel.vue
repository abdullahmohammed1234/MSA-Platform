<script setup lang="ts">
import { useRouter } from 'vue-router';
import type { AttentionItem } from '@/services/commandCenterService';

const props = defineProps<{
  items: AttentionItem[];
}>();

const router = useRouter();

const getSeverityClass = (sev: string) => {
  switch (sev) {
    case 'critical':
      return 'bg-red-100 text-red-800 border-red-300';
    case 'high':
      return 'bg-amber-100 text-amber-800 border-amber-300';
    case 'medium':
      return 'bg-yellow-100 text-yellow-800 border-yellow-300';
    default:
      return 'bg-neutral-100 text-neutral-800 border-neutral-300';
  }
};

const handleNavigate = (url: string) => {
  router.push(url);
};
</script>

<template>
  <div class="bg-white rounded-xl border border-neutral-ivory p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-bold text-neutral-black">
          Immediate Attention Required
        </h3>
        <p class="text-xs text-neutral-muted">
          Prioritized stream of critical alerts, pending governance approvals, and blocked automations requiring action.
        </p>
      </div>

      <span class="text-xs font-mono font-bold px-2.5 py-1 bg-red-50 text-red-700 rounded-lg border border-red-200">
        {{ items.length }} Priority Item(s)
      </span>
    </div>

    <div v-if="items.length === 0" class="py-8 text-center bg-neutral-background rounded-lg border border-dashed border-neutral-ivory">
      <svg class="mx-auto h-8 w-8 text-emerald-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="text-xs font-bold text-neutral-black">All Systems Operating Normally</p>
      <p class="text-[11px] text-neutral-muted">No unresolved critical alerts, pending approvals, or blocked automations.</p>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="item in items"
        :key="item.id"
        class="p-4 rounded-xl border border-neutral-ivory hover:border-primary/40 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white"
      >
        <div class="flex items-start gap-3 min-w-0">
          <span :class="['px-2.5 py-0.5 text-[10px] font-bold uppercase rounded border shrink-0 mt-0.5', getSeverityClass(item.severity)]">
            {{ item.severity }}
          </span>

          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-[11px] font-bold text-neutral-muted uppercase font-mono">
                {{ item.source }}
              </span>
              <span class="text-xs font-bold text-neutral-black truncate">
                {{ item.title }}
              </span>
            </div>
            <p class="text-xs text-neutral-muted line-clamp-1 mt-0.5">
              {{ item.summary }}
            </p>
            <span class="text-[10px] text-neutral-muted font-mono">
              {{ item.age_human }}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
          <button
            @click="handleNavigate(item.drilldown_url)"
            class="px-3 py-1.5 text-xs font-bold text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition-colors cursor-pointer flex items-center gap-1"
          >
            <span>{{ item.action_name }}</span>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
