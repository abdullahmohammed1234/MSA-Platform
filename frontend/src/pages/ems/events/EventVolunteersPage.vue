<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  ArrowLeft,
  Download,
  HeartHandshake,
  Loader2,
  Search,
  UserCheck,
  UserX,
  Clock,
} from 'lucide-vue-next';
import client from '@/services/api';
import { useToastStore } from '@/components/feedback/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const eventId = computed(() => String(route.params.uuid || ''));
const loading = ref(true);
const volunteers = ref<any[]>([]);
const metrics = ref({
  total: 0,
  pending: 0,
  approved: 0,
  declined: 0,
});

const statusFilter = ref<string>('all');
const searchQuery = ref<string>('');
const updatingId = ref<number | null>(null);

const fetchVolunteers = async () => {
  loading.value = true;
  try {
    const params: any = {};
    if (statusFilter.value !== 'all') {
      params.status = statusFilter.value;
    }
    if (searchQuery.value) {
      params.search = searchQuery.value;
    }

    const res = await client.get(`/ems/events/${eventId.value}/volunteers`, { params });
    if (res.data?.success) {
      volunteers.value = res.data.data.volunteers || [];
      metrics.value = res.data.data.metrics || { total: 0, pending: 0, approved: 0, declined: 0 };
    }
  } catch (err: any) {
    toast.error('Failed to load event volunteers');
  } finally {
    loading.value = false;
  }
};

const updateStatus = async (volunteerId: number, status: 'approved' | 'declined' | 'pending') => {
  updatingId.value = volunteerId;
  try {
    const res = await client.patch(`/ems/events/${eventId.value}/volunteers/${volunteerId}/status`, {
      status,
    });
    if (res.data?.success) {
      toast.success(`Volunteer status updated to ${status}`);
      await fetchVolunteers();
    }
  } catch (err: any) {
    toast.error('Failed to update volunteer status');
  } finally {
    updatingId.value = null;
  }
};

const handleExport = () => {
  window.open(`/api/v1/ems/events/${eventId.value}/volunteers/export`, '_blank');
};

