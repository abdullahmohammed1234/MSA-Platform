<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { connectAdminService } from '@/services/admin/connectAdminService';
import type { ConnectEventItem } from '@/services/admin/connectAdminService';
import {
  Calendar,
  Search,
  ExternalLink,
  Info,
  MapPin,
  Clock,
} from 'lucide-vue-next';

const isLoading = ref(true);
const error = ref<string | null>(null);
const events = ref<ConnectEventItem[]>([]);
const total = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const searchQuery = ref('');
const ownershipNotice = ref('');

const fetchEvents = async (page = 1) => {
  try {
    isLoading.value = true;
    error.value = null;
    const res = await connectAdminService.getEvents({
      search: searchQuery.value.trim() || undefined,
      page,
      per_page: 15,
    });
    events.value = res.events.data;
    total.value = res.events.total;
    currentPage.value = res.events.current_page;
    lastPage.value = res.events.last_page;
    ownershipNotice.value = res.ownership_notice;
  } catch (err: any) {
    error.value = err?.response?.data?.message || err?.message || 'Failed to load events from EMS.';
  } finally {
    isLoading.value = false;
  }
};

const handleSearch = () => {
  void fetchEvents(1);
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return '—';
  try {
    return new Date(dateStr).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  } catch {
    return dateStr;
  }
};

onMounted(() => {
  void fetchEvents(1);
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-neutral-900">EMS Event Integration</h1>
        <p class="text-xs text-neutral-500 mt-1">
          Inspect authoritative EMS events delivered into member-facing Connect feeds and calendar views.
        </p>
      </div>
      <router-link
        to="/ems"
        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-[#ebe8de] text-xs font-semibold text-neutral-700 hover:text-[#640c0e] hover:bg-[#faf8f5] transition-colors shadow-xs self-start sm:self-center"
      >
        <span>Open EMS Console</span>
        <ExternalLink class="w-3.5 h-3.5" />
      </router-link>
    </div>

    <!-- EMS Source of Truth Notice Banner -->
    <div class="bg-blue-50/70 border border-blue-200/80 rounded-2xl p-4 text-xs text-blue-900 flex items-start gap-3">
      <Info class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
      <div class="space-y-1">
        <div class="font-bold">Authoritative Source of Truth: Event Management System (EMS)</div>
        <p class="text-blue-800 leading-relaxed">
          {{ ownershipNotice || 'EMS is the sole authoritative owner of events, ticketing, registrations, Square payments, refunds, and check-in workflows. MSA Connect does not duplicate event management tables or bypass EMS lifecycle validation.' }}
        </p>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-2xl border border-[#ebe8de] p-4 shadow-xs flex items-center gap-3">
      <div class="relative flex-1">
        <Search class="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          type="text"
          v-model="searchQuery"
          @keyup.enter="handleSearch"
          placeholder="Filter EMS events by title or slug..."
          class="w-full text-xs pl-10 pr-4 py-2 rounded-xl border border-[#ebe8de] bg-[#faf8f5] focus:outline-none focus:border-[#640c0e]"
        />
      </div>
      <button
        type="button"
        @click="handleSearch"
        class="px-4 py-2 bg-[#640c0e] text-white text-xs font-semibold rounded-xl hover:bg-[#500a0b] transition-colors cursor-pointer"
      >
        Search
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="bg-white rounded-2xl border border-[#ebe8de] p-8 text-center text-xs text-neutral-400">
      Loading events from EMS...
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-2xl p-4 text-xs text-red-700">
      {{ error }}
    </div>

    <!-- Empty State -->
    <div v-else-if="events.length === 0" class="bg-white rounded-2xl border border-[#ebe8de] p-12 text-center space-y-2">
      <Calendar class="w-8 h-8 text-neutral-300 mx-auto" />
      <div class="text-sm font-bold text-neutral-700">No Events Found</div>
      <p class="text-xs text-neutral-500 max-w-sm mx-auto">
        No events currently match your search criteria. Published events created in EMS will appear here.
      </p>
    </div>

    <!-- Events Table -->
    <div v-else class="bg-white rounded-2xl border border-[#ebe8de] shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-[#faf8f5] border-b border-[#ebe8de] text-neutral-500 font-semibold uppercase tracking-wider text-[10px]">
            <tr>
              <th class="py-3 px-4">Event Name & Slug</th>
              <th class="py-3 px-4">Date & Time</th>
              <th class="py-3 px-4">Location</th>
              <th class="py-3 px-4">Visibility</th>
              <th class="py-3 px-4">EMS Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#ebe8de]">
            <tr v-for="evt in events" :key="evt.id" class="hover:bg-[#faf8f5]/60 transition-colors">
              <td class="py-3.5 px-4 font-medium text-neutral-900">
                <div class="font-bold text-sm">{{ evt.name }}</div>
                <div class="text-[11px] text-neutral-400 font-mono mt-0.5">{{ evt.slug }}</div>
              </td>
              <td class="py-3.5 px-4 text-neutral-600 whitespace-nowrap">
                <div class="flex items-center gap-1.5">
                  <Clock class="w-3.5 h-3.5 text-neutral-400" />
                  <span>{{ formatDate(evt.start_at) }}</span>
                </div>
              </td>
              <td class="py-3.5 px-4 text-neutral-600">
                <div class="flex items-center gap-1.5">
                  <MapPin class="w-3.5 h-3.5 text-neutral-400" />
                  <span>{{ evt.location || 'Online / Campus' }}</span>
                </div>
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-2">
                  <span
                    v-if="evt.is_public"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700"
                  >
                    Public
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-neutral-100 text-neutral-600"
                  >
                    Private
                  </span>
                  <span
                    v-if="evt.is_featured"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-700"
                  >
                    Featured
                  </span>
                </div>
              </td>
              <td class="py-3.5 px-4">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-neutral-100 text-neutral-700">
                  {{ evt.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div v-if="lastPage > 1" class="p-4 border-t border-[#ebe8de] flex items-center justify-between text-xs text-neutral-500">
        <div>Showing page {{ currentPage }} of {{ lastPage }} ({{ total }} total events)</div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="currentPage <= 1"
            @click="fetchEvents(currentPage - 1)"
            class="px-2.5 py-1 rounded-lg border border-[#ebe8de] disabled:opacity-40 cursor-pointer"
          >
            Previous
          </button>
          <button
            type="button"
            :disabled="currentPage >= lastPage"
            @click="fetchEvents(currentPage + 1)"
            class="px-2.5 py-1 rounded-lg border border-[#ebe8de] disabled:opacity-40 cursor-pointer"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
