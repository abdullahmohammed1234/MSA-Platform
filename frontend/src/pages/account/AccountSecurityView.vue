<script setup lang="ts">
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { accountService } from '@/services/account/accountService';
import { Shield, CheckCircle2, AlertCircle, Lock } from 'lucide-vue-next';

const authStore = useAuthStore();

const currentPassword = ref<string>('');
const newPassword = ref<string>('');
const newPasswordConfirmation = ref<string>('');

const isSubmitting = ref<boolean>(false);
const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const handleUpdatePassword = async () => {
  isSubmitting.value = true;
  successMessage.value = null;
  errorMessage.value = null;

  if (newPassword.value !== newPasswordConfirmation.value) {
    errorMessage.value = 'The new password confirmation does not match.';
    isSubmitting.value = false;
    return;
  }

  try {
    const response = await accountService.updatePassword({
      current_password: currentPassword.value,
      new_password: newPassword.value,
      new_password_confirmation: newPasswordConfirmation.value,
    });

    // Update stored session token with fresh token returned by backend
    if (response.token) {
      authStore.token = response.token;
      localStorage.setItem('auth_token', response.token);
    }

    successMessage.value = 'Password updated successfully!';
    currentPassword.value = '';
    newPassword.value = '';
    newPasswordConfirmation.value = '';
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.errors?.current_password?.[0] || 'Failed to update password.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
      <div class="flex items-center gap-3">
        <Shield class="h-6 w-6 text-primary" />
        <div>
          <h2 class="text-lg font-display font-black text-primary">Password & Security</h2>
          <p class="text-xs text-neutral-black/60 font-medium">Update your account password securely.</p>
        </div>
      </div>
    </div>

    <!-- Alerts -->
    <div v-if="successMessage" class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl p-4 text-xs font-bold flex items-center gap-2">
      <CheckCircle2 class="h-4 w-4 text-emerald-600 shrink-0" />
      {{ successMessage }}
    </div>

    <div v-if="errorMessage" class="bg-red-50 border border-red-200 text-red-800 rounded-2xl p-4 text-xs font-bold flex items-center gap-2">
      <AlertCircle class="h-4 w-4 text-red-600 shrink-0" />
      {{ errorMessage }}
    </div>

    <!-- Password Form Card -->
    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft max-w-2xl">
      <form @submit.prevent="handleUpdatePassword" class="space-y-6">
        <div>
          <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black mb-2">
            Current Password
          </label>
          <input 
            v-model="currentPassword"
            type="password"
            required
            class="w-full px-4 py-3 rounded-2xl border border-neutral-ivory bg-neutral-background/50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-xs font-bold transition-all"
            placeholder="Enter your current password"
          />
        </div>

        <div>
          <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black mb-2">
            New Password
          </label>
          <input 
            v-model="newPassword"
            type="password"
            required
            minlength="8"
            class="w-full px-4 py-3 rounded-2xl border border-neutral-ivory bg-neutral-background/50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-xs font-bold transition-all"
            placeholder="At least 8 characters"
          />
        </div>

        <div>
          <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black mb-2">
            Confirm New Password
          </label>
          <input 
            v-model="newPasswordConfirmation"
            type="password"
            required
            minlength="8"
            class="w-full px-4 py-3 rounded-2xl border border-neutral-ivory bg-neutral-background/50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-xs font-bold transition-all"
            placeholder="Repeat new password"
          />
        </div>

        <div class="pt-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <p class="text-[10px] text-neutral-black/50 font-medium max-w-xs">
            Updating your password revokes old tokens and establishes a fresh session.
          </p>

          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-6 py-3 bg-primary text-white rounded-2xl text-xs font-extrabold uppercase tracking-wider hover:bg-secondary transition-all disabled:opacity-50 cursor-pointer shadow-soft hover:shadow-premium inline-flex items-center gap-2 shrink-0"
          >
            <Lock class="h-3.5 w-3.5" />
            {{ isSubmitting ? 'Updating...' : 'Update Password' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
