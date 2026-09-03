<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { sponsorshipService } from '@/services/sponsorship.service';
import { FileSignature, Plus, Search, ExternalLink } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useToastStore } from '@/components/feedback/toast';

const router = useRouter();
const toast = useToastStore();
const loading = ref(true);
const deals = ref<any[]>([]);
const organizations = ref<any[]>([]);
const search = ref('');
const isModalOpen = ref(false);
const submitting = ref(false);

const newDeal = ref({
  organization_id: '',
  title: '',
  sponsorship_type: 'corporate',
  status: 'pledged',
  committed_amount: 500,
  start_date: new Date().toISOString().split('T')[0],
  notes: '',
});

const loadDeals = async () => {
  loading.value = true;
  try {
    const res = await sponsorshipService.getSponsorships({ search: search.value });
    if (res.success) {
      deals.value = res.data || [];
    }
  } catch (err) {
    console.error('Failed to load deals:', err);
  } finally {
    loading.value = false;
  }
};

const openCreateModal = async () => {
  isModalOpen.value = true;
  try {
    const res = await sponsorshipService.getOrganizations();
    if (res.success) {
      organizations.value = res.data || [];
      if (organizations.value.length > 0 && !newDeal.value.organization_id) {
        newDeal.value.organization_id = organizations.value[0].id;
      }
    }
  } catch (err) {
    console.error('Failed to load organizations:', err);
  }
};

const handleCreateDeal = async () => {
  if (!newDeal.value.organization_id || !newDeal.value.title) {
    toast.error('Please fill in required fields.');
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      organization_id: Number(newDeal.value.organization_id),
      title: newDeal.value.title,
      sponsorship_type: newDeal.value.sponsorship_type,
      status: newDeal.value.status,
      total_committed_cents: Math.round(Number(newDeal.value.committed_amount) * 100),
      start_date: newDeal.value.start_date,
      notes: newDeal.value.notes,
    };

    const res = await sponsorshipService.createSponsorship(payload);
    if (res.success) {
      toast.success('Sponsorship deal created successfully.');
      isModalOpen.value = false;
      if (res.data?.uuid) {
        router.push(`/sponsorship/admin/sponsorships/${res.data.uuid}`);
      } else {
        loadDeals();
      }
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to create deal.');
  } finally {
    submitting.value = false;
  }
};

onMounted(() => {
  loadDeals();
});

const formatCurrency = (cents: number) => {
  return new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency: 'CAD',
    maximumFractionDigits: 2,
  }).format((cents || 0) / 100);
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-neutral-black tracking-tight">Sponsorship Deals & Contracts</h1>
        <p class="text-xs text-neutral-muted mt-1">Manage partner commitments, executed agreements, and contract statuses.</p>
      </div>

      <Button variant="primary" @click="openCreateModal">
        <Plus class="w-4 h-4 mr-2" />
        New Deal
      </Button>
    </div>

    <!-- Search Bar -->
    <div class="bg-white p-4 rounded-2xl border border-neutral-ivory flex gap-4 shadow-soft">
      <div class="relative flex-1">
        <Search class="w-4 h-4 absolute left-3.5 top-3 text-neutral-muted" />
        <input
          v-model="search"
          @input="loadDeals"
          type="text"
          placeholder="Search by deal number, title, or sponsor legal name..."
          class="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-neutral-ivory focus:outline-none focus:border-primary"
        />
      </div>
    </div>

    <!-- Deals Table -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary border-t-transparent"></div>
    </div>

    <div v-else-if="deals.length === 0" class="bg-white rounded-2xl p-12 text-center text-neutral-muted border border-neutral-ivory text-xs">
      No sponsorship deals found.
    </div>

    <div v-else class="bg-white rounded-2xl border border-neutral-ivory shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-neutral-background border-b border-neutral-ivory text-[10px] uppercase font-bold text-neutral-muted tracking-wider">
              <th class="py-3 px-4">Deal Number & Title</th>
              <th class="py-3 px-4">Sponsor</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Financial Status</th>
              <th class="py-3 px-4">Committed Value</th>
              <th class="py-3 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory text-xs">
            <tr v-for="d in deals" :key="d.uuid" class="hover:bg-neutral-background/50">
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-3">
                  <div class="h-8 w-8 bg-primary/10 rounded-lg flex items-center justify-center text-primary font-bold text-xs shrink-0">
                    <FileSignature class="w-4 h-4" />
                  </div>
                  <div>
                    <span class="font-bold text-neutral-black block">{{ d.title }}</span>
                    <span class="text-[10px] font-mono text-neutral-muted block">{{ d.sponsorship_number }}</span>
                  </div>
                </div>
              </td>

              <td class="py-3.5 px-4 font-bold text-neutral-black">
                {{ d.organization?.display_name || 'Organization' }}
              </td>

              <td class="py-3.5 px-4">
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-50 text-emerald-700">
                  {{ d.status }}
                </span>
              </td>

              <td class="py-3.5 px-4">
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-blue-50 text-blue-700">
                  {{ d.financial_status }}
                </span>
              </td>

              <td class="py-3.5 px-4 font-extrabold text-primary">
                {{ formatCurrency(d.total_committed_cents) }}
              </td>

              <td class="py-3.5 px-4 text-right">
                <button
                  @click="router.push(`/sponsorship/admin/sponsorships/${d.uuid}`)"
                  class="text-xs font-bold text-primary hover:underline cursor-pointer flex items-center justify-end gap-1 ml-auto"
                >
                  <span>Open Deal</span>
                  <ExternalLink class="w-3 h-3" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Deal Modal -->
    <div v-if="isModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 space-y-6 shadow-2xl">
        <h2 class="text-lg font-bold text-neutral-black">Create Sponsorship Deal</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Partner Organization *</label>
            <select v-model="newDeal.organization_id" required class="w-full px-3 py-2 border rounded-xl text-xs">
              <option v-for="org in organizations" :key="org.id" :value="org.id">
                {{ org.display_name }} ({{ org.legal_name }})
              </option>
            </select>
            <p v-if="organizations.length === 0" class="text-[11px] text-amber-600 mt-1">
              No organizations found. Please add an organization in CRM first.
            </p>
          </div>

          <div>
            <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Deal Title *</label>
            <input v-model="newDeal.title" type="text" required placeholder="e.g. 2026 Annual Sponsorship" class="w-full px-3 py-2 border rounded-xl text-xs" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Committed Amount ($ CAD) *</label>
              <input v-model.number="newDeal.committed_amount" type="number" min="0" step="10" required class="w-full px-3 py-2 border rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Start Date *</label>
              <input v-model="newDeal.start_date" type="date" required class="w-full px-3 py-2 border rounded-xl text-xs" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Notes / Terms Summary</label>
            <textarea v-model="newDeal.notes" rows="3" placeholder="Additional details..." class="w-full px-3 py-2 border rounded-xl text-xs resize-none"></textarea>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t">
          <Button variant="outline" @click="isModalOpen = false">Cancel</Button>
          <Button variant="primary" :disabled="submitting" @click="handleCreateDeal">
            {{ submitting ? 'Creating...' : 'Create Deal' }}
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
