<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { sponsorshipService } from '@/services/sponsorship.service';
import { Target, Plus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();
const loading = ref(true);
const opportunities = ref<any[]>([]);
const isModalOpen = ref(false);

const newOpp = ref({
  title: '',
  opportunity_type: 'event',
  description: '',
  target_amount_cents: 0,
});

const loadOpportunities = async () => {
  loading.value = true;
  try {
    const res = await sponsorshipService.getOpportunities();
    if (res.success) {
      opportunities.value = res.data || [];
    }
  } catch (err) {
    console.error('Failed to load opportunities:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadOpportunities();
});

const handleCreate = async () => {
  try {
    const res = await sponsorshipService.createOpportunity(newOpp.value);
    if (res.success) {
      toast.success('Sponsorship Opportunity created.');
      isModalOpen.value = false;
      loadOpportunities();
    }
  } catch (err: any) {
    toast.error('Failed to create opportunity.');
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-neutral-black tracking-tight">Sponsorship Opportunities & Packages</h1>
        <p class="text-xs text-neutral-muted mt-1">Manage event opportunities, annual sponsorship drives, and package tier offerings.</p>
      </div>

      <Button variant="primary" @click="isModalOpen = true">
        <Plus class="w-4 h-4 mr-2" />
        New Opportunity
      </Button>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary border-t-transparent"></div>
    </div>

    <div v-else-if="opportunities.length === 0" class="bg-white rounded-2xl p-12 text-center text-neutral-muted border border-neutral-ivory text-xs">
      No opportunities created yet.
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div
        v-for="opp in opportunities"
        :key="opp.uuid"
        class="bg-white rounded-3xl border border-neutral-ivory p-6 shadow-soft space-y-4"
      >
        <div class="flex items-start justify-between">
          <div>
            <span class="text-[10px] font-bold uppercase bg-primary/10 text-primary px-2.5 py-0.5 rounded-full">
              {{ opp.opportunity_type }}
            </span>
            <h2 class="text-lg font-bold text-neutral-black mt-2">{{ opp.title }}</h2>
          </div>
          <Target class="w-6 h-6 text-primary" />
        </div>

        <p v-if="opp.description" class="text-xs text-neutral-muted">{{ opp.description }}</p>

        <!-- Packages Tier List -->
        <div class="pt-4 border-t border-neutral-ivory space-y-2">
          <div class="flex items-center justify-between text-xs font-bold text-neutral-black mb-2">
            <span>Package Tiers</span>
            <span class="text-[10px] text-neutral-muted font-mono">{{ opp.packages?.length || 0 }} Tiers</span>
          </div>

          <div v-if="!opp.packages || opp.packages.length === 0" class="text-[11px] text-neutral-muted italic">
            No package tiers added yet.
          </div>

          <div v-else class="space-y-2">
            <div
              v-for="pkg in opp.packages"
              :key="pkg.uuid"
              class="p-3 bg-neutral-background rounded-xl border border-neutral-ivory flex items-center justify-between text-xs"
            >
              <div>
                <span class="font-bold text-neutral-black block">{{ pkg.name }}</span>
                <span class="text-[10px] text-neutral-muted font-mono">
                  ${{ (pkg.price_cents / 100).toFixed(2) }} CAD
                </span>
              </div>
              <span class="text-[10px] bg-emerald-50 text-emerald-700 font-bold px-2 py-0.5 rounded">
                {{ pkg.claimed_count }} claimed
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Opportunity Modal -->
    <div v-if="isModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <h2 class="text-lg font-bold text-neutral-black">Create Opportunity</h2>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Opportunity Title *</label>
            <input v-model="newOpp.title" type="text" required class="w-full px-3 py-2 border rounded-xl text-xs" />
          </div>

          <div>
            <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Opportunity Type</label>
            <select v-model="newOpp.opportunity_type" class="w-full px-3 py-2 border rounded-xl text-xs">
              <option value="event">Event Sponsorship</option>
              <option value="program">Program / Service Sponsorship</option>
              <option value="annual">Annual Corporate Partnership</option>
              <option value="media">Media / Print Sponsorship</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Description</label>
            <textarea v-model="newOpp.description" rows="3" class="w-full px-3 py-2 border rounded-xl text-xs"></textarea>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t">
          <Button variant="outline" @click="isModalOpen = false">Cancel</Button>
          <Button variant="primary" @click="handleCreate">Save Opportunity</Button>
        </div>
      </div>
    </div>
  </div>
</template>
