<script setup lang="ts">
import { ref } from 'vue';
import { Calendar } from 'lucide-vue-next';

const props = defineProps<{
  period: 'today' | '7d' | '30d' | '90d' | 'this_year' | 'custom';
  startDate?: string;
  endDate?: string;
}>();

const emit = defineEmits<{
  (e: 'change', payload: { period: string; startDate?: string; endDate?: string }): void;
}>();

const selectedPeriod = ref(props.period);
const customStart = ref(props.startDate || '');
const customEnd = ref(props.endDate || '');
const showCustomModal = ref(false);

const periodOptions = [
  { id: 'today', label: 'Today' },
  { id: '7d', label: '7 Days' },
  { id: '30d', label: '30 Days' },
  { id: '90d', label: '90 Days' },
  { id: 'this_year', label: 'This Year' },
  { id: 'custom', label: 'Custom' },
];

const selectPeriod = (id: string) => {
  selectedPeriod.value = id as any;
  if (id === 'custom') {
    showCustomModal.value = true;
  } else {
    emit('change', { period: id });
  }
};

const applyCustom = () => {
  if (customStart.value && customEnd.value) {
    showCustomModal.value = false;
    emit('change', {
      period: 'custom',
      startDate: customStart.value,
      endDate: customEnd.value,
    });
  }
};
</script>

<template>
  <div class="relative flex items-center gap-1.5 bg-neutral-background/70 p-1.5 rounded-2xl border border-neutral-ivory text-xs font-semibold text-neutral-black shadow-soft">
    <div class="flex items-center gap-1.5 px-2.5 text-neutral-muted">
      <Calendar class="w-3.5 h-3.5 text-primary" />
      <span class="hidden sm:inline font-bold">Timeframe:</span>
    </div>

    <div class="flex items-center gap-1 overflow-x-auto">
      <button
        v-for="opt in periodOptions"
        :key="opt.id"
        type="button"
        @click="selectPeriod(opt.id)"
        :class="[
          'px-3 py-1.5 rounded-xl transition-all whitespace-nowrap cursor-pointer',
          selectedPeriod === opt.id
            ? 'bg-primary text-white shadow-soft font-bold'
            : 'text-neutral-muted hover:text-neutral-black hover:bg-white'
        ]"
      >
        {{ opt.label }}
      </button>
    </div>

    <!-- Custom Modal Popup -->
    <div v-if="showCustomModal" class="absolute right-0 top-12 z-50 p-4 bg-white border border-neutral-ivory rounded-2xl shadow-soft space-y-3 w-64">
      <div class="text-xs font-bold text-primary mb-2">Select Custom Date Range</div>
      <div>
        <label class="block text-[10px] uppercase text-neutral-muted font-bold mb-1">Start Date</label>
        <input v-model="customStart" type="date" class="w-full bg-neutral-background border border-neutral-ivory rounded-xl px-2.5 py-1.5 text-xs text-neutral-black" />
      </div>
      <div>
        <label class="block text-[10px] uppercase text-neutral-muted font-bold mb-1">End Date</label>
        <input v-model="customEnd" type="date" class="w-full bg-neutral-background border border-neutral-ivory rounded-xl px-2.5 py-1.5 text-xs text-neutral-black" />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" @click="showCustomModal = false" class="px-3 py-1 text-neutral-muted hover:text-neutral-black text-xs font-semibold cursor-pointer">Cancel</button>
        <button type="button" @click="applyCustom" class="px-3 py-1 bg-primary hover:bg-primary-hover text-white rounded-lg text-xs font-bold cursor-pointer shadow-soft">Apply</button>
      </div>
    </div>
  </div>
</template>
