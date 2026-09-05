<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Search, UserPlus } from 'lucide-vue-next';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const members = ref<any[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');
const showGuestModal = ref(false);

const guestForm = ref({
  name: '',
  email: '',
  phone: '',
  notes: 'Walk-in guest borrower (staff-assisted only)',
});

const fetchMembers = async () => {
  isLoading.value = true;
  try {
    const res = await mlibmsAdminService.getMembers({ search: searchQuery.value });
    members.value = res.data || [];
  } catch (e) {
    toast.error('Failed to load member roster.');
  } finally {
    isLoading.value = false;
  }
};

const handleRegisterGuest = async () => {
  if (!guestForm.value.name || !guestForm.value.email) {
    toast.error('Name and Email are required.');
    return;
  }

  try {
    await mlibmsAdminService.createGuestMember(guestForm.value);
    toast.success('Walk-in guest borrower registered!');
    showGuestModal.value = false;
    guestForm.value = { name: '', email: '', phone: '', notes: 'Walk-in guest borrower (staff-assisted only)' };
    fetchMembers();
  } catch (e: any) {
    toast.error(e.response?.data?.message || 'Failed to register guest borrower.');
  }
};

const toggleSuspension = async (member: any) => {
  const newStatus = member.status === 'suspended' ? 'active' : 'suspended';
  const reason = newStatus === 'suspended' ? 'Manual staff suspension override' : undefined;

  try {
    await mlibmsAdminService.updateMember(member.uuid, { status: newStatus, suspension_reason: reason });
    toast.success(`Member status updated to ${newStatus}.`);
    fetchMembers();
  } catch (e) {
    toast.error('Failed to update member status.');
  }
};

onMounted(fetchMembers);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black">Library Members Roster</h1>
        <p class="text-xs text-neutral-muted mt-1">Manage platform members and register walk-in guest borrowers (staff-assisted only).</p>
      </div>
      <button
        @click="showGuestModal = true"
        class="inline-flex items-center space-x-2 px-4 py-2.5 rounded-xl bg-purple-700 hover:bg-purple-800 text-white font-bold text-xs shadow-soft transition-all"
      >
        <UserPlus class="w-4 h-4" />
        <span>Register Walk-in Guest Borrower</span>
      </button>
    </div>

    <!-- Search Bar -->
    <div class="flex items-center space-x-3 bg-white p-4 rounded-2xl border border-neutral-ivory shadow-soft">
      <div class="relative flex-1">
        <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-muted" />
        <input
          v-model="searchQuery"
          @keyup.enter="fetchMembers"
          type="text"
          placeholder="Search by name, email, or card number..."
          class="w-full pl-10 pr-4 py-2 bg-neutral-background border border-neutral-ivory rounded-xl text-neutral-black placeholder-neutral-muted text-xs focus:outline-none focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20"
        />
      </div>
      <button @click="fetchMembers" class="px-4 py-2 bg-white hover:bg-neutral-background text-neutral-black text-xs font-bold rounded-xl border border-neutral-ivory shadow-sm transition-colors">
        Search
      </button>
    </div>

    <!-- Members Table -->
    <div class="bg-white border border-neutral-ivory rounded-2xl overflow-hidden shadow-soft">
      <table class="w-full text-left text-xs text-neutral-black">
        <thead class="bg-neutral-background text-neutral-muted font-bold uppercase tracking-wider border-b border-neutral-ivory">
          <tr>
            <th class="p-4">Card #</th>
            <th class="p-4">Name & Email</th>
            <th class="p-4">Type</th>
            <th class="p-4">Status</th>
            <th class="p-4 text-center">Active Loans</th>
            <th class="p-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-ivory">
          <tr v-if="isLoading">
            <td colspan="6" class="p-8 text-center text-neutral-muted">Loading member roster...</td>
          </tr>
          <tr v-else-if="members.length === 0">
            <td colspan="6" class="p-8 text-center text-neutral-muted">No members found.</td>
          </tr>
          <tr v-for="mem in members" :key="mem.id" class="hover:bg-neutral-background/60 transition-colors">
            <td class="p-4 font-mono font-bold text-purple-700">{{ mem.library_card_number }}</td>
            <td class="p-4">
              <div class="font-bold text-neutral-black">{{ mem.name }}</div>
              <div class="text-[11px] text-neutral-muted">{{ mem.email }}</div>
            </td>
            <td class="p-4">
              <span class="px-2.5 py-1 rounded-full bg-neutral-background text-neutral-black border border-neutral-ivory capitalize text-[11px] font-semibold">
                {{ mem.membership_type_label }}
                <span v-if="mem.is_guest" class="text-purple-700 ml-1 font-bold">(Guest)</span>
              </span>
            </td>
            <td class="p-4">
              <span
                :class="[
                  'px-2.5 py-1 rounded-full text-[11px] font-bold capitalize',
                  mem.status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'
                ]"
              >
                {{ mem.status }}
              </span>
            </td>
            <td class="p-4 text-center font-bold text-neutral-black">
              {{ mem.active_loans_count }} / {{ mem.max_active_loans }}
            </td>
            <td class="p-4 text-right">
              <button
                @click="toggleSuspension(mem)"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-bold border transition-all',
                  mem.status === 'suspended' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100'
                ]"
              >
                {{ mem.status === 'suspended' ? 'Reactivate' : 'Suspend' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Walk-in Guest Registration Modal -->
    <div v-if="showGuestModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white border border-neutral-ivory rounded-2xl p-6 max-w-md w-full space-y-4 shadow-soft">
        <h3 class="text-lg font-bold text-neutral-black">Register Walk-in Guest Borrower</h3>
        <p class="text-xs text-neutral-muted">Walk-in guests receive a guest card and are staff-assisted only.</p>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-neutral-muted mb-1">Full Name *</label>
            <input v-model="guestForm.name" type="text" class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black focus:outline-none focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20 shadow-sm" />
          </div>
          <div>
            <label class="block font-bold text-neutral-muted mb-1">Email Address *</label>
            <input v-model="guestForm.email" type="email" class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black focus:outline-none focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20 shadow-sm" />
          </div>
          <div>
            <label class="block font-bold text-neutral-muted mb-1">Phone Number</label>
            <input v-model="guestForm.phone" type="text" class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black focus:outline-none focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20 shadow-sm" />
          </div>
        </div>

        <div class="flex justify-end space-x-2 pt-2">
          <button @click="showGuestModal = false" class="px-4 py-2 rounded-xl bg-neutral-background hover:bg-neutral-ivory text-neutral-black text-xs font-bold transition-colors">Cancel</button>
          <button @click="handleRegisterGuest" class="px-4 py-2 rounded-xl bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold transition-colors shadow-sm">Register Guest</button>
        </div>
      </div>
    </div>
  </div>
</template>

