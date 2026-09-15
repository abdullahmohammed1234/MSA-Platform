<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-ivory/60">
      <div>
        <h3 class="text-lg font-display font-semibold text-primary tracking-wide">Operational Changes Registry</h3>
        <p class="text-xs text-neutral-muted">Registered platform changes linked to releases and system services</p>
      </div>

      <!-- Filters -->
      <div class="flex items-center gap-3">
        <select
          v-model="selectedCategory"
          class="bg-white border border-neutral-ivory text-xs rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 cursor-pointer"
        >
          <option value="">All Categories</option>
          <option value="backend_code">Backend Code</option>
          <option value="frontend_code">Frontend Code</option>
          <option value="migration">Database Migration</option>
          <option value="configuration">Configuration</option>
          <option value="rbac">RBAC / Permissions</option>
          <option value="scheduled_job">Scheduled Job</option>
          <option value="queue">Queue System</option>
          <option value="remediation_action">Remediation Action</option>
        </select>

        <select
          v-model="selectedImpact"
          class="bg-white border border-neutral-ivory text-xs rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 cursor-pointer"
        >
          <option value="">All Impact Levels</option>
          <option value="LOW">Low</option>
          <option value="MEDIUM">Medium</option>
          <option value="HIGH">High</option>
          <option value="CRITICAL">Critical</option>
        </select>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="filteredChanges.length === 0" class="py-12 text-center">
      <p class="text-sm text-neutral-muted">No operational changes match the selected criteria.</p>
    </div>

    <!-- Changes List -->
    <div v-else class="space-y-3 mt-4">
      <div
        v-for="change in filteredChanges"
        :key="change.id || change.change_identifier"
        class="bg-neutral-background/40 border border-neutral-ivory rounded-xl p-4 transition-colors hover:border-neutral-gray/50"
      >
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs font-mono px-2 py-0.5 rounded-md bg-primary/10 text-primary border border-primary/20 font-semibold">
                {{ change.change_identifier || 'CHG-' + change.id }}
              </span>
              <span class="text-xs px-2 py-0.5 rounded-md font-mono font-semibold" :class="categoryBadgeClass(change.category)">
                {{ change.category }}
              </span>
              <h4 class="text-sm font-semibold text-neutral-black">{{ change.title }}</h4>
            </div>

            <p v-if="change.description" class="text-xs text-neutral-muted mt-1 leading-relaxed">
              {{ change.description }}
            </p>

            <div class="flex flex-wrap items-center gap-2 mt-2 text-xs text-neutral-muted">
              <span v-if="change.requires_migration" class="px-2 py-0.5 rounded-md bg-amber-50 border border-amber-200 text-amber-700 font-semibold">
                Migration Required
              </span>
              <span v-if="change.is_breaking" class="px-2 py-0.5 rounded-md bg-rose-50 border border-rose-200 text-rose-700 font-semibold">
                Breaking Change
              </span>
              <span v-if="change.requires_rbac_update" class="px-2 py-0.5 rounded-md bg-purple-50 border border-purple-200 text-purple-700 font-semibold">
                RBAC Update
              </span>
              <span>Apps: {{ (change.affected_applications || []).join(', ') || 'platform' }}</span>
            </div>
          </div>

          <div class="flex items-center gap-3 shrink-0">
            <span class="px-2.5 py-1 text-xs font-semibold rounded-md font-mono border" :class="impactBadgeClass(change.impact_level)">
              {{ change.impact_level }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { PlatformChange } from '../../services/releaseService'

const props = defineProps<{
  changes: PlatformChange[]
}>()

const selectedCategory = ref('')
const selectedImpact = ref('')

const filteredChanges = computed(() => {
  return props.changes.filter(c => {
    if (selectedCategory.value && c.category !== selectedCategory.value) return false
    if (selectedImpact.value && c.impact_level !== selectedImpact.value) return false
    return true
  })
})

const categoryBadgeClass = (category: string) => {
  switch (category) {
    case 'migration':
      return 'bg-amber-50 text-amber-700 border border-amber-200'
    case 'backend_code':
      return 'bg-blue-50 text-blue-700 border border-blue-200'
    case 'rbac':
      return 'bg-purple-50 text-purple-700 border border-purple-200'
    case 'remediation_action':
      return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
    default:
      return 'bg-neutral-100 text-neutral-700 border border-neutral-200'
  }
}

const impactBadgeClass = (impact: string) => {
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
