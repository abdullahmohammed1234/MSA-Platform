<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-ivory mb-6">
      <div>
        <h3 class="text-lg font-display font-semibold text-primary">Migration Readiness & Schema Status</h3>
        <p class="text-xs text-neutral-muted">Database schema migrations inspection (Read-only, no auto-execution)</p>
      </div>
      <span class="text-xs font-bold px-3 py-1 rounded-full border" :class="statusBadgeClass">
        {{ migrations.status }}
      </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="bg-emerald-50/60 border border-emerald-200/80 rounded-xl p-4 text-center">
        <div class="text-2xl font-display font-bold text-emerald-700">{{ migrations.applied_count }}</div>
        <div class="text-xs text-emerald-800 font-bold mt-1">Applied Migrations</div>
      </div>

      <div class="bg-neutral-background/70 border border-neutral-ivory rounded-xl p-4 text-center">
        <div class="text-2xl font-display font-bold" :class="migrations.pending_count > 0 ? 'text-amber-700' : 'text-neutral-muted'">
          {{ migrations.pending_count }}
        </div>
        <div class="text-xs text-neutral-muted font-medium mt-1">Pending Migrations</div>
      </div>

      <div class="bg-neutral-background/70 border border-neutral-ivory rounded-xl p-4">
        <div class="text-xs text-neutral-muted font-medium">Latest Applied Migration</div>
        <div class="text-xs font-mono font-bold text-neutral-black mt-1 truncate" :title="migrations.latest_applied || 'None'">
          {{ migrations.latest_applied || 'None' }}
        </div>
      </div>
    </div>

    <div class="bg-neutral-background p-3 rounded-xl border border-neutral-ivory text-xs text-neutral-muted">
      <div class="font-bold mb-1 text-neutral-black">Status Details:</div>
      <div>{{ migrations.details }}</div>
    </div>

    <!-- Pending Migrations List if present -->
    <div v-if="migrations.pending_migrations && migrations.pending_migrations.length > 0" class="mt-4">
      <h4 class="text-xs font-bold text-amber-800 uppercase tracking-wider mb-2">Pending Migration Files</h4>
      <ul class="space-y-1.5 font-mono text-xs text-amber-900 bg-amber-50 p-3 rounded-xl border border-amber-200">
        <li v-for="file in migrations.pending_migrations" :key="file" class="flex items-center gap-2">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
          {{ file }}
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { MigrationStatus } from '../../services/lifecycleService'

const props = defineProps<{
  migrations: MigrationStatus
}>()

const statusBadgeClass = computed(() => {
  switch (props.migrations.status) {
    case 'UP_TO_DATE':
      return 'bg-emerald-100 text-emerald-800 border-emerald-300'
    case 'PENDING_MIGRATIONS':
      return 'bg-amber-100 text-amber-800 border-amber-300'
    default:
      return 'bg-neutral-100 text-neutral-800 border-neutral-300'
  }
})
</script>
