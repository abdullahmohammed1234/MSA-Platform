<template>
  <div class="admin-volunteer-opportunities space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-100">Volunteer Opportunities Administration</h1>
        <p class="text-sm text-slate-400 mt-1">Manage platform-wide volunteer positions, teams, shifts, and rosters.</p>
      </div>
      <button
        @click="showCreateModal = true"
        class="inline-flex items-center px-4 py-2.5 rounded-xl font-semibold text-sm text-slate-900 bg-emerald-400 hover:bg-emerald-300 transition shadow-lg shadow-emerald-950/20"
      >
        + Create Opportunity
      </button>
    </div>

    <!-- Analytics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4" v-if="analytics">
      <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl p-4">
        <div class="text-slate-400 text-xs font-medium uppercase">Total Positions</div>
        <div class="text-2xl font-black text-slate-100 mt-1">{{ analytics.total_opportunities }}</div>
      </div>
      <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl p-4">
        <div class="text-slate-400 text-xs font-medium uppercase">Open & Active</div>
        <div class="text-2xl font-black text-emerald-400 mt-1">{{ analytics.open_opportunities }}</div>
      </div>
      <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl p-4">
        <div class="text-slate-400 text-xs font-medium uppercase">Active Signups</div>
        <div class="text-2xl font-black text-sky-400 mt-1">{{ analytics.active_signups }}</div>
      </div>
      <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl p-4">
        <div class="text-slate-400 text-xs font-medium uppercase">Completed Assignments</div>
        <div class="text-2xl font-black text-indigo-400 mt-1">{{ analytics.completed_signups }}</div>
      </div>
    </div>

    <!-- Opportunities Table -->
    <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl overflow-hidden shadow-xl">
      <table class="w-full text-left text-sm text-slate-300">
        <thead class="bg-slate-900/60 text-slate-400 text-xs uppercase tracking-wider border-b border-slate-700/60">
          <tr>
            <th class="px-6 py-4">Title</th>
            <th class="px-6 py-4">Linked Event</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4">Signups</th>
            <th class="px-6 py-4">Created Date</th>
            <th class="px-6 py-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-700/40">
          <tr v-if="loading">
            <td colspan="6" class="px-6 py-8 text-center text-slate-500">Loading volunteer positions...</td>
          </tr>
          <tr v-else-if="opportunities.length === 0">
            <td colspan="6" class="px-6 py-8 text-center text-slate-500">No volunteer opportunities found.</td>
          </tr>
          <tr v-for="opp in opportunities" :key="opp.id" class="hover:bg-slate-700/30 transition">
            <td class="px-6 py-4 font-semibold text-slate-100">
              <router-link :to="`/admin/volunteering/${opp.id}`" class="hover:text-emerald-400 transition">
                {{ opp.title }}
              </router-link>
            </td>
            <td class="px-6 py-4">
              <span v-if="opp.event" class="px-2.5 py-0.5 rounded-md text-xs font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-800/50">
                {{ opp.event.name }}
              </span>
              <span v-else class="text-slate-500 text-xs">Standalone</span>
            </td>
            <td class="px-6 py-4">
              <span
                :class="[
                  'px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase',
                  opp.status === 'open' ? 'bg-emerald-500/20 text-emerald-400' :
                  opp.status === 'closed' ? 'bg-rose-500/20 text-rose-400' :
                  'bg-slate-700 text-slate-400'
                ]"
              >
                {{ opp.status }}
              </span>
            </td>
            <td class="px-6 py-4 font-mono font-bold text-slate-200">
              {{ opp.signups_count || 0 }}
            </td>
            <td class="px-6 py-4 text-slate-400 text-xs">
              {{ formatDate(opp.created_at) }}
            </td>
            <td class="px-6 py-4 text-right space-x-2">
              <router-link
                :to="`/admin/volunteering/${opp.id}`"
                class="px-3 py-1 text-xs font-medium text-emerald-300 bg-emerald-900/30 border border-emerald-500/30 rounded-lg hover:bg-emerald-800/40 transition"
              >
                Manage & Roster
              </router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Opportunity Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
      <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 sm:p-8 max-w-lg w-full space-y-6 shadow-2xl">
        <h2 class="text-xl font-bold text-slate-100">Create Volunteer Opportunity</h2>
        
        <form @submit.prevent="handleCreate" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1">Title *</label>
            <input v-model="createForm.title" type="text" required class="w-full px-4 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-200" />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1">Description</label>
            <textarea v-model="createForm.description" rows="3" class="w-full px-4 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-200"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-400 mb-1">Location</label>
              <input v-model="createForm.location" type="text" class="w-full px-4 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-200" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 mb-1">Status</label>
              <select v-model="createForm.status" class="w-full px-4 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-200">
                <option value="draft">Draft</option>
                <option value="open">Open</option>
                <option value="closed">Closed</option>
              </select>
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
            <button type="button" @click="showCreateModal = false" class="px-4 py-2 rounded-xl text-slate-400 hover:bg-slate-800 transition">Cancel</button>
            <button type="submit" class="px-5 py-2 rounded-xl font-bold text-slate-900 bg-emerald-400 hover:bg-emerald-300 transition">Save Opportunity</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { volunteeringService } from '../../../services/volunteeringService';

const opportunities = ref<any[]>([]);
const analytics = ref<any>(null);
const loading = ref(true);
const showCreateModal = ref(false);

const createForm = ref({
  title: '',
  description: '',
  location: '',
  status: 'open',
});

const fetchData = async () => {
  loading.value = true;
  try {
    const [oppRes, analyticsRes] = await Promise.all([
      volunteeringService.getAdminOpportunities(),
      volunteeringService.getAnalytics(),
    ]);
    opportunities.value = oppRes.data || [];
    analytics.value = analyticsRes.data;
  } catch (err) {
    console.error('Failed to load admin volunteer data:', err);
  } finally {
    loading.value = false;
  }
};

const handleCreate = async () => {
  try {
    await volunteeringService.createOpportunity(createForm.value);
    showCreateModal.value = false;
    createForm.value = { title: '', description: '', location: '', status: 'open' };
    await fetchData();
  } catch (err) {
    alert('Failed to create volunteer opportunity.');
  }
};

const formatDate = (dateStr?: string) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString();
};

onMounted(() => {
  fetchData();
});
</script>
