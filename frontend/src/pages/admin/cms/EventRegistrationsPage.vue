<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  ArrowLeft,
  QrCode,
  RefreshCw,
  Search,
  Users,
} from 'lucide-vue-next';
import cmsService from '@/services/cms/cmsService';
import type { Event, EventRegistration } from '@/types/cms';

const route = useRoute();
const router = useRouter();

const eventUuid = computed(() => String(route.params.uuid || ''));
const event = ref<Event | null>(null);
const registrations = ref<EventRegistration[]>([]);
const isLoading = ref(true);
const error = ref('');
const searchQuery = ref('');
const statusFilter = ref<'all' | 'registered' | 'attending'>('all');
const pagination = ref({ page: 1, lastPage: 1, total: 0 });

const filteredRegistrations = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();

  return registrations.value.filter((person) => {
    const matchesStatus =
      statusFilter.value === 'all' || person.status === statusFilter.value;
    if (!matchesStatus) return false;
    if (!query) return true;

    const haystack = `${person.full_name} ${person.email} ${person.phone || ''}`.toLowerCase();
    return haystack.includes(query);
  });
});

const attendingCount = computed(
  () => registrations.value.filter((person) => person.status === 'attending').length
);
const registeredCount = computed(
  () => registrations.value.filter((person) => person.status === 'registered').length
);

async function loadRegistrations(page = 1) {
  if (!eventUuid.value) return;

  isLoading.value = true;
  error.value = '';

  try {
    const res = await cmsService.getEventRegistrations(eventUuid.value, {
      page,
      per_page: 100,
    });

    event.value = {
      uuid: res.event.uuid,
      title: res.event.title,
      spots_left: res.event.spots_left,
    } as Event;

    // Keep uuid internally for matching, but never render it in the list UI.
    registrations.value = res.data;
    pagination.value = {
      page: res.current_page,
      lastPage: res.last_page,
      total: res.total,
    };
  } catch (err: any) {
    error.value = err?.response?.data?.message || err?.message || 'Failed to load registrations.';
  } finally {
    isLoading.value = false;
  }
}

function openCheckIn() {
  router.push({
    name: 'admin-cms-event-check-in',
    query: { event: eventUuid.value },
  });
}

onMounted(() => loadRegistrations());
watch(eventUuid, () => loadRegistrations());
</script>

<template>
  <div class="space-y-6 text-neutral-black">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
      <div>
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-neutral-muted hover:text-primary mb-3 cursor-pointer"
          @click="router.push({ name: 'admin-cms-events' })"
        >
          <ArrowLeft :size="14" /> Back to events
        </button>
        <h1 class="text-3xl font-display font-extrabold text-primary">
          {{ event?.title || 'Event registrations' }}
        </h1>
        <p class="text-sm text-neutral-muted mt-1">
          Registered guests for this event. Check-in codes are kept hidden and only used when scanning.
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl border border-neutral-gray/20 bg-white px-4 py-3 text-[10px] font-black uppercase tracking-widest hover:bg-primary/5 cursor-pointer"
          :disabled="isLoading"
          @click="loadRegistrations(pagination.page)"
        >
          <RefreshCw :size="14" /> Refresh
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl bg-primary text-white px-4 py-3 text-[10px] font-black uppercase tracking-widest hover:bg-secondary cursor-pointer"
          @click="openCheckIn"
        >
          <QrCode :size="14" /> Open check-in scanner
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="bg-white border border-neutral-gray/10 rounded-3xl p-5 shadow-soft">
        <div class="text-[10px] uppercase tracking-widest text-neutral-muted font-bold">Total registered</div>
        <div class="text-2xl font-display font-extrabold text-primary mt-1">{{ pagination.total }}</div>
      </div>
      <div class="bg-white border border-neutral-gray/10 rounded-3xl p-5 shadow-soft">
        <div class="text-[10px] uppercase tracking-widest text-neutral-muted font-bold">Still registered</div>
        <div class="text-2xl font-display font-extrabold text-primary mt-1">{{ registeredCount }}</div>
      </div>
      <div class="bg-white border border-neutral-gray/10 rounded-3xl p-5 shadow-soft">
        <div class="text-[10px] uppercase tracking-widest text-neutral-muted font-bold">Attending</div>
        <div class="text-2xl font-display font-extrabold text-emerald-700 mt-1">{{ attendingCount }}</div>
      </div>
    </div>

    <div class="bg-white border border-neutral-gray/10 rounded-3xl p-4 shadow-soft flex flex-col md:flex-row gap-3">
      <div class="relative flex-1">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-black/30" :size="16" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by name, email, or phone..."
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 pl-12 pr-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20"
        />
      </div>
      <select
        v-model="statusFilter"
        class="md:w-48 bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-4 text-xs font-semibold"
      >
        <option value="all">All statuses</option>
        <option value="registered">Registered</option>
        <option value="attending">Attending</option>
      </select>
    </div>

    <div v-if="error" class="p-4 bg-secondary/10 border border-secondary/20 text-secondary rounded-2xl text-xs font-bold">
      {{ error }}
    </div>

    <div class="bg-white border border-neutral-gray/10 rounded-[2rem] shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-xs">
          <thead class="bg-neutral-background/50 border-b border-neutral-gray/10 uppercase font-black text-neutral-black/40 tracking-wider">
            <tr>
              <th class="p-5">Guest</th>
              <th class="p-5">Contact</th>
              <th class="p-5">Status</th>
              <th class="p-5">Registered</th>
              <th class="p-5">Checked in</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-gray/10">
            <tr v-if="isLoading">
              <td colspan="5" class="p-10 text-center text-neutral-muted">Loading registrations...</td>
            </tr>
            <tr v-else-if="filteredRegistrations.length === 0">
              <td colspan="5" class="p-10 text-center text-neutral-muted">
                <Users :size="28" class="mx-auto mb-3 opacity-40" />
                No guests match this filter.
              </td>
            </tr>
            <tr v-for="person in filteredRegistrations" :key="person.uuid" class="hover:bg-neutral-background/30">
              <td class="p-5 font-bold text-primary">{{ person.full_name }}</td>
              <td class="p-5 text-neutral-black/70">
                <div>{{ person.email }}</div>
                <div v-if="person.phone" class="text-neutral-muted mt-0.5">{{ person.phone }}</div>
              </td>
              <td class="p-5">
                <span
                  class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider"
                  :class="person.status === 'attending'
                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                    : 'bg-primary/5 text-primary border border-primary/15'"
                >
                  {{ person.status }}
                </span>
              </td>
              <td class="p-5 text-neutral-muted">
                {{ person.registered_at ? new Date(person.registered_at).toLocaleString() : '—' }}
              </td>
              <td class="p-5 text-neutral-muted">
                {{ person.checked_in_at ? new Date(person.checked_in_at).toLocaleString() : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
