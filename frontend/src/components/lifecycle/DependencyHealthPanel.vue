<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-ivory mb-6">
      <div>
        <h3 class="text-lg font-display font-semibold text-primary">Dependency & Service Inventory</h3>
        <p class="text-xs text-neutral-muted">Core service providers, infrastructure probes & truthfulness semantics</p>
      </div>
      <span class="text-xs font-bold px-3 py-1 rounded-full border" :class="overallBadgeClass">
        {{ dependencies.overall_status }}
      </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="(service, key) in dependencies.services"
        :key="key"
        class="bg-neutral-background/60 border rounded-xl p-4 flex flex-col justify-between"
        :class="serviceBorderClass(service.status)"
      >
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-mono font-bold text-neutral-muted uppercase tracking-wider">{{ service.category }}</span>
            <span class="px-2 py-0.5 text-xs font-bold rounded-lg border" :class="statusTagClass(service.status)">
              {{ service.status }}
            </span>
          </div>

          <h4 class="text-sm font-semibold text-neutral-black mb-1">{{ service.name }}</h4>
          <div class="text-xs text-neutral-muted mb-2 leading-relaxed">{{ service.details }}</div>
        </div>

        <div class="pt-3 border-t border-neutral-ivory mt-2 text-[11px] text-neutral-muted space-y-1">
          <div>Driver: <span class="font-mono text-neutral-black font-semibold">{{ service.driver }}</span></div>
          <div v-if="service.limitations" class="text-amber-700 italic">
            Note: {{ service.limitations }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { DependencyHealth } from '../../services/lifecycleService'

const props = defineProps<{
  dependencies: DependencyHealth
}>()

const overallBadgeClass = computed(() => {
  switch (props.dependencies.overall_status) {
    case 'HEALTHY':
      return 'bg-emerald-100 text-emerald-800 border-emerald-300'
    case 'HEALTHY_WITH_UNVERIFIED_SIGNALS':
      return 'bg-blue-100 text-blue-800 border-blue-300'
    case 'DEGRADED':
      return 'bg-amber-100 text-amber-800 border-amber-300'
    default:
      return 'bg-neutral-100 text-neutral-800 border-neutral-300'
  }
})

function serviceBorderClass(status: string) {
  switch (status) {
    case 'HEALTHY':
      return 'border-neutral-ivory hover:border-emerald-200'
    case 'DEGRADED':
      return 'border-amber-200 bg-amber-50/30'
    case 'UNAVAILABLE':
      return 'border-red-200 bg-red-50/30'
    case 'NOT_VERIFIED':
    case 'UNKNOWN':
      return 'border-blue-200 bg-blue-50/30'
    default:
      return 'border-neutral-ivory'
  }
}

function statusTagClass(status: string) {
  switch (status) {
    case 'HEALTHY':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200'
    case 'DEGRADED':
      return 'bg-amber-100 text-amber-800 border-amber-200'
    case 'UNAVAILABLE':
      return 'bg-red-100 text-red-800 border-red-200'
    case 'NOT_CONFIGURED':
      return 'bg-neutral-100 text-neutral-800 border-neutral-200'
    case 'NOT_VERIFIED':
    case 'UNKNOWN':
      return 'bg-blue-100 text-blue-800 border-blue-200'
    default:
      return 'bg-neutral-100 text-neutral-800 border-neutral-200'
  }
}
</script>
