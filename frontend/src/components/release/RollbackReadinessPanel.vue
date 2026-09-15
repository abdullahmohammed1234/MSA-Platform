<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-ivory/60">
      <div>
        <h3 class="text-lg font-display font-semibold text-primary tracking-wide">Truthful Rollback Readiness</h3>
        <p class="text-xs text-neutral-muted">cPanel / Shared Hosting manual rollback recovery evaluation</p>
      </div>

      <span
        class="px-3.5 py-1.5 text-xs font-semibold rounded-xl tracking-wider border shadow-sm"
        :class="rollbackBadgeClass(rollback.rollback_readiness)"
      >
        {{ rollback.rollback_readiness || 'ROLLBACK_MANUAL' }}
      </span>
    </div>

    <!-- Overview Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 my-6">
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-4">
        <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider mb-1">Previous Release Baseline</div>
        <div class="text-sm font-bold font-mono text-primary">
          {{ rollback.previous_release_identifier || 'None Recorded' }}
        </div>
      </div>
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-4">
        <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider mb-1">Migration Rollback Compatible</div>
        <div class="text-sm font-bold font-mono" :class="rollback.migration_rollback_compatible ? 'text-emerald-700' : 'text-amber-700'">
          {{ rollback.migration_rollback_compatible ? 'YES (No DB Schema Changes)' : 'MANUAL DB RESTORE REQUIRED' }}
        </div>
      </div>
      <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-4">
        <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider mb-1">Backup Verification</div>
        <div class="text-sm font-bold font-mono text-neutral-muted">
          {{ rollback.backup_verification_state || 'NOT_VERIFIED' }}
        </div>
      </div>
    </div>

    <!-- Rollback Blockers / Warnings -->
    <div v-if="rollback.blockers && rollback.blockers.length > 0" class="mb-6">
      <h4 class="text-xs font-bold text-rose-700 uppercase tracking-wider mb-2">Rollback Blockers & Constraints</h4>
      <div class="space-y-2">
        <div
          v-for="(blocker, idx) in rollback.blockers"
          :key="idx"
          class="bg-rose-50 border border-rose-200 rounded-xl p-3 text-xs text-rose-700 flex items-start gap-2 font-medium"
        >
          <span class="font-bold text-rose-700">!</span>
          <span>{{ blocker }}</span>
        </div>
      </div>
    </div>

    <!-- Manual Recovery Steps -->
    <div>
      <h4 class="text-xs font-bold text-neutral-muted uppercase tracking-wider mb-3">Deterministic Manual Rollback Steps</h4>
      <div class="space-y-2">
        <div
          v-for="(step, idx) in rollback.manual_recovery_steps"
          :key="idx"
          class="bg-neutral-background/40 border border-neutral-ivory rounded-xl p-3 text-xs text-neutral-black flex items-start gap-3"
        >
          <span class="w-5 h-5 rounded-full bg-primary text-white font-mono font-bold flex items-center justify-center text-[10px] shrink-0">
            {{ idx + 1 }}
          </span>
          <span class="leading-relaxed">{{ step }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { RollbackReadiness } from '../../services/releaseService'

defineProps<{
  rollback: Partial<RollbackReadiness>
}>()

const rollbackBadgeClass = (status?: string) => {
  switch (status) {
    case 'ROLLBACK_READY':
      return 'bg-emerald-50 border-emerald-200 text-emerald-700'
    case 'ROLLBACK_MANUAL':
      return 'bg-amber-50 border-amber-200 text-amber-700'
    case 'ROLLBACK_UNAVAILABLE':
      return 'bg-rose-50 border-rose-200 text-rose-700'
    default:
      return 'bg-neutral-100 border-neutral-200 text-neutral-700'
  }
}
</script>
