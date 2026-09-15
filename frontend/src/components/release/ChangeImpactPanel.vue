<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-ivory/60">
      <div>
        <h3 class="text-lg font-display font-semibold text-primary tracking-wide">Deterministic Impact Analysis</h3>
        <p class="text-xs text-neutral-muted">Zero AI guessing — rule-based change impact matrix</p>
      </div>

      <span
        class="px-3 py-1 text-xs font-semibold rounded-md font-mono border"
        :class="impactBadgeClass(impact.overall_impact_level)"
      >
        {{ impact.overall_impact_level || 'LOW' }} IMPACT
      </span>
    </div>

    <!-- Requirements Matrix -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 my-6">
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-3 text-center">
        <div class="text-xs font-bold text-neutral-muted uppercase tracking-wider mb-1">Migration</div>
        <div class="text-sm font-bold font-mono" :class="impact.requires_migration ? 'text-amber-700' : 'text-emerald-700'">
          {{ impact.requires_migration ? 'YES' : 'NO' }}
        </div>
      </div>
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-3 text-center">
        <div class="text-xs font-bold text-neutral-muted uppercase tracking-wider mb-1">RBAC Update</div>
        <div class="text-sm font-bold font-mono" :class="impact.requires_rbac_update ? 'text-purple-700' : 'text-emerald-700'">
          {{ impact.requires_rbac_update ? 'YES' : 'NO' }}
        </div>
      </div>
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-3 text-center">
        <div class="text-xs font-bold text-neutral-muted uppercase tracking-wider mb-1">Queue Flush</div>
        <div class="text-sm font-bold font-mono" :class="impact.requires_queue_flush ? 'text-amber-700' : 'text-emerald-700'">
          {{ impact.requires_queue_flush ? 'YES' : 'NO' }}
        </div>
      </div>
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-3 text-center">
        <div class="text-xs font-bold text-neutral-muted uppercase tracking-wider mb-1">Breaking</div>
        <div class="text-sm font-bold font-mono" :class="impact.has_breaking_changes ? 'text-rose-700' : 'text-emerald-700'">
          {{ impact.has_breaking_changes ? 'YES' : 'NO' }}
        </div>
      </div>
    </div>

    <!-- Affected Services / Applications -->
    <div class="space-y-3">
      <div>
        <h4 class="text-xs font-bold text-neutral-muted uppercase tracking-wider mb-2">Affected Applications & Services</h4>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="app in impact.affected_applications"
            :key="app"
            class="px-2.5 py-1 text-xs font-mono rounded-md bg-primary/10 border border-primary/20 text-primary font-semibold"
          >
            {{ app }}
          </span>
          <span
            v-for="svc in impact.affected_services"
            :key="svc"
            class="px-2.5 py-1 text-xs font-mono rounded-md bg-neutral-100 border border-neutral-200 text-neutral-800 font-semibold"
          >
            {{ svc }}
          </span>
        </div>
      </div>

      <!-- Risk Factors -->
      <div v-if="impact.risk_factors && impact.risk_factors.length > 0" class="pt-3 border-t border-neutral-ivory/60">
        <h4 class="text-xs font-bold text-rose-700 uppercase tracking-wider mb-2">Evaluated Risk Factors</h4>
        <ul class="space-y-1">
          <li
            v-for="risk in impact.risk_factors"
            :key="risk"
            class="text-xs text-rose-700 flex items-center gap-2 font-medium"
          >
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-600"></span>
            {{ risk }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ImpactAnalysis } from '../../services/releaseService'

defineProps<{
  impact: Partial<ImpactAnalysis>
}>()

const impactBadgeClass = (impact?: string) => {
  switch (impact) {
    case 'LOW':
      return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
    case 'MEDIUM':
      return 'bg-blue-50 text-blue-700 border border-blue-200'
    case 'HIGH':
      return 'bg-amber-50 text-amber-700 border border-amber-200'
    case 'CRITICAL':
      return 'bg-rose-50 text-rose-700 border border-rose-200'
    default:
      return 'bg-neutral-100 text-neutral-700 border border-neutral-200'
  }
}
</script>
