<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { systemsControlPlaneService } from '@/services/admin/systemsControlPlane'
import type { PlatformService } from '@/types/systems'
import SystemStatusBadge from '@/components/admin/systems/SystemStatusBadge.vue'
import SystemHealthSummary from '@/components/admin/systems/SystemHealthSummary.vue'
import SystemLastChecked from '@/components/admin/systems/SystemLastChecked.vue'
import { RefreshCw, ArrowLeft, ChevronRight } from 'lucide-vue-next'

const route = useRoute()
const loading = ref(false)
const error = ref<string | null>(null)
const service = ref<PlatformService | null>(null)

const serviceId = computed(() => String(route.params.serviceId || ''))

const load = async (refresh = false) => {
  if (!serviceId.value) return
  loading.value = true
  error.value = null
  try {
    service.value = await systemsControlPlaneService.getPlatformService(serviceId.value, refresh)
  } catch (e: any) {
    error.value = e?.response?.data?.message || e?.message || 'Failed to load platform service'
    service.value = null
  } finally {
    loading.value = false
  }
}

watch(serviceId, () => load(false))
onMounted(() => load(false))
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <router-link to="/admin/systems" class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-neutral-muted hover:text-primary mb-2">
          <ArrowLeft class="h-3.5 w-3.5" /> Systems
        </router-link>
        <h1 class="text-2xl font-display font-bold text-neutral-black">{{ service?.name || 'Platform Service' }}</h1>
        <p class="text-sm text-neutral-muted mt-1">{{ service?.description }}</p>
      </div>
      <div class="flex items-center gap-2">
        <SystemStatusBadge v-if="service" :status="service.status" />
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory px-3 py-2 text-sm font-semibold hover:bg-neutral-background"
          :disabled="loading"
          @click="load(true)"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>
      </div>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ error }}</div>

    <template v-else-if="service">
      <SystemLastChecked :checked-at="service.last_checked_at" />

      <section class="rounded-2xl border border-neutral-ivory bg-white p-5 space-y-3">
        <h2 class="text-sm font-bold text-neutral-black">Health</h2>
        <SystemHealthSummary
          :status-reason="service.status_reason || service.message"
          :checks="service.checks"
          :errors="service.errors"
        />
      </section>

      <section v-if="service.metrics && Object.keys(service.metrics).length" class="rounded-2xl border border-neutral-ivory bg-white p-5">
        <h2 class="text-sm font-bold text-neutral-black mb-3">Metrics</h2>
        <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 text-sm">
          <div v-for="(value, key) in service.metrics" :key="String(key)">
            <dt class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">{{ key }}</dt>
            <dd class="font-semibold text-neutral-black mt-1">{{ value ?? '—' }}</dd>
          </div>
        </dl>
      </section>

      <section v-if="service.partitions?.length" class="rounded-2xl border border-neutral-ivory bg-white p-5">
        <h2 class="text-sm font-bold text-neutral-black mb-3">Queue partitions</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead>
              <tr class="text-[10px] font-black uppercase tracking-widest text-neutral-muted border-b border-neutral-ivory">
                <th class="py-2 pr-3">Queue</th>
                <th class="py-2 pr-3">Pending</th>
                <th class="py-2 pr-3">Active</th>
                <th class="py-2 pr-3">Failed</th>
                <th class="py-2">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in service.partitions" :key="p.name" class="border-b border-neutral-ivory/70">
                <td class="py-2 pr-3 font-semibold">{{ p.name }}</td>
                <td class="py-2 pr-3">{{ p.pending }}</td>
                <td class="py-2 pr-3">{{ p.active }}</td>
                <td class="py-2 pr-3">{{ p.failed }}</td>
                <td class="py-2 capitalize">{{ p.status }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="mt-3 text-xs text-neutral-muted">Worker process liveness is unknown without a process inspector. Counts come from existing job tables.</p>
      </section>

      <router-link
        v-if="service.admin_path"
        :to="service.admin_path"
        class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline"
      >
        Open queue administration
        <ChevronRight class="h-4 w-4" />
      </router-link>
    </template>
  </div>
</template>
