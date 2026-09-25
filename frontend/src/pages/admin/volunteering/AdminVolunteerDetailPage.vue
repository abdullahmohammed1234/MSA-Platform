<template>
  <div class="admin-volunteer-detail space-y-8">
    
    <!-- Top Nav -->
    <div>
      <router-link
        to="/vms/opportunities"
        class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 hover:text-primary transition"
      >
        <ArrowLeft class="w-4 h-4" />
        <span>Back to Volunteer Opportunities</span>
      </router-link>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="bg-white rounded-3xl border border-neutral-ivory p-12 text-center space-y-4 shadow-soft">
      <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto" />
      <p class="text-sm font-bold text-neutral-black/70">Loading volunteer opportunity roster...</p>
    </div>

    <template v-else-if="opportunity">
      <!-- Opportunity Details Header -->
      <div class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 shadow-soft space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-primary">{{ opportunity.title }}</h1>
              <span
                :class="[
                  'px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider border',
                  opportunity.status === 'open' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'
                ]"
              >
                {{ opportunity.status }}
              </span>
              <span v-if="opportunity.event" class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-primary/10 text-primary border border-primary/20">
                Event: {{ opportunity.event.name }}
              </span>
            </div>
            <p v-if="opportunity.description" class="text-xs sm:text-sm text-neutral-black/70">{{ opportunity.description }}</p>
          </div>

          <div class="flex items-center gap-3">
            <button
              @click="toggleOpportunityStatus"
              :class="[
                'px-5 py-2.5 rounded-full text-xs font-extrabold uppercase tracking-wider transition cursor-pointer border',
                opportunity.status === 'open' 
                  ? 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100' 
                  : 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100'
              ]"
            >
              {{ opportunity.status === 'open' ? 'Close Opportunity' : 'Open Opportunity' }}
            </button>
          </div>
        </div>

        <!-- Team & Shift Capacity Summary Bars -->
        <div v-if="opportunity.teams && opportunity.teams.length > 0" class="pt-4 border-t border-neutral-ivory space-y-3">
          <h3 class="text-xs font-extrabold uppercase tracking-wider text-neutral-muted">Teams & Capacity Overview</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="team in opportunity.teams"
              :key="team.id"
              class="bg-neutral-background rounded-2xl p-4 border border-neutral-ivory/80 space-y-2"
            >
              <div class="flex justify-between items-center text-xs font-bold text-neutral-black">
                <span>{{ team.name }}</span>
                <span class="font-mono text-primary">{{ getTeamSignupCount(team.id) }} / {{ team.capacity || '∞' }}</span>
              </div>
              <div class="w-full h-2 bg-neutral-ivory rounded-full overflow-hidden">
                <div
                  class="h-full bg-primary rounded-full transition-all"
                  :style="{ width: getTeamCapacityPercentage(team) + '%' }"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Volunteer Roster Management -->
      <div class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 shadow-soft space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-neutral-ivory pb-4">
          <div>
            <h2 class="text-xl font-display font-extrabold text-primary">Volunteer Roster ({{ signups.length }})</h2>
            <p class="text-xs text-neutral-black/70">Manage volunteer registrations, assign statuses, and review contact details.</p>
          </div>
          
          <div class="flex flex-wrap items-center gap-3">
            <select
              v-model="statusFilter"
              @change="fetchSignups"
              class="px-3.5 py-2 rounded-full border border-neutral-ivory text-xs font-bold uppercase tracking-wider text-neutral-black bg-white focus:outline-none focus:border-primary"
            >
              <option value="">All Statuses</option>
              <option value="signed_up">Signed Up</option>
              <option value="confirmed">Confirmed</option>
              <option value="completed">Completed</option>
              <option value="no_show">No Show</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-neutral-black">
            <thead class="bg-neutral-background/60 text-neutral-muted text-xs uppercase tracking-wider border-b border-neutral-ivory">
              <tr>
                <th class="px-4 py-3">Volunteer</th>
                <th class="px-4 py-3">Team & Shift</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Experience / Notes</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory/60">
              <tr v-if="signups.length === 0">
                <td colspan="5" class="px-4 py-6 text-center text-neutral-muted">No volunteer signups found for this criteria.</td>
              </tr>
              <tr v-for="signup in signups" :key="signup.id" class="hover:bg-neutral-background/40 transition">
                <td class="px-4 py-3">
                  <div class="font-bold text-primary">{{ signup.name }}</div>
                  <div class="text-xs text-neutral-muted">{{ signup.email }} &bull; {{ signup.phone || 'No phone' }}</div>
                </td>
                <td class="px-4 py-3 text-xs">
                  <div class="font-bold">{{ signup.team?.name || 'General Team' }}</div>
                  <div class="text-neutral-muted font-mono text-[11px]">{{ signup.shift?.name || 'General Shift' }}</div>
                </td>
                <td class="px-4 py-3">
                  <span
                    :class="[
                      'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider border',
                      statusBadgeClass(signup.status)
                    ]"
                  >
                    {{ signup.status.replace('_', ' ') }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-neutral-black/70 max-w-xs truncate">
                  {{ signup.experience || signup.notes || '-' }}
                </td>
                <td class="px-4 py-3 text-right">
                  <select
                    :value="signup.status"
                    @change="updateStatus(signup.id, ($event.target as HTMLSelectElement).value)"
                    class="px-3 py-1.5 rounded-full border border-neutral-ivory text-xs font-bold uppercase tracking-wider bg-white focus:outline-none focus:border-primary cursor-pointer"
                  >
                    <option value="signed_up">Signed Up</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="completed">Completed</option>
                    <option value="no_show">No Show</option>
                    <option value="cancelled">Cancelled</option>
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
import { ArrowLeft } from 'lucide-vue-next';
import { volunteeringService } from '@/services/volunteeringService';

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

const getTeamSignupCount = (teamId: number) => {
  return signups.value.filter(s => s.team_id === teamId && s.status !== 'cancelled').length;
};

const getTeamCapacityPercentage = (team: any) => {
  if (!team.capacity) return 50;
  const count = getTeamSignupCount(team.id);
  return Math.min(100, Math.round((count / team.capacity) * 100));
};

const statusBadgeClass = (status: string) => {
  const s = status?.toLowerCase();
  if (s === 'confirmed' || s === 'approved') return 'bg-emerald-50 text-emerald-800 border-emerald-200';
  if (s === 'completed') return 'bg-sky-50 text-sky-800 border-sky-200';
  if (s === 'no_show') return 'bg-amber-50 text-amber-800 border-amber-200';
  if (s === 'cancelled') return 'bg-rose-50 text-rose-800 border-rose-200';
  return 'bg-neutral-ivory/60 text-neutral-black/70 border-neutral-ivory';
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
