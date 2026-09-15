<template>
  <div class="space-y-6 pb-12">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Platform Change Management & Release Intelligence</h1>
        <p class="text-sm text-neutral-muted mt-1">
          Centralized control plane for release state, change impact analysis, post-deployment verification & rollback readiness
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          @click="openCompare"
          class="px-4 py-2 text-sm font-semibold rounded-xl border border-neutral-ivory bg-white hover:bg-neutral-background text-neutral-black shadow-soft transition disabled:opacity-60 cursor-pointer flex items-center gap-2"
        >
          Compare Releases
        </button>
        <button
          @click="fetchData"
          :disabled="loading"
          class="px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-soft transition disabled:opacity-60 cursor-pointer flex items-center gap-2"
        >
          <span v-if="loading" class="animate-spin inline-block w-3.5 h-3.5 border-2 border-white/20 border-t-white rounded-full"></span>
          <span>{{ loading ? 'Refreshing...' : 'Refresh Telemetry' }}</span>
        </button>
      </div>
    </div>

    <!-- Error Banner -->
    <div v-if="error" class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex items-center justify-between">
      <div>
        <strong class="font-bold">Error loading release telemetry:</strong> {{ error }}
      </div>
      <button @click="fetchData" class="px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg cursor-pointer">Retry</button>
    </div>

    <!-- Loading State -->
    <div v-if="loading && !currentRelease" class="bg-white border border-neutral-ivory rounded-2xl p-8 text-center animate-pulse text-neutral-muted shadow-soft">
      Loading release telemetry & change impact matrix...
    </div>

    <!-- Main Content -->
    <div v-if="currentRelease" class="space-y-6">
      <!-- Active Release Card -->
      <CurrentReleasePanel
        :release="currentRelease"
        @verify="runVerification"
        @open-approval="showApprovalModal = true"
      />

      <!-- Tab Navigation -->
      <div class="flex border-b border-neutral-ivory/60 gap-6 text-sm font-medium">
        <button
          @click="activeTab = 'changes'"
          class="pb-3 border-b-2 transition-colors cursor-pointer"
          :class="activeTab === 'changes' ? 'border-primary text-primary font-semibold' : 'border-transparent text-neutral-muted hover:text-primary'"
        >
          Changes & Impact Matrix
        </button>
        <button
          @click="activeTab = 'verification'"
          class="pb-3 border-b-2 transition-colors cursor-pointer"
          :class="activeTab === 'verification' ? 'border-primary text-primary font-semibold' : 'border-transparent text-neutral-muted hover:text-primary'"
        >
          Post-Release Verification Probes
        </button>
        <button
          @click="activeTab = 'timeline'"
          class="pb-3 border-b-2 transition-colors cursor-pointer"
          :class="activeTab === 'timeline' ? 'border-primary text-primary font-semibold' : 'border-transparent text-neutral-muted hover:text-primary'"
        >
          Timeline & Incident Correlation
        </button>
        <button
          @click="activeTab = 'rollback'"
          class="pb-3 border-b-2 transition-colors cursor-pointer"
          :class="activeTab === 'rollback' ? 'border-primary text-primary font-semibold' : 'border-transparent text-neutral-muted hover:text-primary'"
        >
          Rollback Readiness & Recovery
        </button>
      </div>

      <!-- Tab 1: Changes & Impact -->
      <div v-if="activeTab === 'changes'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
          <ReleaseChangesPanel :changes="changesList" />
        </div>
        <div>
          <ChangeImpactPanel :impact="impactData" />
        </div>
      </div>

      <!-- Tab 2: Verification Probes -->
      <div v-if="activeTab === 'verification'">
        <PostReleaseVerificationPanel
          :verification="verificationData"
          @run-verify="runVerification"
        />
      </div>

      <!-- Tab 3: Timeline & Correlation -->
      <div v-if="activeTab === 'timeline'">
        <ReleaseTimelinePanel :timeline="timelineData" />
      </div>

      <!-- Tab 4: Rollback Readiness -->
      <div v-if="activeTab === 'rollback'">
        <RollbackReadinessPanel :rollback="rollbackData" />
      </div>
    </div>

    <!-- Modals -->
    <ReleaseApprovalModal
      :is-open="showApprovalModal"
      :release-identifier="currentRelease?.release_identifier || ''"
      :error-message="approvalError"
      @close="showApprovalModal = false"
      @approve="handleApprove"
    />

    <ReleaseComparisonModal
      :is-open="showComparisonModal"
      :comparison="comparisonData"
      @close="showComparisonModal = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  releaseService,
  type PlatformRelease,
  type PlatformChange,
  type ImpactAnalysis,
  type VerificationResult,
  type RollbackReadiness,
  type TimelineEvent,
  type ComparisonResult
} from '../../../services/releaseService'

