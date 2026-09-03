<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { sponsorshipService } from '@/services/sponsorship.service';
import { Building2, ArrowLeft, Mail, Plus, MessageSquare } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useToastStore } from '@/components/feedback/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const loading = ref(true);
const org = ref<any>(null);

const isContactModalOpen = ref(false);
const isCommModalOpen = ref(false);

const newContact = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  title: '',
  is_primary: false,
});

const newComm = ref({
  interaction_type: 'email',
  subject: '',
  body: '',
});

const loadOrganization = async () => {
  loading.value = true;
  try {
    const res = await sponsorshipService.getOrganizationDetails(route.params.uuid as string);
    if (res.success) {
      org.value = res.data;
    }
  } catch (err) {
    console.error('Failed to load organization detail:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadOrganization();
});

const handleAddContact = async () => {
  try {
    const res = await sponsorshipService.addContact(org.value.uuid, newContact.value);
    if (res.success) {
      toast.success('Contact added.');
      isContactModalOpen.value = false;
      loadOrganization();
    }
  } catch (err: any) {
    toast.error('Failed to add contact.');
  }
};

const handleLogComm = async () => {
  try {
    const res = await sponsorshipService.logCommunication(org.value.uuid, newComm.value);
    if (res.success) {
      toast.success('Communication logged.');
      isCommModalOpen.value = false;
      loadOrganization();
    }
  } catch (err: any) {
    toast.error('Failed to log communication.');
  }
};
</script>

<template>
  <div class="space-y-6">
    <button
      @click="router.push('/sponsorship/admin/organizations')"
      class="text-xs font-bold text-neutral-muted hover:text-primary flex items-center gap-1.5 cursor-pointer"
    >
      <ArrowLeft class="w-4 h-4" />
      <span>Back to CRM Directory</span>
    </button>

    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary border-t-transparent"></div>
    </div>

    <div v-else-if="org" class="space-y-6">
      <!-- Organization Header Card -->
      <div class="bg-white rounded-3xl p-6 border border-neutral-ivory shadow-soft flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="h-14 w-14 bg-primary/10 rounded-2xl flex items-center justify-center text-primary font-bold text-xl shrink-0">
            <Building2 class="w-7 h-7" />
          </div>
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-bold text-neutral-black">{{ org.display_name }}</h1>
              <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-50 text-emerald-700">
                {{ org.status }}
              </span>
            </div>
            <p class="text-xs text-neutral-muted mt-0.5">{{ org.legal_name }} • {{ org.relationship_type }}</p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <Button variant="outline" @click="isCommModalOpen = true">
            <MessageSquare class="w-4 h-4 mr-2" />
            Log Interaction
          </Button>
          <Button variant="primary" @click="isContactModalOpen = true">
            <Plus class="w-4 h-4 mr-2" />
            Add Contact
          </Button>
        </div>
      </div>

      <!-- Contacts & History Split Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Contacts Column -->
        <div class="bg-white rounded-2xl border border-neutral-ivory p-6 shadow-soft space-y-4">
          <h2 class="text-base font-bold text-neutral-black border-b border-neutral-ivory pb-3">Contacts & Representatives</h2>

          <div v-if="!org.contacts || org.contacts.length === 0" class="text-xs text-neutral-muted py-4 text-center">
            No contacts recorded.
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="c in org.contacts"
              :key="c.uuid"
              class="p-3.5 bg-neutral-background rounded-xl border border-neutral-ivory space-y-1"
            >
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-neutral-black">{{ c.first_name }} {{ c.last_name }}</span>
                <span v-if="c.is_primary" class="text-[9px] font-bold uppercase bg-primary/10 text-primary px-1.5 py-0.5 rounded">Primary</span>
              </div>
              <span v-if="c.title" class="text-[10px] text-neutral-muted block">{{ c.title }}</span>
              <div class="flex items-center gap-2 text-[11px] text-neutral-muted mt-2">
                <Mail class="w-3 h-3" />
                <span>{{ c.email }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Deal History & Communication Logs -->
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-2xl border border-neutral-ivory p-6 shadow-soft space-y-4">
            <h2 class="text-base font-bold text-neutral-black border-b border-neutral-ivory pb-3">Sponsorship Deals History</h2>

            <div v-if="!org.sponsorships || org.sponsorships.length === 0" class="text-xs text-neutral-muted py-4 text-center">
              No active or past sponsorship deals.
            </div>

            <div v-else class="divide-y divide-neutral-ivory">
              <div
                v-for="deal in org.sponsorships"
                :key="deal.uuid"
                class="py-3 flex items-center justify-between cursor-pointer hover:bg-neutral-background/50 px-2 rounded-lg"
                @click="router.push(`/sponsorship/admin/sponsorships/${deal.uuid}`)"
              >
                <div>
                  <span class="text-xs font-bold text-neutral-black block">{{ deal.title }}</span>
                  <span class="text-[10px] text-neutral-muted">{{ deal.sponsorship_number }}</span>
                </div>
                <span class="text-xs font-bold text-primary">${{ (deal.total_committed_cents / 100).toFixed(2) }} CAD</span>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-neutral-ivory p-6 shadow-soft space-y-4">
            <h2 class="text-base font-bold text-neutral-black border-b border-neutral-ivory pb-3">Communication Timeline</h2>

            <div v-if="!org.communications || org.communications.length === 0" class="text-xs text-neutral-muted py-4 text-center">
              No communication logs recorded yet.
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="comm in org.communications"
                :key="comm.id"
                class="p-3.5 bg-neutral-background rounded-xl border border-neutral-ivory text-xs space-y-1"
              >
                <div class="flex items-center justify-between">
                  <span class="font-bold text-neutral-black">{{ comm.subject }}</span>
                  <span class="text-[10px] font-mono text-neutral-muted">{{ new Date(comm.interaction_at).toLocaleDateString() }}</span>
                </div>
                <p class="text-neutral-muted">{{ comm.body }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Contact Modal -->
      <div v-if="isContactModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
          <h2 class="text-lg font-bold text-neutral-black">Add Organization Representative</h2>

          <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">First Name *</label>
                <input v-model="newContact.first_name" type="text" required class="w-full px-3 py-2 border rounded-xl text-xs" />
              </div>
              <div>
                <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Last Name *</label>
                <input v-model="newContact.last_name" type="text" required class="w-full px-3 py-2 border rounded-xl text-xs" />
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Email *</label>
              <input v-model="newContact.email" type="email" required class="w-full px-3 py-2 border rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Job Title / Role</label>
              <input v-model="newContact.title" type="text" class="w-full px-3 py-2 border rounded-xl text-xs" />
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <Button variant="outline" @click="isContactModalOpen = false">Cancel</Button>
            <Button variant="primary" @click="handleAddContact">Save Contact</Button>
          </div>
        </div>
      </div>

      <!-- Log Communication Modal -->
      <div v-if="isCommModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
          <h2 class="text-lg font-bold text-neutral-black">Log Interaction / Notes</h2>

          <div class="space-y-3">
            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Interaction Type</label>
              <select v-model="newComm.interaction_type" class="w-full px-3 py-2 border rounded-xl text-xs">
                <option value="email">Email</option>
                <option value="call">Phone Call</option>
                <option value="meeting">In-Person Meeting</option>
                <option value="note">Internal Note</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Subject *</label>
              <input v-model="newComm.subject" type="text" required class="w-full px-3 py-2 border rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-bold uppercase text-neutral-muted mb-1">Summary / Notes *</label>
              <textarea v-model="newComm.body" rows="3" required class="w-full px-3 py-2 border rounded-xl text-xs"></textarea>
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <Button variant="outline" @click="isCommModalOpen = false">Cancel</Button>
            <Button variant="primary" @click="handleLogComm">Log Communication</Button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
