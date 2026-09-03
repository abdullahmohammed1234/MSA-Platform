<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { donationsService } from '@/services/donations.service';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const donors = ref<any[]>([]);
const search = ref('');
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const isLoading = ref(false);

onMounted(async () => {
  await fetchDonors();
});

const fetchDonors = async (page = 1) => {
  isLoading.value = true;
  try {
    const data = await donationsService.getDmsDonors({
      page,
      search: search.value || undefined,
    });
    donors.value = data.donors || [];
    currentPage.value = data.meta?.current_page || 1;
    lastPage.value = data.meta?.last_page || 1;
    total.value = data.meta?.total || 0;
  } catch (error) {
    toast.error('Failed to load donors list.');
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

let searchTimeout: any = null;
watch(search, () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchDonors(1);
  }, 350);
});
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-display font-medium text-primary">Donors Roster</h1>
      <p class="text-sm text-neutral-muted mt-1">Aggregated breakdown of donor contributions and donation counts.</p>
    </div>

    <!-- Controls -->
    <div class="bg-white border border-neutral-ivory p-4 rounded-2xl shadow-soft flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="relative max-w-xs w-full">
        <input
          v-model="search"
          type="text"
          placeholder="Search donor name or email..."
          class="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40"
        />
        <span class="absolute left-3 top-2.5 text-neutral-muted">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </span>
      </div>

      <div class="text-xs text-neutral-muted whitespace-nowrap">
        Total Donors: <span class="font-bold text-neutral-black">{{ total }}</span>
      </div>
    </div>

    <!-- Donors Table -->
    <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft overflow-hidden">
      <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
        <p class="text-xs text-neutral-muted">Loading donor records...</p>
      </div>

      <div v-else-if="donors.length === 0" class="py-20 text-center text-neutral-muted italic">
        No donor records match your search criteria.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-neutral-background/50 border-b border-neutral-ivory/50 text-[10px] font-bold uppercase tracking-wider text-neutral-muted">
              <th class="px-6 py-4">Donor Name</th>
              <th class="px-6 py-4">Email</th>
              <th class="px-6 py-4">Total Donations</th>
              <th class="px-6 py-4">Total Contributed</th>
              <th class="px-6 py-4">Last Donated</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory/30 text-xs">
            <tr v-for="d in donors" :key="d.donor_email" class="hover:bg-neutral-background/30 transition-colors">
              <td class="px-6 py-4 font-semibold text-neutral-black">{{ d.donor_name }}</td>
              <td class="px-6 py-4 font-mono text-neutral-muted">{{ d.donor_email }}</td>
              <td class="px-6 py-4 font-bold text-neutral-black">{{ d.total_donations }}</td>
              <td class="px-6 py-4 font-bold text-emerald-600">{{ d.formatted_total }}</td>
              <td class="px-6 py-4 font-mono text-neutral-muted">{{ new Date(d.last_donated_at).toLocaleDateString() }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="px-6 py-4 border-t border-neutral-ivory/50 flex justify-between items-center text-xs">
        <span class="text-neutral-muted">Page {{ currentPage }} of {{ lastPage }}</span>
        <div class="flex gap-2">
          <button
            @click="fetchDonors(currentPage - 1)"
            :disabled="currentPage === 1 || isLoading"
            class="px-3 py-1.5 rounded-xl border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 transition-colors cursor-pointer text-neutral-muted font-semibold"
          >
            Previous
          </button>
          <button
            @click="fetchDonors(currentPage + 1)"
            :disabled="currentPage === lastPage || isLoading"
            class="px-3 py-1.5 rounded-xl border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 transition-colors cursor-pointer text-neutral-muted font-semibold"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