onMounted(() => {
  fetchVolunteers();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <button
          @click="router.back()"
          class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white transition-colors mb-2"
        >
          <ArrowLeft class="w-4 h-4" /> Back to Event
        </button>
        <h1 class="text-2xl sm:text-3xl font-bold text-white flex items-center gap-3">
          <HeartHandshake class="w-8 h-8 text-emerald-400" />
          <span>Event Volunteers</span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">Review and manage volunteer registrations for this event.</p>
      </div>

      <button
        @click="handleExport"
        class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl font-medium text-sm border border-slate-700 transition-all flex items-center gap-2 self-start sm:self-auto"
      >
        <Download class="w-4 h-4 text-emerald-400" />
        <span>Export CSV</span>
      </button>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4">
        <div class="text-xs uppercase tracking-wider font-semibold text-slate-400">Total Applications</div>
        <div class="text-2xl font-bold text-white mt-1">{{ metrics.total }}</div>
      </div>
      <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4">
        <div class="text-xs uppercase tracking-wider font-semibold text-amber-400 flex items-center gap-1.5">
          <Clock class="w-3.5 h-3.5" /> Pending
        </div>
        <div class="text-2xl font-bold text-amber-300 mt-1">{{ metrics.pending }}</div>
      </div>
      <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-4">
        <div class="text-xs uppercase tracking-wider font-semibold text-emerald-400 flex items-center gap-1.5">
          <UserCheck class="w-3.5 h-3.5" /> Approved
        </div>
        <div class="text-2xl font-bold text-emerald-300 mt-1">{{ metrics.approved }}</div>
      </div>
      <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
        <div class="text-xs uppercase tracking-wider font-semibold text-rose-400 flex items-center gap-1.5">
          <UserX class="w-3.5 h-3.5" /> Declined
        </div>
        <div class="text-2xl font-bold text-rose-300 mt-1">{{ metrics.declined }}</div>
      </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4 flex flex-col md:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-2 w-full md:w-auto overflow-x-auto pb-1 md:pb-0">
        <button
          v-for="st in ['all', 'pending', 'approved', 'declined']"
          :key="st"
          @click="statusFilter = st; fetchVolunteers()"
          :class="[
            'px-3.5 py-1.5 rounded-xl text-xs font-semibold uppercase tracking-wider transition-all whitespace-nowrap',
            statusFilter === st
              ? 'bg-emerald-500 text-slate-950 shadow-md shadow-emerald-500/20'
              : 'bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700'
          ]"
        >
          {{ st }}
        </button>
      </div>

      <div class="relative w-full md:w-72">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" />
        <input
          v-model="searchQuery"
          @keyup.enter="fetchVolunteers"
          type="text"
          placeholder="Search by name or email..."
          class="w-full bg-slate-800/80 border border-slate-700 focus:border-emerald-500 rounded-xl pl-9 pr-3.5 py-2 text-sm text-white focus:outline-none"
        />
      </div>
    </div>

    <!-- Volunteers List / Table -->
    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
      <div v-if="loading" class="p-12 text-center text-slate-400 flex flex-col items-center gap-3">
        <Loader2 class="w-8 h-8 animate-spin text-emerald-400" />
        <span>Loading volunteers...</span>
      </div>

      <div v-else-if="volunteers.length === 0" class="p-12 text-center text-slate-400">
        <HeartHandshake class="w-12 h-12 text-slate-600 mx-auto mb-3" />
        <p class="text-base font-semibold text-slate-300">No volunteer applications found.</p>
        <p class="text-xs mt-1">Try clearing filters or checking back later.</p>
      </div>

      <div v-else class="divide-y divide-slate-800">
        <div 
          v-for="v in volunteers" 
          :key="v.id"
          class="p-5 hover:bg-slate-800/40 transition-colors flex flex-col md:flex-row md:items-center justify-between gap-4"
        >
          <div class="space-y-1.5 max-w-xl">
            <div class="flex items-center gap-3">
              <span class="font-bold text-white text-base">{{ v.name }}</span>
              <span 
                :class="[
                  'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border',
                  v.status === 'approved' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
                  v.status === 'declined' ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' :
                  'bg-amber-500/20 text-amber-300 border-amber-500/30'
                ]"
              >
                {{ v.status }}
              </span>
            </div>

            <div class="text-xs text-slate-400 flex flex-wrap gap-x-4 gap-y-1">
              <span>{{ v.email }}</span>
              <span v-if="v.phone">• {{ v.phone }}</span>
              <span v-if="v.availability">• Availability: {{ v.availability }}</span>
            </div>

            <div v-if="v.interests && v.interests.length" class="flex flex-wrap gap-1.5 pt-1">
              <span 
                v-for="int in v.interests" 
                :key="int"
                class="px-2 py-0.5 bg-slate-800 text-slate-300 rounded-md text-[10px] font-medium"
              >
                {{ int }}
              </span>
            </div>

            <p v-if="v.experience" class="text-xs text-slate-300 italic pt-1">
              "{{ v.experience }}"
            </p>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2 self-end md:self-center shrink-0">
            <button
              v-if="v.status !== 'approved'"
              @click="updateStatus(v.id, 'approved')"
              :disabled="updatingId === v.id"
              class="px-3 py-1.5 bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white rounded-xl text-xs font-semibold transition-all border border-emerald-500/30 flex items-center gap-1.5"
            >
              <UserCheck class="w-3.5 h-3.5" />
              <span>Approve</span>
            </button>

            <button
              v-if="v.status !== 'declined'"
              @click="updateStatus(v.id, 'declined')"
              :disabled="updatingId === v.id"
              class="px-3 py-1.5 bg-rose-600/20 hover:bg-rose-600 text-rose-300 hover:text-white rounded-xl text-xs font-semibold transition-all border border-rose-500/30 flex items-center gap-1.5"
            >
              <UserX class="w-3.5 h-3.5" />
              <span>Decline</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
