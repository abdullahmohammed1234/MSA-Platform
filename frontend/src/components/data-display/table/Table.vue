<script setup lang="ts">
import { ref } from 'vue';
import type { TableProps, TableColumn } from './types';

const props = withDefaults(defineProps<TableProps>(), {
  isLoading: false,
  emptyText: 'No records available.',
  currentPage: 1,
  totalPages: 1,
  totalItems: 0,
  itemsPerPage: 10
});

const emit = defineEmits<{
  (e: 'sort', payload: { key: string; order: 'asc' | 'desc' }): void;
  (e: 'page-change', page: number): void;
}>();

const sortKey = ref('');
const sortOrder = ref<'asc' | 'desc'>('asc');

const handleSort = (col: TableColumn) => {
  if (!col.sortable) return;
  if (sortKey.value === col.key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = col.key;
    sortOrder.value = 'asc';
  }
  emit('sort', { key: sortKey.value, order: sortOrder.value });
};

const setPage = (page: number) => {
  if (page < 1 || page > props.totalPages || page === props.currentPage) return;
  emit('page-change', page);
};
</script>

<template>
  <div class="w-full bg-white border border-neutral-ivory rounded-2xl shadow-soft overflow-hidden">
    <div class="w-full overflow-x-auto">
      <table class="w-full border-collapse text-left">
        <!-- Table Header -->
        <thead>
          <tr class="border-b border-neutral-ivory/60 bg-neutral-background/30 select-none">
            <th
              v-for="col in columns"
              :key="col.key"
              @click="handleSort(col)"
              :class="[
                'px-6 py-4 text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted transition-colors',
                col.sortable ? 'cursor-pointer hover:text-primary' : ''
              ]"
            >
              <div class="flex items-center gap-1.5">
                <span>{{ col.label }}</span>
                <!-- Sorting arrows -->
                <span v-if="col.sortable" class="text-neutral-gray hover:text-primary">
                  <svg
                    v-if="sortKey !== col.key"
                    class="h-3 w-3"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                  </svg>
                  <svg
                    v-else-if="sortOrder === 'asc'"
                    class="h-3 w-3 text-primary"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                  </svg>
                  <svg
                    v-else
                    class="h-3 w-3 text-primary"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                  </svg>
                </span>
              </div>
            </th>
          </tr>
        </thead>

        <!-- Table Body -->
        <tbody class="divide-y divide-neutral-ivory/40">
          
          <!-- Loading State Skeletons -->
          <template v-if="isLoading">
            <tr v-for="r in 3" :key="`loading-row-${r}`" class="animate-pulse">
              <td v-for="col in columns" :key="`loading-cell-${col.key}`" class="px-6 py-4">
                <div class="h-4 bg-neutral-ivory/50 rounded w-2/3"></div>
              </td>
            </tr>
          </template>

          <!-- Empty State -->
          <template v-else-if="items.length === 0">
            <tr>
              <td :colspan="columns.length" class="text-center px-6 py-12 text-sm text-neutral-muted font-medium">
                <slot name="empty">
                  {{ emptyText }}
                </slot>
              </td>
            </tr>
          </template>

          <!-- Standard Rows -->
          <template v-else>
            <tr
              v-for="(item, index) in items"
              :key="item.id || index"
              class="hover:bg-neutral-background/40 transition-colors duration-200"
            >
              <td
                v-for="col in columns"
                :key="col.key"
                class="px-6 py-4 text-sm text-neutral-black"
              >
                <slot :name="col.key" :item="item" :value="item[col.key]">
                  {{ item[col.key] }}
                </slot>
              </td>
            </tr>
          </template>

        </tbody>
      </table>
    </div>

    <!-- Pagination Footer -->
    <div
      v-if="totalPages > 1"
      class="px-6 py-4 border-t border-neutral-ivory/50 flex flex-col sm:flex-row items-center justify-between gap-4 bg-neutral-background/10"
    >
      <!-- Stats description -->
      <span class="text-xs text-neutral-muted">
        Showing Page {{ currentPage }} of {{ totalPages }}
        <span v-if="totalItems">({{ totalItems }} total records)</span>
      </span>

      <!-- Action items -->
      <div class="flex items-center gap-2">
        <button
          @click="setPage(currentPage - 1)"
          :disabled="currentPage === 1"
          class="px-3 py-1.5 rounded-lg border border-neutral-ivory text-xs font-semibold text-neutral-muted hover:text-primary hover:bg-neutral-background transition-colors disabled:opacity-50 disabled:pointer-events-none cursor-pointer"
        >
          Previous
        </button>
        <button
          v-for="page in totalPages"
          :key="page"
          @click="setPage(page)"
          :class="[
            'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer',
            currentPage === page
              ? 'bg-primary text-white'
              : 'border border-neutral-ivory text-neutral-muted hover:text-primary hover:bg-neutral-background'
          ]"
        >
          {{ page }}
        </button>
        <button
          @click="setPage(currentPage + 1)"
          :disabled="currentPage === totalPages"
          class="px-3 py-1.5 rounded-lg border border-neutral-ivory text-xs font-semibold text-neutral-muted hover:text-primary hover:bg-neutral-background transition-colors disabled:opacity-50 disabled:pointer-events-none cursor-pointer"
        >
          Next
        </button>
      </div>
    </div>

  </div>
</template>
