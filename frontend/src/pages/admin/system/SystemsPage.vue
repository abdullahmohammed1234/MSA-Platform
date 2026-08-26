<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { systemsControlPlaneService } from '@/services/admin/systemsControlPlane'
import type { SystemsOverview, SystemStatus } from '@/types/systems'
import SystemCard from '@/components/admin/systems/SystemCard.vue'
import PlatformServiceCard from '@/components/admin/systems/PlatformServiceCard.vue'
import SecurityCenterCard from '@/components/admin/systems/SecurityCenterCard.vue'
import SystemStatusBadge from '@/components/admin/systems/SystemStatusBadge.vue'
import { RefreshCw } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const loading = ref(false)
const error = ref<string | null>(null)
const data = ref<SystemsOverview | null>(null)
const filter = ref<'all' | 'applications' | 'platform' | 'security' | SystemStatus>('all')

const isSuper = computed(() =>
  typeof authStore.isPrivilegedAdmin === 'boolean'
    ? authStore.isPrivilegedAdmin
    : authStore.roles.includes('admin') || authStore.roles.includes('super-admin')
)

const canViewQueues = computed(
  () => isSuper.value || authStore.permissions.includes('view_queue_status')
)
const canViewSecurity = computed(
  () => isSuper.value || authStore.permissions.includes('view_security')
)

const visiblePlatformServices = computed(() => {
  const services = data.value?.platform_services ?? []
  return services.filter((s) => {
    if (s.id === 'queues' && !canViewQueues.value) return false
    return true
  })
})

const showSecurity = computed(() => canViewSecurity.value && !!data.value?.security)

const filteredApps = computed(() => {
  const apps = data.value?.applications ?? []
  if (filter.value === 'all' || filter.value === 'applications') return apps
  if (['operational', 'degraded', 'unavailable', 'unknown'].includes(filter.value)) {
    return apps.filter((a) => a.status === filter.value)
  }
  return apps
})

const showAppsSection = computed(
  () => filter.value === 'all' || filter.value === 'applications' || ['operational', 'degraded', 'unavailable', 'unknown'].includes(filter.value)
)
const showPlatformSection = computed(() => filter.value === 'all' || filter.value === 'platform')
const showSecuritySection = computed(() => filter.value === 'all' || filter.value === 'security')

const load = async (refresh = false) => {
  loading.value = true
  error.value = null
  try {
    data.value = await systemsControlPlaneService.getOverview(refresh)
  } catch (e: any) {
    error.value = e?.response?.data?.message || e?.message || 'Failed to load Systems overview'
  } finally {
    loading.value = false
  }
}

onMounted(() => load(false))
</script>

<template>
  <div class="space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-neutral-black">Systems</h1>
        <p class="text-sm text-neutral-muted mt-1 max-w-2xl">
          Operational control plane for the SFU MSA platform. Visibility and navigation only —
          application configuration stays inside each application.
        </p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory px-3 py-2 text-sm font-semibold hover:bg-neutral-background self-start"
        :disabled="loading"
        @click="load(true)"
      >
        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
        Refresh
      </button>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      {{ error }}
    </div>

    <template v-if="data">
      <!-- Summary -->
      <section class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
          <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Applications</p>
          <p class="mt-2 text-2xl font-bold text-neutral-black">{{ data.summary.applications_total }}</p>
          <p class="mt-1 text-xs text-neutral-muted">
            {{ data.summary.applications_by_status.operational || 0 }} operational ·
            {{ data.summary.applications_by_status.degraded || 0 }} degraded ·
            {{ data.summary.applications_by_status.unavailable || 0 }} unavailable ·
            {{ data.summary.applications_by_status.unknown || 0 }} unknown
          </p>
        </div>
        <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
          <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Platform services</p>
          <p class="mt-2 text-2xl font-bold text-neutral-black">{{ data.summary.platform_services_total }}</p>
          <p class="mt-1 text-xs text-neutral-muted">
            {{ data.summary.platform_services_by_status.operational || 0 }} operational ·
            {{ data.summary.platform_services_by_status.degraded || 0 }} degraded ·
            {{ data.summary.platform_services_by_status.unavailable || 0 }} unavailable ·
            {{ data.summary.platform_services_by_status.unknown || 0 }} unknown
          </p>
        </div>
        <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
          <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Security</p>
          <div class="mt-2">
            <SystemStatusBadge :status="data.summary.security_status" />
          </div>
          <p class="mt-2 text-xs text-neutral-muted">
            {{ data.security?.status_reason || 'Open Security Center for posture details.' }}
          </p>
          <p class="mt-2 text-xs text-neutral-muted">
            Platform {{ data.platform.version }}
            <span v-if="data.platform.commit_sha"> · {{ data.platform.commit_sha }}</span>
          </p>
        </div>
      </section>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="f in [
            { id: 'all', label: 'All' },
            { id: 'applications', label: 'Applications' },
            { id: 'platform', label: 'Platform Services' },
            { id: 'security', label: 'Security' },
            { id: 'operational', label: 'Operational' },
            { id: 'degraded', label: 'Degraded' },
            { id: 'unavailable', label: 'Unavailable' },
          ]"
          :key="f.id"
          type="button"
          class="rounded-full px-3 py-1.5 text-xs font-bold border transition"
          :class="filter === f.id ? 'bg-primary text-white border-primary' : 'bg-white text-neutral-black border-neutral-ivory hover:bg-neutral-background'"
          @click="filter = f.id as any"
        >
          {{ f.label }}
        </button>
      </div>

      <p class="text-xs text-neutral-muted">
        Last checked {{ new Date(data.checked_at).toLocaleString() }}
        · Status is polled and cached server-side (not a live heartbeat)
      </p>

      <!-- Applications -->
      <section v-if="showAppsSection" class="space-y-4">
        <h2 class="text-sm font-black uppercase tracking-widest text-neutral-muted">Applications</h2>
        <div class="grid gap-4 lg:grid-cols-2">
          <SystemCard v-for="app in filteredApps" :key="app.id" :app="app" />
        </div>
        <p v-if="filteredApps.length === 0" class="text-sm text-neutral-muted">No applications match this filter.</p>
      </section>

      <!-- Platform services -->
      <section v-if="showPlatformSection" class="space-y-4">
        <h2 class="text-sm font-black uppercase tracking-widest text-neutral-muted">Platform Services</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <PlatformServiceCard
            v-for="service in visiblePlatformServices"
            :key="service.id"
            :service="service"
          />
        </div>
      </section>

      <!-- Security -->
      <section v-if="showSecuritySection && showSecurity" class="space-y-4">
        <h2 class="text-sm font-black uppercase tracking-widest text-neutral-muted">Security</h2>
        <div class="max-w-xl">
          <SecurityCenterCard :security="data.security" />
        </div>
      </section>
    </template>

    <div v-else-if="loading" class="text-sm text-neutral-muted">Loading systems…</div>
  </div>
</template>
