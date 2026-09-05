<template>
  <div class="space-y-6 max-w-4xl">
    <div>
      <h1 class="text-2xl font-display font-bold text-neutral-black">Library System Settings</h1>
      <p class="text-neutral-muted text-sm mt-1">Configure global circulation rules, borrowing limits, grace periods, and guest access policy.</p>
    </div>

    <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft space-y-6">
      <div v-if="loading" class="text-neutral-muted text-center py-6">Loading settings...</div>
      <form v-else @submit.prevent="saveSettings" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-xs font-bold text-neutral-muted uppercase mb-1">Max Active Loans Per Member</label>
            <input
              v-model.number="form.max_loans_per_member"
              type="number"
              min="1"
              max="20"
              required
              class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm shadow-sm"
            />
            <p class="text-xs text-neutral-muted mt-1">Maximum concurrent books a member may hold.</p>
          </div>

          <div>
            <label class="block text-xs font-bold text-neutral-muted uppercase mb-1">Standard Loan Duration (Days)</label>
            <input
              v-model.number="form.loan_duration_days"
              type="number"
              min="1"
              max="90"
              required
              class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm shadow-sm"
            />
            <p class="text-xs text-neutral-muted mt-1">Default borrowing period in calendar days.</p>
          </div>

          <div>
            <label class="block text-xs font-bold text-neutral-muted uppercase mb-1">Grace Period (Days)</label>
            <input
              v-model.number="form.grace_period_days"
              type="number"
              min="0"
              max="14"
              required
              class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm shadow-sm"
            />
            <p class="text-xs text-neutral-muted mt-1">Days after due date before overdue status flags trigger.</p>
          </div>

          <div>
            <label class="block text-xs font-bold text-neutral-muted uppercase mb-1">Max Renewals Allowed</label>
            <input
              v-model.number="form.max_renewals"
              type="number"
              min="0"
              max="10"
              required
              class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm shadow-sm"
            />
            <p class="text-xs text-neutral-muted mt-1">Max consecutive self-service renewals permitted.</p>
          </div>

          <div>
            <label class="block text-xs font-bold text-neutral-muted uppercase mb-1">Overdue Fine Rate ($ / Day)</label>
            <input
              v-model.number="form.fine_rate_per_day"
              type="number"
              step="0.01"
              min="0"
              required
              class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 font-mono text-sm shadow-sm"
            />
            <p class="text-xs text-neutral-muted mt-1">Daily fine assessment for overdue items.</p>
          </div>

          <div>
            <label class="block text-xs font-bold text-neutral-muted uppercase mb-1">Hold Reservation Expiration (Days)</label>
            <input
              v-model.number="form.hold_expiration_days"
              type="number"
              min="1"
              max="30"
              required
              class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm shadow-sm"
            />
            <p class="text-xs text-neutral-muted mt-1">Days a member has to pick up a fulfilled hold item.</p>
          </div>
        </div>

        <div class="pt-4 border-t border-neutral-ivory flex items-center justify-between">
          <div class="flex items-center gap-3">
            <input
              v-model="form.guest_borrowing_enabled"
              type="checkbox"
              id="guest_borrowing"
              class="w-4 h-4 rounded border-neutral-ivory text-primary focus:ring-primary/20"
            />
            <label for="guest_borrowing" class="text-xs font-bold text-neutral-black">
              Enable Staff-Assisted Guest Borrowing
            </label>
          </div>

          <button
            type="submit"
            :disabled="saving"
            class="px-6 py-2.5 bg-primary hover:bg-primary/90 text-white font-bold rounded-xl transition-colors text-sm shadow-soft disabled:opacity-50"
          >
            {{ saving ? 'Saving Changes...' : 'Save Configuration' }}
          </button>
        </div>

        <div v-if="successMsg" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-bold">
          {{ successMsg }}
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';

const loading = ref(true);
const saving = ref(false);
const successMsg = ref('');

const form = ref({
  max_loans_per_member: 5,
  loan_duration_days: 14,
  grace_period_days: 2,
  max_renewals: 2,
  fine_rate_per_day: 0.5,
  hold_expiration_days: 3,
  guest_borrowing_enabled: true,
});

const loadSettings = async () => {
  loading.value = true;
  try {
    const res = await mlibmsAdminService.getSettings();
    if (res && res.data) {
      form.value = { ...form.value, ...res.data };
    }
  } catch (err) {
    console.error('Failed to load settings', err);
  } finally {
    loading.value = false;
  }
};

const saveSettings = async () => {
  saving.value = true;
  successMsg.value = '';
  try {
    await mlibmsAdminService.updateSettings(form.value);
    successMsg.value = 'Library system settings updated successfully.';
  } catch (err) {
    alert('Failed to update settings.');
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  loadSettings();
});
</script>

