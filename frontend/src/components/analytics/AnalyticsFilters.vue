<template>
  <div class="bg-white border border-neutral-ivory p-4 rounded-xl shadow-soft flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div class="flex flex-wrap items-center gap-3">
      <!-- Start Date -->
      <div class="flex flex-col">
        <label class="text-[9px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1">Start Date</label>
        <input 
          type="date" 
          v-model="localStart"
          @change="emitDates"
          class="border border-neutral-ivory bg-neutral-background px-3 py-1.5 rounded-lg text-xs font-mono text-neutral-black focus:outline-none focus:ring-1 focus:ring-primary"
        />
      </div>

      <!-- End Date -->
      <div class="flex flex-col">
        <label class="text-[9px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1">End Date</label>
        <input 
          type="date" 
          v-model="localEnd"
          @change="emitDates"
          class="border border-neutral-ivory bg-neutral-background px-3 py-1.5 rounded-lg text-xs font-mono text-neutral-black focus:outline-none focus:ring-1 focus:ring-primary"
        />
      </div>
    </div>

    <!-- Export Action Buttons -->
    <div class="flex flex-wrap items-center gap-2">
      <button 
        @click="exportData('csv')"
        class="px-4 py-2 bg-neutral-background border border-neutral-ivory text-neutral-black font-semibold text-xs rounded-xl shadow-soft hover:bg-neutral-ivory hover:-translate-y-0.5 transition-all cursor-pointer"
      >
        Export CSV
      </button>
      <button 
        @click="exportData('pdf')"
        class="px-4 py-2 bg-primary text-white font-semibold text-xs rounded-xl shadow-soft hover:bg-primary/95 hover:-translate-y-0.5 transition-all cursor-pointer"
      >
        Export PDF Report
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
  start: string;
  end: string;
}>();

const emit = defineEmits<{
  (e: 'update:dates', payload: { start: string; end: string }): void;
  (e: 'export', format: 'csv' | 'pdf'): void;
}>();

const localStart = ref(props.start);
const localEnd = ref(props.end);

const emitDates = () => {
  emit('update:dates', { start: localStart.value, end: localEnd.value });
};

const exportData = (format: 'csv' | 'pdf') => {
  emit('export', format);
};
</script>
