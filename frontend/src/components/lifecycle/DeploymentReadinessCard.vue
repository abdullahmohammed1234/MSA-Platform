<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-neutral-ivory">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="inline-block w-2.5 h-2.5 rounded-full" :class="statusDotClass"></span>
          <h2 class="text-xl font-display font-semibold text-primary tracking-wide">Deployment Readiness Evaluation</h2>
        </div>
        <p class="text-sm text-neutral-muted">
          Deterministic 9-domain pre-deployment check evaluated at {{ formattedTime }}
        </p>
      </div>

      <div class="flex items-center gap-3">
        <span
          class="px-4 py-1.5 text-xs font-bold rounded-xl tracking-wider border shadow-xs"
          :class="badgeClass"
        >
          {{ readiness.readiness_status }}
        </span>
      </div>
    </div>

    <!-- Summary Counters -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 my-6">
      <div class="bg-emerald-50/60 border border-emerald-200/80 rounded-xl p-3 text-center">
        <div class="text-2xl font-display font-bold text-emerald-700">{{ readiness.summary.passed }}</div>
        <div class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider mt-0.5">Passed Checks</div>
      </div>
      <div class="bg-amber-50/60 border border-amber-200/80 rounded-xl p-3 text-center">
        <div class="text-2xl font-display font-bold text-amber-700">{{ readiness.summary.warning }}</div>
        <div class="text-[10px] font-bold text-amber-800 uppercase tracking-wider mt-0.5">Warnings</div>
      </div>
      <div class="bg-red-50/60 border border-red-200/80 rounded-xl p-3 text-center">
        <div class="text-2xl font-display font-bold text-red-700">{{ readiness.summary.failed }}</div>
        <div class="text-[10px] font-bold text-red-800 uppercase tracking-wider mt-0.5">Failures</div>
      </div>
      <div class="bg-neutral-background border border-neutral-ivory rounded-xl p-3 text-center">
        <div class="text-2xl font-display font-bold text-neutral-muted">{{ readiness.summary.unknown }}</div>
        <div class="text-[10px] font-bold text-neutral-muted uppercase tracking-wider mt-0.5">Unverified</div>
      </div>
    </div>

    <!-- Checks Breakdown List -->
    <div class="space-y-3">
      <div
        v-for="check in readiness.checks"
        :key="check.key"
        class="bg-neutral-background/60 border rounded-xl p-4 transition-colors"
        :class="checkBorderClass(check.status)"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-white border border-neutral-ivory text-neutral-black">
                {{ check.domain }}
              </span>
              <h4 class="text-sm font-semibold text-neutral-black">{{ check.title }}</h4>
            </div>
            <p class="text-xs text-neutral-muted leading-relaxed">{{ check.details }}</p>
            <div v-if="check.remediation" class="mt-2 text-xs text-amber-900 font-mono bg-amber-50 p-2 rounded-lg border border-amber-200">
              <span class="font-bold">Fix:</span> {{ check.remediation }}
            </div>
          </div>

          <span class="px-2.5 py-1 text-xs font-bold rounded-lg shrink-0 border" :class="checkTagClass(check.status)">
            {{ check.status }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { DeploymentReadiness } from '../../services/lifecycleService'

const props = defineProps<{
  readiness: DeploymentReadiness
}>()

const formattedTime = computed(() => {
  if (!props.readiness.evaluated_at) return ''
  try {
    return new Date(props.readiness.evaluated_at).toLocaleString()
  } catch {
    return props.readiness.evaluated_at
  }
})

const badgeClass = computed(() => {
  switch (props.readiness.readiness_status) {
    case 'READY':
      return 'bg-emerald-100 text-emerald-800 border-emerald-300'
    case 'READY_WITH_WARNINGS':
      return 'bg-amber-100 text-amber-800 border-amber-300'
    case 'NOT_READY':
      return 'bg-red-100 text-red-800 border-red-300'
    default:
      return 'bg-neutral-100 text-neutral-800 border-neutral-300'
  }
})

const statusDotClass = computed(() => {
  switch (props.readiness.readiness_status) {
    case 'READY':
      return 'bg-emerald-500'
    case 'READY_WITH_WARNINGS':
      return 'bg-amber-500'
    case 'NOT_READY':
      return 'bg-red-500'
    default:
      return 'bg-neutral-400'
  }
})

function checkBorderClass(status: string) {
  switch (status) {
    case 'PASSED':
      return 'border-neutral-ivory hover:border-emerald-200'
    case 'WARNING':
      return 'border-amber-200 bg-amber-50/30'
    case 'FAILED':
      return 'border-red-200 bg-red-50/30'
    default:
      return 'border-neutral-ivory'
  }
}

function checkTagClass(status: string) {
  switch (status) {
    case 'PASSED':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200'
    case 'WARNING':
      return 'bg-amber-100 text-amber-800 border-amber-200'
    case 'FAILED':
      return 'bg-red-100 text-red-800 border-red-200'
    default:
      return 'bg-neutral-100 text-neutral-800 border-neutral-200'
  }
}
</script>
