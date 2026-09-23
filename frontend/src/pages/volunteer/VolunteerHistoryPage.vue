<template>
  <div class="volunteer-history-page min-h-screen bg-slate-900 text-slate-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto space-y-8">
      
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <router-link to="/volunteer" class="text-xs font-semibold text-emerald-400 hover:underline mb-1 inline-block">
            &larr; Back to Opportunities
          </router-link>
          <h1 class="text-3xl font-extrabold text-slate-100 tracking-tight">
            My Volunteer Activity
          </h1>
          <p class="text-slate-400 text-sm mt-1">Track your past signups, shifts, and volunteer contributions.</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-4">
        <div v-for="i in 3" :key="i" class="h-28 rounded-2xl bg-slate-800/60 animate-pulse"></div>
      </div>

      <!-- Content -->
      <template v-else>
        <!-- KPI Metrics Header -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl p-5 backdrop-blur-md">
            <div class="text-slate-400 text-xs font-medium uppercase tracking-wider">Total Signups</div>
            <div class="text-3xl font-black text-slate-100 mt-1">{{ historyData?.total_signups || 0 }}</div>
          </div>
          <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl p-5 backdrop-blur-md">
            <div class="text-slate-400 text-xs font-medium uppercase tracking-wider">Active Assignments</div>
            <div class="text-3xl font-black text-emerald-400 mt-1">{{ historyData?.active_count || 0 }}</div>
          </div>
          <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl p-5 backdrop-blur-md">
            <div class="text-slate-400 text-xs font-medium uppercase tracking-wider">Completed Hours / Service</div>
            <div class="text-3xl font-black text-sky-400 mt-1">{{ historyData?.completed_count || 0 }}</div>
          </div>
        </div>

        <!-- Signups List -->
        <div v-if="!historyData?.signups || historyData.signups.length === 0" class="text-center py-16 bg-slate-800/40 rounded-3xl border border-slate-800">
          <svg class="w-12 h-12 mx-auto text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
          <h3 class="text-lg font-semibold text-slate-300">No Volunteer History Recorded</h3>
          <p class="text-slate-500 text-sm mt-1">Sign up for upcoming volunteer opportunities to start building your profile.</p>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="item in historyData.signups"
            :key="item.id"
            class="bg-slate-800/80 border border-slate-700/60 rounded-2xl p-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 transition hover:border-slate-600"
          >
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="font-bold text-slate-100 text-lg">
                  {{ item.opportunity?.title || 'Volunteer Opportunity' }}
                </span>
                <span
                  :class="[
                    'px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider',
                    item.status === 'completed' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' :
                    item.status === 'confirmed' ? 'bg-sky-500/20 text-sky-400 border border-sky-500/30' :
                    item.status === 'cancelled' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' :
                    'bg-amber-500/20 text-amber-400 border border-amber-500/30'
                  ]"
                >
                  {{ item.status.replace('_', ' ') }}
                </span>
              </div>

              <div class="text-xs text-slate-400 flex flex-wrap gap-x-4 gap-y-1">
                <span v-if="item.team">Team: <strong class="text-slate-300">{{ item.team.name }}</strong></span>
                <span v-if="item.shift">Shift: <strong class="text-slate-300">{{ item.shift.name || formatDate(item.shift.start_at) }}</strong></span>
                <span>Registered: {{ formatDate(item.created_at) }}</span>
              </div>
            </div>

            <button
              v-if="['signed_up', 'confirmed'].includes(item.status)"
              @click="cancelRegistration(item.uuid)"
              class="px-3 py-1.5 text-xs font-medium text-rose-400 bg-rose-950/30 border border-rose-800/50 hover:bg-rose-900/50 rounded-lg transition"
            >
              Cancel Signup
            </button>
          </div>
        </div>
      </template>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { volunteeringService, type VolunteerSignup } from '../../services/volunteeringService';

const historyData = ref<{
  total_signups: number;
  completed_count: number;
  active_count: number;
  signups: VolunteerSignup[];
} | null>(null);

const loading = ref(true);

const fetchHistory = async () => {
  loading.value = true;
  try {
    const res = await volunteeringService.getMyHistory();
    historyData.value = res.data;
  } catch (err) {
    console.error('Failed to load volunteer history:', err);
  } finally {
    loading.value = false;
  }
};

const cancelRegistration = async (uuid: string) => {
  if (!confirm('Are you sure you want to cancel your volunteer registration?')) return;
  try {
    await volunteeringService.cancelSignup(uuid);
    await fetchHistory();
  } catch (err) {
    alert('Failed to cancel registration.');
  }
};

const formatDate = (dateStr?: string | null) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

onMounted(() => {
  fetchHistory();
});
</script>
