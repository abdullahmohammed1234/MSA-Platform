<template>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-bold text-slate-900">Change Accountability</h3>
        <p class="text-xs text-slate-500">WHO, WHAT, WHEN, WHERE, WHY & RESULT</p>
      </div>
    </div>

    <div v-if="items.length === 0" class="text-center py-8 text-slate-400 text-xs">
      No recent administrative changes recorded.
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="item in items"
        :key="item.id"
        class="p-4 bg-slate-50 rounded-xl border border-slate-200/80 hover:border-slate-300 transition"
      >
        <div class="flex items-start justify-between gap-2 flex-wrap mb-2">
          <div class="flex items-center gap-2">
            <span class="font-bold text-slate-900 text-xs">{{ item.what.action }}</span>
            <span class="px-2 py-0.5 bg-slate-200 text-slate-700 font-semibold rounded text-[10px] uppercase">
              {{ item.where.domain }}
            </span>
          </div>
          <span class="text-[11px] text-slate-400 font-medium">{{ item.when.human }}</span>
        </div>

        <p class="text-xs text-slate-700 mb-3">{{ item.what.description }}</p>

        <!-- Structured WHO, WHERE, WHY, RESULT Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 text-[11px] bg-white p-2.5 rounded-lg border border-slate-100">
          <div>
            <span class="text-slate-400 uppercase font-bold block text-[9px]">WHO</span>
            <span class="font-medium text-slate-800">{{ item.who.name }}</span>
          </div>
          <div>
            <span class="text-slate-400 uppercase font-bold block text-[9px]">WHERE</span>
            <span class="font-medium text-slate-800">
              {{ item.where.domain }} {{ item.where.target_type ? `(${item.where.target_type})` : '' }}
            </span>
          </div>
          <div>
            <span class="text-slate-400 uppercase font-bold block text-[9px]">WHY</span>
            <span
              class="font-medium"
              :class="item.why === 'Reason not recorded' ? 'text-amber-600 italic' : 'text-slate-800'"
            >
              {{ item.why }}
            </span>
          </div>
          <div>
            <span class="text-slate-400 uppercase font-bold block text-[9px]">RESULT</span>
            <span class="font-semibold text-emerald-700">{{ item.result }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ChangeAccountabilityItem } from '@/services/governanceService';

defineProps<{
  items: ChangeAccountabilityItem[];
}>();
</script>
