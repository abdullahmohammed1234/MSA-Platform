<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { sponsorshipService, type Organization } from '@/services/sponsorship.service';
import { Building2, Plus, Search, Mail, Phone, ExternalLink } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useToastStore } from '@/components/feedback/toast';

const router = useRouter();
const toast = useToastStore();
const loading = ref(true);
const organizations = ref<Organization[]>([]);
const search = ref('');
const isModalOpen = ref(false);

const newOrg = ref({
  legal_name: '',
  display_name: '',
  relationship_type: 'sponsor',
  email: '',
  phone: '',
  industry: '',
  website_url: '',
});

const loadOrganizations = async () => {
  loading.value = true;
  try {
    const res = await sponsorshipService.getOrganizations({ search: search.value });
    if (res.success) {
      organizations.value = res.data || [];
    }
  } catch (err) {
    console.error('Failed to load organizations:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadOrganizations();
});

const handleCreate = async () => {
  try {
    const res = await sponsorshipService.createOrganization(newOrg.value);
    if (res.success) {
      toast.success('Organization added to CRM.');
      isModalOpen.value = false;
      loadOrganizations();
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to create organization.');
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-neutral-black tracking-tight">Sponsors & Partners CRM</h1>
        <p class="text-xs text-neutral-muted mt-1">Directory of corporate, community, media, and in-kind partners.</p>
      </div>

      <Button variant="primary" @click="isModalOpen = true">
        <Plus class="w-4 h-4 mr-2" />
        Add Organization
      </Button>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-neutral-ivory flex gap-4 shadow-soft">
      <div class="relative flex-1">
        <Search class="w-4 h-4 absolute left-3.5 top-3 text-neutral-muted" />
        <input
          v-model="search"
          @input="loadOrganizations"
          type="text"
          placeholder="Search by legal name, display name, email, or industry..."
          class="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-neutral-ivory focus:outline-none focus:border-primary"
        />
      </div>
    </div>

    <!-- Organizations Table -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary border-t-transparent"></div>
      <p class="mt-3 text-xs text-neutral-muted uppercase tracking-widest font-bold">Loading CRM Roster...</p>
    </div>

    <div v-else-if="organizations.length === 0" class="bg-white rounded-2xl p-12 text-center text-neutral-muted border border-neutral-ivory text-xs">
      No organizations found. Click "Add Organization" to create one.
    </div>

    <div v-else class="bg-white rounded-2xl border border-neutral-ivory shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-neutral-background border-b border-neutral-ivory text-[10px] uppercase font-bold text-neutral-muted tracking-wider">
              <th class="py-3 px-4">Organization</th>
              <th class="py-3 px-4">Type</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Contact</th>
              <th class="py-3 px-4">Account Owner</th>
              <th class="py-3 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory text-xs">
            <tr v-for="org in organizations" :key="org.uuid" class="hover:bg-neutral-background/50">
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-3">
                  <div class="h-8 w-8 bg-primary/10 rounded-lg flex items-center justify-center text-primary font-bold text-xs shrink-0">
                    <Building2 class="w-4 h-4" />
                  </div>
                  <div>
                    <span class="font-bold text-neutral-black block">{{ org.display_name }}</span>
                    <span class="text-[10px] text-neutral-muted block">{{ org.legal_name }}</span>
                  </div>
                </div>
              </td>

              <td class="py-3.5 px-4">
                <span class="text-[10px] font-mono uppercase bg-neutral-ivory px-2 py-0.5 rounded text-neutral-black">
                  {{ org.relationship_type }}
                </span>
              </td>

              <td class="py-3.5 px-4">
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-50 text-emerald-700">
                  {{ org.status }}
                </span>
              </td>

              <td class="py-3.5 px-4">
                <div class="space-y-0.5 text-[11px]">
                  <div v-if="org.email" class="flex items-center gap-1 text-neutral-black">
                    <Mail class="w-3 h-3 text-neutral-muted" />
                    <span>{{ org.email }}</span>
                  </div>
                  <div v-if="org.phone" class="flex items-center gap-1 text-neutral-muted">
                    <Phone class="w-3 h-3 text-neutral-muted" />
                    <span>{{ org.phone }}</span>
                  </div>
                </div>
              </td>

              <td class="py-3.5 px-4 text-neutral-muted text-[11px]">
                {{ org.account_owner?.name || 'Unassigned' }}
              </td>

              <td class="py-3.5 px-4 text-right">
                <button
                  @click="router.push(`/sponsorship/admin/organizations/${org.uuid}`)"
                  class="text-xs font-bold text-primary hover:underline cursor-pointer flex items-center justify-end gap-1 ml-auto"
                >
                  <span>Profile</span>
                  <ExternalLink class="w-3 h-3" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Organization Modal -->
    <div v-if="isModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 space-y-6 shadow-2xl">
        <h2 class="text-lg font-bold text-neutral-black">Add Partner Organization</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Display Name *</label>
            <input v-model="newOrg.display_name" type="text" required class="w-full px-3 py-2 border rounded-xl text-xs" />
          </div>

          <div>
            <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Legal Name *</label>
            <input v-model="newOrg.legal_name" type="text" required class="w-full px-3 py-2 border rounded-xl text-xs" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Relationship Type</label>
              <select v-model="newOrg.relationship_type" class="w-full px-3 py-2 border rounded-xl text-xs">
                <option value="sponsor">Corporate Sponsor</option>
                <option value="in_kind_partner">In-Kind Partner</option>
                <option value="community_partner">Community Partner</option>
                <option value="media_partner">Media Partner</option>
                <option value="vendor_partner">Vendor Partner</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Industry</label>
              <input v-model="newOrg.industry" type="text" class="w-full px-3 py-2 border rounded-xl text-xs" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Email</label>
              <input v-model="newOrg.email" type="email" class="w-full px-3 py-2 border rounded-xl text-xs" />
            </div>
            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Phone</label>
              <input v-model="newOrg.phone" type="tel" class="w-full px-3 py-2 border rounded-xl text-xs" />
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t">
          <Button variant="outline" @click="isModalOpen = false">Cancel</Button>
          <Button variant="primary" @click="handleCreate">Save Organization</Button>
        </div>
      </div>
    </div>
  </div>
</template>
