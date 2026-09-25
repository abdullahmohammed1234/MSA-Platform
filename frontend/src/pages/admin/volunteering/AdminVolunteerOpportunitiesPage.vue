<template>
  <div class="admin-volunteer-opportunities space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft">
      <div>
        <span class="text-xs font-extrabold uppercase tracking-widest text-secondary">Volunteer Management System (VMS)</span>
        <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-primary">Volunteer Opportunities</h1>
        <p class="text-xs sm:text-sm text-neutral-black/70 mt-1">Configure EMS events for volunteering, manage teams, shifts, and signups.</p>
      </div>
      <button
        @click="openCreateModal"
        class="inline-flex items-center gap-2 px-5 py-3 rounded-full text-xs font-extrabold uppercase tracking-wider text-white bg-primary hover:bg-secondary transition shadow-brand cursor-pointer"
      >
        <Plus class="w-4 h-4" />
        <span>Enable EMS Event for Volunteering</span>
      </button>
    </div>

    <!-- Analytics Metrics Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" v-if="analytics">
      <div class="bg-white border border-neutral-ivory rounded-3xl p-5 shadow-soft space-y-1">
        <div class="text-neutral-muted text-xs font-bold uppercase tracking-wider">Total Opportunities</div>
        <div class="text-3xl font-display font-extrabold text-primary">{{ analytics.total_opportunities || 0 }}</div>
      </div>
      <div class="bg-white border border-neutral-ivory rounded-3xl p-5 shadow-soft space-y-1">
        <div class="text-neutral-muted text-xs font-bold uppercase tracking-wider">Open Positions</div>
        <div class="text-3xl font-display font-extrabold text-emerald-700">{{ analytics.open_opportunities || 0 }}</div>
      </div>
      <div class="bg-white border border-neutral-ivory rounded-3xl p-5 shadow-soft space-y-1">
        <div class="text-neutral-muted text-xs font-bold uppercase tracking-wider">Active Signups</div>
        <div class="text-3xl font-display font-extrabold text-secondary">{{ analytics.active_signups || 0 }}</div>
      </div>
      <div class="bg-white border border-neutral-ivory rounded-3xl p-5 shadow-soft space-y-1">
        <div class="text-neutral-muted text-xs font-bold uppercase tracking-wider">Completed Signups</div>
        <div class="text-3xl font-display font-extrabold text-amber-700">{{ analytics.completed_signups || 0 }}</div>
      </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="bg-white border border-neutral-ivory rounded-3xl p-4 sm:p-6 shadow-soft flex flex-col sm:flex-row gap-4 justify-between items-center">
      <div class="relative w-full sm:w-80">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by title or location..."
          class="w-full pl-10 pr-4 py-2 rounded-full border border-neutral-ivory text-xs sm:text-sm text-neutral-black placeholder-neutral-muted focus:outline-none focus:border-primary"
          @input="fetchData"
        />
        <Search class="w-4 h-4 absolute left-3.5 top-3 text-neutral-muted" />
      </div>

      <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
        <select
          v-model="statusFilter"
          @change="fetchData"
          class="px-3.5 py-2 rounded-full border border-neutral-ivory text-xs font-bold uppercase tracking-wider text-neutral-black bg-white focus:outline-none focus:border-primary"
        >
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="draft">Draft</option>
          <option value="closed">Closed</option>
        </select>
      </div>
    </div>

    <!-- Opportunities Table -->
    <div class="bg-white border border-neutral-ivory rounded-3xl overflow-hidden shadow-soft">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-neutral-black">
          <thead class="bg-neutral-background/60 text-neutral-muted text-xs uppercase tracking-wider border-b border-neutral-ivory">
            <tr>
              <th class="px-6 py-4">Opportunity</th>
              <th class="px-6 py-4">Association</th>
              <th class="px-6 py-4">Status</th>
              <th class="px-6 py-4">Signups</th>
              <th class="px-6 py-4">Created Date</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory/60">
            <tr v-if="loading">
              <td colspan="6" class="px-6 py-8 text-center text-neutral-muted">Loading volunteer opportunities...</td>
            </tr>
            <tr v-else-if="opportunities.length === 0">
              <td colspan="6" class="px-6 py-8 text-center text-neutral-muted">No volunteer opportunities found matching your criteria.</td>
            </tr>
            <tr v-for="opp in opportunities" :key="opp.id" class="hover:bg-neutral-background/50 transition">
              <td class="px-6 py-4 font-bold text-primary">
                <router-link :to="`/vms/opportunities/${opp.id}`" class="hover:underline">
                  {{ opp.title }}
                </router-link>
              </td>
              <td class="px-6 py-4">
                <span v-if="opp.event" class="px-2.5 py-0.5 rounded-full text-xs font-extrabold uppercase tracking-wider bg-primary/10 text-primary border border-primary/20">
                  EMS Event: {{ opp.event.name }}
                </span>
                <span v-else class="text-neutral-muted text-xs font-bold uppercase tracking-wider">Standalone</span>
              </td>
              <td class="px-6 py-4">
                <span
                  :class="[
                    'px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider border',
                    opp.status === 'open' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' :
                    opp.status === 'closed' ? 'bg-rose-50 text-rose-800 border-rose-200' :
                    'bg-neutral-ivory/60 text-neutral-black/70 border-neutral-ivory'
                  ]"
                >
                  {{ opp.status }}
                </span>
              </td>
              <td class="px-6 py-4 font-mono font-bold text-neutral-black">
                {{ opp.signups_count || 0 }}
              </td>
              <td class="px-6 py-4 text-neutral-muted text-xs">
                {{ formatDate(opp.created_at) }}
              </td>
              <td class="px-6 py-4 text-right space-x-2">
                <router-link
                  :to="`/vms/opportunities/${opp.id}`"
                  class="px-3.5 py-1.5 text-xs font-extrabold uppercase tracking-wider text-primary border border-primary/30 rounded-full hover:bg-primary/5 transition"
                >
                  Manage Roster
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Configure EMS Event for Volunteering Modal -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 bg-primary/30 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 max-w-lg w-full space-y-6 shadow-2xl">
          <div class="flex justify-between items-center border-b border-neutral-ivory pb-4">
            <div>
              <h2 class="text-xl font-display font-extrabold text-primary">Enable Volunteering for EMS Event</h2>
              <p class="text-xs text-neutral-muted mt-0.5">Derive volunteer position details directly from an EMS Event.</p>
            </div>
            <button @click="showCreateModal = false" class="text-neutral-muted hover:text-neutral-black">
              <X class="w-5 h-5" />
            </button>
          </div>
          
          <form @submit.prevent="handleCreate" class="space-y-4">
            <div>
              <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Select EMS Event *</label>
              <select
                v-model="createForm.event_id"
                @change="onEventSelect"
                required
                class="w-full px-4 py-2.5 rounded-xl border border-neutral-ivory text-sm text-neutral-black focus:outline-none focus:border-primary"
              >
                <option :value="null" disabled>Choose an EMS Event...</option>
                <option v-for="event in eligibleEvents" :key="event.id" :value="event.id" :disabled="event.is_configured">
                  {{ event.name }} {{ event.is_configured ? '(Already Configured)' : '' }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Position Title *</label>
              <input v-model="createForm.title" type="text" required class="w-full px-4 py-2.5 rounded-xl border border-neutral-ivory text-sm text-neutral-black focus:outline-none focus:border-primary" />
            </div>

            <div>
              <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Description</label>
              <textarea v-model="createForm.description" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-neutral-ivory text-sm text-neutral-black focus:outline-none focus:border-primary"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Location</label>
                <input v-model="createForm.location" type="text" class="w-full px-4 py-2.5 rounded-xl border border-neutral-ivory text-sm text-neutral-black focus:outline-none focus:border-primary" />
              </div>
              <div>
                <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Status</label>
                <select v-model="createForm.status" class="w-full px-4 py-2.5 rounded-xl border border-neutral-ivory text-sm text-neutral-black focus:outline-none focus:border-primary">
                  <option value="draft">Draft</option>
                  <option value="open">Open</option>
                  <option value="closed">Closed</option>
                </select>
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-neutral-ivory">
              <button type="button" @click="showCreateModal = false" class="px-5 py-2.5 rounded-full text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 border border-neutral-ivory hover:bg-neutral-background transition cursor-pointer">Cancel</button>
              <button type="submit" :disabled="creating" class="px-5 py-2.5 rounded-full text-xs font-extrabold uppercase tracking-wider text-white bg-primary hover:bg-secondary transition shadow-brand cursor-pointer">
                {{ creating ? 'Saving...' : 'Enable Volunteering' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Plus, Search, X } from 'lucide-vue-next';
import { volunteeringService } from '@/services/volunteeringService';

const opportunities = ref<any[]>([]);
const eligibleEvents = ref<any[]>([]);
const analytics = ref<any>(null);
const loading = ref(true);
const creating = ref(false);
const showCreateModal = ref(false);

const searchQuery = ref('');
const statusFilter = ref('');

const createForm = ref<{
  event_id: number | null;
  title: string;
  description: string;
  location: string;
  status: string;
}>({
  event_id: null,
  title: '',
  description: '',
  location: '',
  status: 'open',
});

const fetchData = async () => {
  loading.value = true;
  try {
    const [oppRes, analyticsRes] = await Promise.all([
      volunteeringService.getAdminOpportunities({
        search: searchQuery.value || undefined,
        status: statusFilter.value || undefined,
      }),
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

const openCreateModal = async () => {
  try {
    const eventsRes = await volunteeringService.getEligibleEvents();
    eligibleEvents.value = eventsRes.data || [];
  } catch (err) {
    console.error('Failed to load eligible EMS events:', err);
  }
  showCreateModal.value = true;
};

const onEventSelect = () => {
  if (!createForm.value.event_id) return;
  const selected = eligibleEvents.value.find(e => e.id === createForm.value.event_id);
  if (selected) {
    createForm.value.title = selected.name;
    createForm.value.description = selected.description || '';
    createForm.value.location = selected.location || '';
  }
};

const handleCreate = async () => {
  creating.value = true;
  try {
    await volunteeringService.createOpportunity(createForm.value);
    showCreateModal.value = false;
    createForm.value = { event_id: null, title: '', description: '', location: '', status: 'open' };
    await fetchData();
  } catch (err) {
    alert('Failed to configure EMS event for volunteering.');
  } finally {
    creating.value = false;
  }
};

const formatDate = (dateStr?: string) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

onMounted(() => {
  fetchData();
});
</script>
