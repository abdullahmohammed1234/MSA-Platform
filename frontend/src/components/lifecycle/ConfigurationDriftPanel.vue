<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-ivory mb-6">
      <div>
        <h3 class="text-lg font-display font-semibold text-primary">Configuration Drift Diagnostics</h3>
        <p class="text-xs text-neutral-muted">Rule-based detection of missing required keys, driver mismatches & security drift</p>
      </div>
      <span class="text-xs font-bold px-3 py-1 rounded-full border" :class="statusBadgeClass">
        {{ drift.status }}
      </span>
    </div>

    <!-- Empty State if No Drift -->
    <div v-if="drift.total_findings === 0" class="bg-neutral-background/70 border border-neutral-ivory rounded-xl p-6 text-center">
      <div class="w-10 h-10 rounded-full bg-emerald-100 border border-emerald-300 text-emerald-800 flex items-center justify-center mx-auto mb-3 font-bold text-lg">
        ✓
      </div>
      <h4 class="text-sm font-semibold text-neutral-black mb-1">No Configuration Drift Detected</h4>
      <p class="text-xs text-neutral-muted">All required platform parameters, drivers, security settings, and database schema states align with baseline rules.</p>
    </div>

    <!-- Drift Findings List -->
    <div v-else class="space-y-4">
      <div class="flex items-center gap-3 text-xs text-neutral-muted bg-neutral-background p-3 rounded-xl border border-neutral-ivory font-mono">
        <span>Total Findings: <strong class="text-neutral-black">{{ drift.total_findings }}</strong></span>
        <span>•</span>
        <span class="text-red-700">Critical: <strong>{{ drift.critical_count }}</strong></span>
        <span>•</span>
        <span class="text-amber-700">Warning: <strong>{{ drift.warning_count }}</strong></span>
        <span>•</span>
        <span class="text-blue-700">Info: <strong>{{ drift.info_count }}</strong></span>
      </div>

      <div class="space-y-3">
        <div
          v-for="finding in drift.findings"
          :key="finding.rule_key"
          class="bg-neutral-background/60 border rounded-xl p-4 transition-colors"
          :class="findingBorderClass(finding.severity)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="space-y-1.5">
              <div class="flex items-center gap-2">
                <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-white border border-neutral-ivory text-neutral-black">
                  {{ finding.category }}
                </span>
                <span class="font-mono text-xs font-bold text-neutral-black">{{ finding.rule_key }}</span>
              </div>
              <p class="text-xs text-neutral-muted leading-relaxed">{{ finding.explanation }}</p>

              <div class="mt-2 text-xs font-mono p-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-900">
                <span class="font-bold text-amber-800">Remediation:</span> {{ finding.remediation }}
              </div>
            </div>

            <span class="px-2.5 py-1 text-xs font-bold rounded-lg shrink-0 uppercase border" :class="severityTagClass(finding.severity)">
              {{ finding.severity }}
            </span>
          </div>

          <div class="mt-2 pt-2 border-t border-neutral-ivory text-[10px] font-mono text-neutral-muted">
            Verification source: {{ finding.verification_source }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ConfigurationDrift } from '../../services/lifecycleService'

const props = defineProps<{
  drift: ConfigurationDrift
}>()

const statusBadgeClass = computed(() => {
  switch (props.drift.status) {
    case 'NO_DRIFT':
      return 'bg-emerald-100 text-emerald-800 border-emerald-300'
    case 'MINOR_DRIFT':
      return 'bg-blue-100 text-blue-800 border-blue-300'
    case 'WARNING_DRIFT':
      return 'bg-amber-100 text-amber-800 border-amber-300'
    case 'CRITICAL_DRIFT':
      return 'bg-red-100 text-red-800 border-red-300'
    default:
      return 'bg-neutral-100 text-neutral-800 border-neutral-300'
  }
})

function findingBorderClass(severity: string) {
  switch (severity) {
    case 'critical':
      return 'border-red-200 bg-red-50/30'
    case 'warning':
      return 'border-amber-200 bg-amber-50/30'
    default:
      return 'border-neutral-ivory'
  }
}

function severityTagClass(severity: string) {
  switch (severity) {
    case 'critical':
      return 'bg-red-100 text-red-800 border-red-200'
    case 'warning':
      return 'bg-amber-100 text-amber-800 border-amber-200'
    default:
      return 'bg-blue-100 text-blue-800 border-blue-200'
  }
}
</script>
