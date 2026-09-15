<template>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-bold text-slate-900">Incident Timeline Reconstruction</h3>
        <p class="text-xs text-slate-500">Chronological lifecycle trace with explicit relationship mapping</p>
      </div>
    </div>

    <!-- Alert Identifier Input / Selector -->
    <div class="flex items-center gap-2 mb-6">
      <input
        v-model="inputIdentifier"
        type="text"
        placeholder="Enter Alert UUID or ID..."
        class="text-xs px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 flex-1 max-w-sm"
        @keyup.enter="fetchTimeline"
      />
      <button
        @click="fetchTimeline"
        :disabled="loading || !inputIdentifier"
        class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-semibold text-xs rounded-lg transition"
      >
        {{ loading ? 'Loading...' : 'Reconstruct Timeline' }}
      </button>
    </div>

    <!-- Timeline Results -->
    <div v-if="error" class="p-3 bg-rose-50 border border-rose-200 rounded-lg text-xs text-rose-700">
      {{ error }}
    </div>

    <div v-else-if="timelineData && timelineData.alert" class="space-y-4">
      <!-- Target Alert Header -->
      <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between flex-wrap gap-2 text-xs">
        <div>
          <span class="font-bold text-slate-900">{{ timelineData.alert.title }}</span>
          <span class="text-slate-500 ml-2 font-mono">({{ timelineData.alert.rule_key }})</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="severityClass(timelineData.alert.severity)">
            {{ timelineData.alert.severity }}
          </span>
          <span class="px-2 py-0.5 bg-slate-200 text-slate-800 rounded text-[10px] font-semibold uppercase">
            {{ timelineData.alert.status }}
          </span>
        </div>
      </div>

      <!-- Chronological Timeline Nodes -->
      <div class="relative pl-6 border-l-2 border-slate-200 space-y-6 my-4">
        <div
          v-for="node in timelineData.nodes"
          :key="node.id"
          class="relative group"
        >
          <!-- Timeline Node Point -->
          <div
            class="absolute -left-[31px] top-1.5 w-3.5 h-3.5 rounded-full border-2 border-white shadow-sm"
            :class="nodePointColor(node.severity)"
          ></div>

          <div class="p-3 bg-slate-50 rounded-lg border border-slate-100 hover:border-slate-300 transition text-xs">
            <div class="flex items-center justify-between flex-wrap gap-2 mb-1">
              <div class="flex items-center gap-2">
                <span class="font-bold text-slate-800">{{ node.title }}</span>
                <span class="px-1.5 py-0.5 bg-slate-200 text-slate-700 rounded text-[9px] font-semibold uppercase">
                  {{ node.relationship_label }}
                </span>
              </div>
              <span class="text-[11px] text-slate-400 font-medium">{{ formatDate(node.timestamp) }}</span>
            </div>
            <p class="text-slate-600 font-medium mb-1">{{ node.summary }}</p>
            <div class="text-[10px] text-slate-400 font-semibold">
              Actor: <span class="text-slate-600">{{ node.actor }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="!loading" class="text-center py-8 text-slate-400 text-xs">
      Enter an Operational Alert UUID or ID above to reconstruct its incident timeline.
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { governanceService, type IncidentTimelineData } from '@/services/governanceService';

const inputIdentifier = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const timelineData = ref<IncidentTimelineData | null>(null);

async function fetchTimeline() {
  if (!inputIdentifier.value.trim()) return;
  loading.value = true;
  error.value = null;
  timelineData.value = null;

  try {
    timelineData.value = await governanceService.getIncidentTimeline(inputIdentifier.value.trim());
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load incident timeline.';
  } finally {
    loading.value = false;
  }
}

function severityClass(severity: string): string {
  switch (severity) {
    case 'critical':
      return 'bg-rose-100 text-rose-800';
    case 'high':
      return 'bg-orange-100 text-orange-800';
    case 'medium':
      return 'bg-amber-100 text-amber-800';
    default:
      return 'bg-blue-100 text-blue-800';
  }
}

function nodePointColor(severity: string): string {
  switch (severity) {
    case 'critical':
      return 'bg-rose-500';
    case 'warning':
      return 'bg-amber-500';
    default:
      return 'bg-emerald-500';
  }
}

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  } catch {
    return iso;
  }
}
</script>
