<template>
  <div class="space-y-6 pb-12">
    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft">
      <div>
        <h1 class="text-2xl sm:text-3xl font-display font-medium text-primary tracking-wide">Platform Lifecycle & Environment Center</h1>
        <p class="text-sm text-neutral-muted mt-1">
          Centralized control plane for deployment readiness, framework versions, dependency health, migrations & configuration drift
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          @click="fetchLifecycleData"
          :disabled="loading"
          class="px-4 py-2 text-xs font-semibold rounded-xl bg-primary hover:bg-primary-hover text-white shadow-soft disabled:opacity-50 flex items-center gap-2 transition cursor-pointer"
        >
          <span v-if="loading" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white/20 border-t-white rounded-full"></span>
          <span>{{ loading ? 'Refreshing...' : 'Refresh Diagnostics' }}</span>
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm flex items-center justify-between shadow-soft">
      <div>
        <strong class="font-bold">Error loading lifecycle telemetry:</strong> {{ error }}
      </div>
      <button @click="fetchLifecycleData" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shadow-soft cursor-pointer">Retry</button>
    </div>

    <!-- Loading State -->
    <div v-if="loading && !lifecycle" class="space-y-6">
      <div class="bg-white border border-neutral-ivory rounded-2xl p-8 text-center animate-pulse text-neutral-muted shadow-soft">
        Loading platform lifecycle & environment telemetry...
      </div>
    </div>

    <!-- Main Content Grid -->
    <div v-if="lifecycle" class="space-y-6">
      <!-- Layer 1: Deployment Readiness -->
      <DeploymentReadinessCard :readiness="lifecycle.readiness" />

      <!-- Layer 2: Environment & Release Inventory -->
      <EnvironmentInventoryCard :environment="lifecycle.environment" :release="lifecycle.release" />

      <!-- Layer 3: Dependency Health Matrix -->
      <DependencyHealthPanel :dependencies="lifecycle.dependencies" />

      <!-- Layer 4: Configuration Drift Diagnostics -->
      <ConfigurationDriftPanel :drift="lifecycle.drift" />

      <!-- Layer 5: Migrations & Scheduler/Queue Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <MigrationReadinessPanel :migrations="lifecycle.migrations" />
        <SchedulerQueuePanel :scheduler-queue="lifecycle.scheduler_queue" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { lifecycleService, type LifecycleOverviewData } from '../../../services/lifecycleService'
import DeploymentReadinessCard from '../../../components/lifecycle/DeploymentReadinessCard.vue'
import EnvironmentInventoryCard from '../../../components/lifecycle/EnvironmentInventoryCard.vue'
import DependencyHealthPanel from '../../../components/lifecycle/DependencyHealthPanel.vue'
import MigrationReadinessPanel from '../../../components/lifecycle/MigrationReadinessPanel.vue'
import SchedulerQueuePanel from '../../../components/lifecycle/SchedulerQueuePanel.vue'
import ConfigurationDriftPanel from '../../../components/lifecycle/ConfigurationDriftPanel.vue'

const lifecycle = ref<LifecycleOverviewData | null>(null)
const loading = ref<boolean>(false)
const error = ref<string | null>(null)

async function fetchLifecycleData() {
  loading.value = true
  error.value = null
  try {
    lifecycle.value = await lifecycleService.getLifecycleOverview()
  } catch (err: any) {
    error.value = err?.response?.data?.message || err?.message || 'Failed to fetch lifecycle telemetry'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchLifecycleData()
})
</script>
