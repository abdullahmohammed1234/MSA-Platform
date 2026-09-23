<template>
  <div class="admin-volunteer-detail space-y-6">
    <div class="flex items-center justify-between">
      <router-link to="/admin/volunteering" class="text-xs font-semibold text-emerald-400 hover:underline">
        &larr; Back to Volunteer List
      </router-link>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="h-64 rounded-2xl bg-slate-800/60 animate-pulse"></div>

    <template v-else-if="opportunity">
      <!-- Opportunity Details Header -->
      <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl p-6 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-2xl font-bold text-slate-100">{{ opportunity.title }}</h1>
              <span
                :class="[
                  'px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase',
                  opportunity.status === 'open' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-400'
                ]"
              >
                {{ opportunity.status }}
              </span>
            </div>
            <p class="text-slate-400 text-sm mt-1">{{ opportunity.description }}</p>
          </div>

          <div class="flex gap-2">
            <button
              @click="toggleOpportunityStatus"
              class="px-4 py-2 text-xs font-semibold rounded-xl border transition"
              :class="opportunity.status === 'open' ? 'border-rose-500/30 text-rose-400 bg-rose-950/20 hover:bg-rose-900/30' : 'border-emerald-500/30 text-emerald-400 bg-emerald-950/20 hover:bg-emerald-900/30'"
            >
              {{ opportunity.status === 'open' ? 'Close Position' : 'Open Position' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Volunteer Roster Table -->
      <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl overflow-hidden shadow-xl space-y-4 p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <h2 class="text-lg font-bold text-slate-100">Volunteer Roster ({{ signups.length }})</h2>
          
          <div class="flex items-center gap-3">
            <select v-model="statusFilter" @change="fetchSignups" class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-xs text-slate-200">
              <option value="">All Statuses</option>
              <option value="signed_up">Signed Up</option>
              <option value="confirmed">Confirmed</option>
              <option value="completed">Completed</option>
              <option value="no_show">No Show</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-900/60 text-slate-400 text-xs uppercase tracking-wider border-b border-slate-700/60">
              <tr>
                <th class="px-4 py-3">Volunteer</th>
                <th class="px-4 py-3">Shift / Team</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Admin Notes</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
              <tr v-if="signups.length === 0">
                <td colspan="5" class="px-4 py-6 text-center text-slate-500">No volunteer signups found for this criteria.</td>
              </tr>
              <tr v-for="signup in signups" :key="signup.id" class="hover:bg-slate-700/30 transition">
                <td class="px-4 py-3">
                  <div class="font-semibold text-slate-100">{{ signup.name }}</div>
                  <div class="text-xs text-slate-400">{{ signup.email }} • {{ signup.phone || 'No phone' }}</div>
                </td>
                <td class="px-4 py-3 text-xs">
                  <div>{{ signup.shift?.name || 'Shift ' + (signup.shift_id || 'General') }}</div>
                  <div class="text-slate-500">{{ signup.team?.name || 'No team' }}</div>
                </td>
                <td class="px-4 py-3">
                  <span
                    :class="[
                      'px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase',
                      signup.status === 'completed' ? 'bg-emerald-500/20 text-emerald-400' :
                      signup.status === 'confirmed' ? 'bg-sky-500/20 text-sky-400' :
                      signup.status === 'cancelled' ? 'bg-rose-500/20 text-rose-400' :
                      signup.status === 'no_show' ? 'bg-amber-500/20 text-amber-400' :
                      'bg-slate-700 text-slate-300'
                    ]"
                  >
                    {{ signup.status.replace('_', ' ') }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-400">
                  {{ signup.admin_notes || '-' }}
                </td>
                <td class="px-4 py-3 text-right space-x-2">
                  <select
                    :value="signup.status"
                    @change="updateStatus(signup.id, ($event.target as HTMLSelectElement).value)"
                    class="px-2 py-1 rounded-lg bg-slate-900 border border-slate-700 text-xs text-slate-200"
                  >
                    <option value="signed_up">Signed Up</option>
                    <option value="confirmed">Confirm</option>
                    <option value="completed">Complete</option>
                    <option value="no_show">No Show</option>
                    <option value="cancelled">Cancel</option>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { volunteeringService } from '../../../services/volunteeringService';

const route = useRoute();
const id = Number(route.params.id);

const opportunity = ref<any>(null);
const signups = ref<any[]>([]);
const loading = ref(true);
const statusFilter = ref('');

const fetchOpportunityDetails = async () => {
  loading.value = true;
  try {
    const res = await volunteeringService.getAdminOpportunity(id);
    opportunity.value = res.data;
    await fetchSignups();
  } catch (err) {
    console.error('Failed to load admin opportunity details:', err);
  } finally {
    loading.value = false;
  }
};

const fetchSignups = async () => {
  try {
    const res = await volunteeringService.getSignupsForOpportunity(id, {
      status: statusFilter.value || undefined,
    });
    signups.value = res.data || [];
  } catch (err) {
    console.error('Failed to load signups:', err);
  }
};

const updateStatus = async (signupId: number, status: string) => {
  try {
    await volunteeringService.updateSignupStatus(signupId, { status });
    await fetchSignups();
  } catch (err) {
    alert('Failed to update volunteer status.');
  }
};

const toggleOpportunityStatus = async () => {
  if (!opportunity.value) return;
  const newStatus = opportunity.value.status === 'open' ? 'closed' : 'open';
  try {
    await volunteeringService.updateOpportunity(opportunity.value.id, { status: newStatus });
    opportunity.value.status = newStatus;
  } catch (err) {
    alert('Failed to update position status.');
  }
};

onMounted(() => {
  fetchOpportunityDetails();
});
</script>
