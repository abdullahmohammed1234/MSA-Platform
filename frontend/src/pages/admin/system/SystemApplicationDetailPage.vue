<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { systemsControlPlaneService } from '@/services/admin/systemsControlPlane'
import type { SystemApplication } from '@/types/systems'
import SystemStatusBadge from '@/components/admin/systems/SystemStatusBadge.vue'
import SystemHealthSummary from '@/components/admin/systems/SystemHealthSummary.vue'
import SystemDependencyList from '@/components/admin/systems/SystemDependencyList.vue'
import SystemLastChecked from '@/components/admin/systems/SystemLastChecked.vue'
import { ExternalLink, RefreshCw, ArrowLeft, Wrench } from 'lucide-vue-next'

const route = useRoute()
const loading = ref(false)
const error = ref<string | null>(null)
const app = ref<SystemApplication | null>(null)

const systemId = computed(() => String(route.meta.systemId || route.params.systemId || ''))

const load = async (refresh = false) => {
  if (!systemId.value) {
    error.value = 'Missing system id'
    return
  }
  loading.value = true
  error.value = null
  try {
    app.value = await systemsControlPlaneService.getApplication(systemId.value, refresh)
  } catch (e: any) {
    error.value = e?.response?.data?.message || e?.message || 'Failed to load application'
    app.value = null
  } finally {
    loading.value = false
  }
}

watch(systemId, () => load(false))
onMounted(() => load(false))
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <router-link to="/admin/systems" class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-neutral-muted hover:text-primary mb-2">
          <ArrowLeft class="h-3.5 w-3.5" /> Systems
        </router-link>
        <h1 class="text-2xl font-display font-bold text-neutral-black">{{ app?.name || 'Application' }}</h1>
        <p class="text-sm text-neutral-muted mt-1 max-w-2xl">{{ app?.description }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <SystemStatusBadge v-if="app" :status="app.status" />
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory px-3 py-2 text-sm font-semibold hover:bg-neutral-background"
          :disabled="loading"
          @click="load(true)"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>
        <router-link
          v-if="app?.url"
          :to="app.url"
          class="inline-flex items-center gap-2 rounded-xl bg-primary px-3 py-2 text-sm font-semibold text-white hover:opacity-90"
        >
          Open application
          <ExternalLink class="h-4 w-4" />
        </router-link>
      </div>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ error }}</div>
    <div v-else-if="loading && !app" class="text-sm text-neutral-muted">Loading…</div>

    <template v-else-if="app">
      <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-neutral-ivory bg-white p-4">
          <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Operational status</p>
          <div class="mt-2"><SystemStatusBadge :status="app.status" /></div>
        </div>
        <div class="rounded-2xl border border-neutral-ivory bg-white p-4">
          <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Version</p>
          <p class="mt-2 text-lg font-semibold">{{ app.version || 'unknown' }}</p>
        </div>
        <div class="rounded-2xl border border-neutral-ivory bg-white p-4">
          <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">URL</p>
          <p class="mt-2 text-sm font-semibold text-primary break-all">{{ app.url || '—' }}</p>
        </div>
        <div class="rounded-2xl border border-neutral-ivory bg-white p-4">
          <SystemLastChecked :checked-at="app.last_checked_at" />
        </div>
      </div>

      <section class="rounded-2xl border border-neutral-ivory bg-white p-5 space-y-3">
        <h2 class="text-sm font-bold text-neutral-black">Health</h2>
        <SystemHealthSummary
          :status-reason="app.status_reason"
          :checks="app.checks"
          :errors="app.errors"
        />
      </section>

      <section v-if="app.dependency_details?.length" class="rounded-2xl border border-neutral-ivory bg-white p-5 space-y-3">
        <h2 class="text-sm font-bold text-neutral-black">Dependencies</h2>
        <SystemDependencyList :dependencies="app.dependency_details" />
      </section>

      <section class="rounded-2xl border border-neutral-ivory bg-white p-5">
        <h2 class="text-sm font-bold text-neutral-black mb-3">Ownership (registry)</h2>
        <div class="grid gap-4 sm:grid-cols-2 text-sm">
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted mb-2">Owns</p>
            <ul class="flex flex-wrap gap-1.5">
              <li v-for="item in app.owns || []" :key="item" class="rounded-full bg-neutral-background px-2.5 py-1 text-xs font-semibold">{{ item }}</li>
            </ul>
          </div>
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted mb-2">Does not own</p>
            <ul class="flex flex-wrap gap-1.5">
              <li v-for="item in app.does_not_own || []" :key="item" class="rounded-full bg-neutral-background px-2.5 py-1 text-xs font-semibold">{{ item }}</li>
            </ul>
          </div>
        </div>
        <p v-if="app.notes" class="mt-3 text-xs text-neutral-muted">{{ app.notes }}</p>
      </section>

      <router-link
        v-if="app.console_path"
        :to="app.console_path"
        class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
      >
        <Wrench class="h-4 w-4" />
        Open operations console
      </router-link>
    </template>
  </div>
</template>
