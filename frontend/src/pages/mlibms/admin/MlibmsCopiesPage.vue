<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Search, Printer, ChevronLeft, ChevronRight, QrCode, AlertCircle, RefreshCw } from 'lucide-vue-next';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const copies = ref<any[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');
const statusFilter = ref('');

// Pagination state
const currentPage = ref(1);
const lastPage = ref(1);
const perPage = ref(25);
const totalCopies = ref(0);

const fetchCopies = async (page = 1) => {
  isLoading.value = true;
  currentPage.value = page;

  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: perPage.value,
    };
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }
    if (statusFilter.value) {
      params.status = statusFilter.value;
    }

    const res = await mlibmsAdminService.getCopies(params);
    copies.value = res.data || [];

    if (res.meta) {
      currentPage.value = res.meta.current_page || 1;
      lastPage.value = res.meta.last_page || 1;
      perPage.value = res.meta.per_page || 25;
      totalCopies.value = res.meta.total || 0;
    } else {
      totalCopies.value = copies.value.length;
      lastPage.value = 1;
    }
  } catch (e) {
    toast.error('Failed to load physical copy inventory.');
  } finally {
    isLoading.value = false;
  }
};

const handleSearch = () => {
  fetchCopies(1);
};

const handleStatusChange = () => {
  fetchCopies(1);
};

const handlePerPageChange = () => {
  fetchCopies(1);
};

const clearFilters = () => {
  searchQuery.value = '';
  statusFilter.value = '';
  fetchCopies(1);
};

const printLabels = () => {
  window.print();
};

const showingRange = computed(() => {
  if (totalCopies.value === 0) return '0 items';
  const start = (currentPage.value - 1) * perPage.value + 1;
  const end = Math.min(currentPage.value * perPage.value, totalCopies.value);
  return `${start}–${end} of ${totalCopies.value} items`;
});

onMounted(() => fetchCopies(1));
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black flex items-center gap-2">
          <QrCode class="w-6 h-6 text-primary" />
          <span>Physical Copy Inventory</span>
        </h1>
        <p class="text-xs text-neutral-muted mt-1">Manage physical book items, barcode identifiers, track shelf status, and print labels.</p>
      </div>
      <button
        @click="printLabels"
        class="inline-flex items-center space-x-2 px-4 py-2.5 rounded-xl bg-white hover:bg-neutral-background text-neutral-black font-bold text-xs border border-neutral-ivory shadow-sm transition-all shrink-0"
      >
        <Printer class="w-4 h-4 text-primary" />
        <span>Print Barcode Labels</span>
      </button>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-neutral-ivory shadow-soft space-y-3">
      <div class="flex flex-col md:flex-row gap-3">
        <div class="relative flex-1">
          <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-muted" />
          <input
            v-model="searchQuery"
            @keyup.enter="handleSearch"
            type="text"
            placeholder="Search by barcode, accession number, or book title..."
            class="w-full pl-10 pr-4 py-2 bg-neutral-background border border-neutral-ivory rounded-xl text-neutral-black placeholder-neutral-muted text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <div class="flex items-center gap-2">
          <select
            v-model="statusFilter"
            @change="handleStatusChange"
            class="px-3 py-2 bg-neutral-background border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          >
            <option value="">All Statuses</option>
            <option value="available">Available</option>
            <option value="checked_out">Checked Out</option>
            <option value="reserved">Reserved</option>
            <option value="maintenance">Maintenance</option>
            <option value="lost">Lost</option>
          </select>

          <select
            v-model.number="perPage"
            @change="handlePerPageChange"
            class="px-3 py-2 bg-neutral-background border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          >
            <option :value="10">10 / page</option>
            <option :value="25">25 / page</option>
            <option :value="50">50 / page</option>
            <option :value="100">100 / page</option>
          </select>

          <button
            @click="handleSearch"
            class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-xl shadow-sm hover:bg-primary/90 transition-colors shrink-0"
          >
            Search
          </button>

          <button
            v-if="searchQuery || statusFilter"
            @click="clearFilters"
            class="px-3 py-2 bg-neutral-background text-neutral-muted hover:text-neutral-black text-xs font-bold rounded-xl border border-neutral-ivory transition-colors shrink-0"
          >
            Clear
          </button>
        </div>
      </div>
    </div>

    <!-- Copies Table -->
    <div class="bg-white border border-neutral-ivory rounded-2xl overflow-hidden shadow-soft">
      <div class="overflow-x-auto w-full">
        <table class="w-full text-left text-xs text-neutral-black min-w-[700px]">
          <thead class="bg-neutral-background text-neutral-muted font-bold uppercase tracking-wider border-b border-neutral-ivory">
            <tr>
              <th class="p-4">Barcode</th>
              <th class="p-4">Book Title</th>
              <th class="p-4">Accession #</th>
              <th class="p-4">Condition</th>
              <th class="p-4">Status</th>
              <th class="p-4">Location</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory">
            <tr v-if="isLoading">
              <td colspan="6" class="p-8 text-center text-neutral-muted">
                <div class="flex items-center justify-center space-x-2">
                  <RefreshCw class="w-4 h-4 animate-spin text-primary" />
                  <span>Loading copy inventory...</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="copies.length === 0">
              <td colspan="6" class="p-8 text-center text-neutral-muted">
                <AlertCircle class="w-8 h-8 mx-auto mb-2 text-neutral-muted opacity-50" />
                <p class="font-semibold">No copy items found matching the current criteria.</p>
              </td>
            </tr>
            <tr v-for="copy in copies" :key="copy.id" class="hover:bg-neutral-background/60 transition-colors">
              <td class="p-4 font-mono font-bold text-primary">{{ copy.barcode }}</td>
              <td class="p-4 font-bold text-neutral-black truncate max-w-xs">{{ copy.book?.title }}</td>
              <td class="p-4 font-mono text-neutral-muted">{{ copy.accession_number }}</td>
              <td class="p-4 capitalize text-neutral-black">{{ copy.condition_label || copy.condition }}</td>
              <td class="p-4">
                <span
                  :class="[
                    'px-2.5 py-1 rounded-full text-[11px] font-bold capitalize',
                    copy.status === 'available' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-neutral-background text-neutral-muted border border-neutral-ivory'
                  ]"
                >
                  {{ copy.status_label || copy.status }}
                </span>
              </td>
              <td class="p-4 text-neutral-muted">{{ copy.location?.name || 'Main Library Shelf' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Bar -->
      <div class="p-4 border-t border-neutral-ivory bg-white flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-neutral-muted">
        <div>
          <span>Showing {{ showingRange }}</span>
        </div>

        <div class="flex items-center space-x-1">
          <button
            @click="fetchCopies(currentPage - 1)"
            :disabled="currentPage <= 1 || isLoading"
            class="px-3 py-1.5 rounded-lg border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 disabled:cursor-not-allowed flex items-center space-x-1 font-semibold"
          >
            <ChevronLeft class="w-3.5 h-3.5" />
            <span>Prev</span>
          </button>

          <span class="px-3 py-1 font-mono font-bold text-neutral-black">
            Page {{ currentPage }} of {{ lastPage }}
          </span>

          <button
            @click="fetchCopies(currentPage + 1)"
            :disabled="currentPage >= lastPage || isLoading"
            class="px-3 py-1.5 rounded-lg border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 disabled:cursor-not-allowed flex items-center space-x-1 font-semibold"
          >
            <span>Next</span>
            <ChevronRight class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