import CurrentReleasePanel from '../../../components/release/CurrentReleasePanel.vue'
import ReleaseChangesPanel from '../../../components/release/ReleaseChangesPanel.vue'
import ChangeImpactPanel from '../../../components/release/ChangeImpactPanel.vue'
import PostReleaseVerificationPanel from '../../../components/release/PostReleaseVerificationPanel.vue'
import ReleaseTimelinePanel from '../../../components/release/ReleaseTimelinePanel.vue'
import RollbackReadinessPanel from '../../../components/release/RollbackReadinessPanel.vue'
import ReleaseApprovalModal from '../../../components/release/ReleaseApprovalModal.vue'
import ReleaseComparisonModal from '../../../components/release/ReleaseComparisonModal.vue'

const currentRelease = ref<Partial<PlatformRelease> | null>(null)
const changesList = ref<PlatformChange[]>([])
const impactData = ref<Partial<ImpactAnalysis>>({})
const verificationData = ref<Partial<VerificationResult>>({})
const rollbackData = ref<Partial<RollbackReadiness>>({})
const timelineData = ref<TimelineEvent[]>([])
const comparisonData = ref<ComparisonResult | null>(null)

const activeTab = ref<'changes' | 'verification' | 'timeline' | 'rollback'>('changes')
const loading = ref<boolean>(false)
const error = ref<string | null>(null)

const showApprovalModal = ref<boolean>(false)
const showComparisonModal = ref<boolean>(false)
const approvalError = ref<string>('')

const fetchData = async () => {
  loading.value = true
  error.value = null

  try {
    const active = await releaseService.getCurrentRelease()
    currentRelease.value = active

    const identifier = active.release_identifier || active.id

    const [changesRes, impactRes, verifyRes, rollbackRes, timelineRes] = await Promise.allSettled([
      releaseService.getChanges(),
      releaseService.getReleaseImpact(identifier),
      releaseService.getReleaseVerification(identifier),
      releaseService.getReleaseRollback(identifier),
      releaseService.getReleaseTimeline(identifier)
    ])

    if (changesRes.status === 'fulfilled') {
      changesList.value = changesRes.value.data || []
    }
    if (impactRes.status === 'fulfilled') {
      impactData.value = impactRes.value
    }
    if (verifyRes.status === 'fulfilled') {
      verificationData.value = verifyRes.value
    }
    if (rollbackRes.status === 'fulfilled') {
      rollbackData.value = rollbackRes.value
    }
    if (timelineRes.status === 'fulfilled') {
      timelineData.value = timelineRes.value.timeline || []
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || err.message || 'Failed to connect to Release Registry'
  } finally {
    loading.value = false
  }
}

const runVerification = async () => {
  if (!currentRelease.value) return
  const identifier = currentRelease.value.release_identifier || currentRelease.value.id
  try {
    const result = await releaseService.runReleaseVerification(String(identifier))
    verificationData.value = result
    if (currentRelease.value) {
      currentRelease.value.post_release_verification_status = result.verification_status
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Verification failed')
  }
}

const handleApprove = async (notes: string) => {
  if (!currentRelease.value) return
  approvalError.value = ''
  const identifier = currentRelease.value.release_identifier || currentRelease.value.id
  try {
    const res = await releaseService.approveRelease(String(identifier), notes)
    if (res.success) {
      showApprovalModal.value = false
      await fetchData()
    }
  } catch (err: any) {
    approvalError.value = err.response?.data?.message || 'Approval failed'
  }
}

const openCompare = async () => {
  try {
    const releasesRes = await releaseService.getReleases({ per_page: 5 })
    const items = releasesRes.data || []
    if (items.length >= 2) {
      const comp = await releaseService.compareReleases(items[0].id, items[1].id)
      comparisonData.value = comp
      showComparisonModal.value = true
    } else {
      alert('Need at least 2 registered releases to compare side-by-side.')
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Comparison failed')
  }
}

onMounted(() => {
  fetchData()
})
</script>
