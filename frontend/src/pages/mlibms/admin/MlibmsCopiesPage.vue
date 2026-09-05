<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Search, Printer } from 'lucide-vue-next';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const copies = ref<any[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');

const fetchCopies = async () => {
  isLoading.value = true;
  try {
    const res = await mlibmsAdminService.getCopies({ search: searchQuery.value });
    copies.value = res.data || [];
  } catch (e) {
    toast.error('Failed to load physical copy inventory.');
  } finally {
    isLoading.value = false;
  }
};

const printLabels = () => {
  window.print();
};

onMounted(fetchCopies);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black">Physical Copy Inventory</h1>
        <p class="text-xs text-neutral-muted mt-1">Manage physical book items, barcode identifiers, and print labels.</p>
      </div>
      <button
        @click="printLabels"
        class="inline-flex items-center space-x-2 px-4 py-2.5 rounded-xl bg-white hover:bg-neutral-background text-neutral-black font-bold text-xs border border-neutral-ivory shadow-sm transition-all"
      >
        <Printer class="w-4 h-4 text-primary" />
        <span>Print Barcode Labels</span>
      </button>
    </div>

    <!-- Search Bar -->
    <div class="flex items-center space-x-3 bg-white p-4 rounded-2xl border border-neutral-ivory shadow-soft">
      <div class="relative flex-1">
        <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-muted" />
        <input
          v-model="searchQuery"
          @keyup.enter="fetchCopies"
          type="text"
          placeholder="Search by barcode, accession number, or title..."
          class="w-full pl-10 pr-4 py-2 bg-neutral-background border border-neutral-ivory rounded-xl text-neutral-black placeholder-neutral-muted text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
      </div>
      <button @click="fetchCopies" class="px-4 py-2 bg-white hover:bg-neutral-background text-neutral-black text-xs font-bold rounded-xl border border-neutral-ivory shadow-sm transition-colors">
        Search
      </button>
    </div>

    <!-- Copies Table -->
    <div class="bg-white border border-neutral-ivory rounded-2xl overflow-hidden shadow-soft">
      <table class="w-full text-left text-xs text-neutral-black">
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
            <td colspan="6" class="p-8 text-center text-neutral-muted">Loading copy inventory...</td>
          </tr>
          <tr v-else-if="copies.length === 0">
            <td colspan="6" class="p-8 text-center text-neutral-muted">No copy items found.</td>
          </tr>
          <tr v-for="copy in copies" :key="copy.id" class="hover:bg-neutral-background/60 transition-colors">
            <td class="p-4 font-mono font-bold text-primary">{{ copy.barcode }}</td>
            <td class="p-4 font-bold text-neutral-black truncate max-w-xs">{{ copy.book?.title }}</td>
            <td class="p-4 font-mono text-neutral-muted">{{ copy.accession_number }}</td>
            <td class="p-4 capitalize text-neutral-black">{{ copy.condition_label }}</td>
            <td class="p-4">
              <span
                :class="[
                  'px-2.5 py-1 rounded-full text-[11px] font-bold capitalize',
                  copy.status === 'available' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-neutral-background text-neutral-muted border border-neutral-ivory'
                ]"
              >
                {{ copy.status_label }}
              </span>
            </td>
            <td class="p-4 text-neutral-muted">{{ copy.location?.name || 'Main Library Shelf' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

