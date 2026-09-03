<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { sponsorshipService } from '@/services/sponsorship.service';
import { CheckSquare } from 'lucide-vue-next';

const loading = ref(true);
const deliverables = ref<any[]>([]);

const loadDeliverables = async () => {
  loading.value = true;
  try {
    const res = await sponsorshipService.getFulfillmentDeliverables();
    if (res.success) {
      deliverables.value = res.data || [];
    }
  } catch (err) {
    console.error('Failed to load fulfillment deliverables:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadDeliverables();
});
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-neutral-black tracking-tight">Fulfillment & Deliverables Tracking</h1>
      <p class="text-xs text-neutral-muted mt-1">Proof-of-execution tracking for logo placements, social posts, booth allocations, and VIP passes.</p>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary border-t-transparent"></div>
    </div>

    <div v-else-if="deliverables.length === 0" class="bg-white rounded-2xl p-12 text-center text-neutral-muted border border-neutral-ivory text-xs">
      No deliverable items recorded.
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="d in deliverables"
        :key="d.uuid"
        class="bg-white rounded-2xl border border-neutral-ivory p-5 shadow-soft space-y-3"
      >
        <div class="flex items-start justify-between">
          <div>
            <span class="text-[10px] font-mono uppercase bg-primary/10 text-primary px-2 py-0.5 rounded">
              {{ d.deliverable_type }}
            </span>
            <h2 class="text-sm font-bold text-neutral-black mt-2">{{ d.title }}</h2>
          </div>
          <CheckSquare class="w-5 h-5 text-primary" />
        </div>

        <p v-if="d.description" class="text-xs text-neutral-muted">{{ d.description }}</p>

        <div class="pt-3 border-t border-neutral-ivory flex items-center justify-between text-xs">
          <span class="text-neutral-muted">{{ d.sponsorship?.organization?.display_name || 'Sponsor' }}</span>
          <span class="font-bold uppercase text-[10px] px-2 py-0.5 rounded bg-emerald-50 text-emerald-700">
            {{ d.status }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
