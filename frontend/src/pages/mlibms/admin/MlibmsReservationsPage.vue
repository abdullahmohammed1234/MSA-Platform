<template>
  <div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black">Hold Queue & Reservations</h1>
        <p class="text-neutral-muted text-sm mt-1">Manage member book holds, pickup availability, and queue cancellations.</p>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-2xl p-4 border border-neutral-ivory shadow-soft flex flex-wrap gap-4 items-center">
      <div class="flex-1 min-w-[240px]">
        <input
          v-model="searchQuery"
          @input="debouncedFetch"
          type="text"
          placeholder="Search by book title, member name, or email..."
          class="w-full bg-neutral-background border border-neutral-ivory rounded-xl px-4 py-2 text-sm text-neutral-black focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
        />
      </div>

      <select
        v-model="selectedStatus"
        @change="fetchReservations"
        class="bg-neutral-background border border-neutral-ivory rounded-xl px-3 py-2 text-sm text-neutral-black focus:outline-none focus:border-primary font-medium shadow-sm"
      >
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="ready_for_pickup">Ready for Pickup</option>
        <option value="fulfilled">Fulfilled</option>
        <option value="cancelled">Cancelled</option>
        <option value="expired">Expired</option>
      </select>
    </div>

    <!-- Reservations Table -->
    <div class="bg-white rounded-2xl border border-neutral-ivory shadow-soft overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-neutral-muted">Loading reservation queue...</div>
      <div v-else-if="reservations.length === 0" class="p-8 text-center text-neutral-muted">No hold reservations found.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm text-neutral-black">
          <thead class="bg-neutral-background text-xs uppercase text-neutral-muted font-bold tracking-wider border-b border-neutral-ivory">
            <tr>
              <th class="px-4 py-3">Book Title</th>
              <th class="px-4 py-3">Member</th>
              <th class="px-4 py-3">Queue Pos</th>
              <th class="px-4 py-3">Reserved At</th>
              <th class="px-4 py-3">Pickup Expiration</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory">
            <tr v-for="res in reservations" :key="res.id" class="hover:bg-neutral-background/60 transition-colors">
              <td class="px-4 py-3 font-bold text-neutral-black">{{ res.book?.title || 'Unknown Title' }}</td>
              <td class="px-4 py-3">
                <div class="font-bold text-neutral-black">{{ res.member?.name }}</div>
                <div class="text-xs text-neutral-muted">{{ res.member?.email }}</div>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 bg-amber-50 text-amber-800 border border-amber-200 rounded font-mono font-bold text-xs">
                  #{{ res.queue_position }}
                </span>
              </td>
              <td class="px-4 py-3 text-neutral-muted">{{ formatDate(res.reserved_at) }}</td>
              <td class="px-4 py-3 text-neutral-muted">{{ formatDate(res.expires_at) }}</td>
              <td class="px-4 py-3">
                <span :class="statusBadgeClass(res.status)" class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider">
                  {{ res.status?.replace(/_/g, ' ') }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  v-if="res.status === 'pending' || res.status === 'ready_for_pickup'"
                  @click="handleCancel(res.uuid)"
                  class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-bold transition-colors border border-rose-200"
                >
                  Cancel Hold
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';

const reservations = ref<any[]>([]);
const loading = ref(true);
const searchQuery = ref('');
const selectedStatus = ref('');

let timer: any = null;
const debouncedFetch = () => {
  clearTimeout(timer);
  timer = setTimeout(fetchReservations, 300);
};

const fetchReservations = async () => {
  loading.value = true;
  try {
    const res = await mlibmsAdminService.getReservations({
      query: searchQuery.value || undefined,
      status: selectedStatus.value || undefined,
    });
    reservations.value = res.data || res;
  } catch (err) {
    console.error('Failed to load reservations', err);
  } finally {
    loading.value = false;
  }
};

const handleCancel = async (uuid: string) => {
  if (!confirm('Are you sure you want to cancel this member hold reservation?')) return;
  try {
    await mlibmsAdminService.cancelReservation(uuid);
    await fetchReservations();
  } catch (err) {
    alert('Failed to cancel hold reservation.');
  }
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const statusBadgeClass = (status: string) => {
  switch (status) {
    case 'pending':
      return 'bg-amber-50 text-amber-700 border border-amber-200';
    case 'ready_for_pickup':
      return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    case 'fulfilled':
      return 'bg-blue-50 text-blue-700 border border-blue-200';
    case 'cancelled':
    case 'expired':
      return 'bg-neutral-background text-neutral-muted border border-neutral-ivory';
    default:
      return 'bg-neutral-background text-neutral-muted border border-neutral-ivory';
  }
};

onMounted(() => {
  fetchReservations();
});
</script>

