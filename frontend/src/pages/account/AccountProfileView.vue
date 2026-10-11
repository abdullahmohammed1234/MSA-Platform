<script setup lang="ts">
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { accountService } from '@/services/account/accountService';
import { UserCheck, CheckCircle2, AlertCircle, RefreshCw } from 'lucide-vue-next';

const authStore = useAuthStore();

const name = ref<string>(authStore.user?.name || '');
const email = ref<string>(authStore.user?.email || '');

const isSubmitting = ref<boolean>(false);
const isResendingVerification = ref<boolean>(false);
const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const verificationMessage = ref<string | null>(null);

const handleUpdateProfile = async () => {
  isSubmitting.value = true;
  successMessage.value = null;
  errorMessage.value = null;

  try {
    const response = await accountService.updateProfile({
      name: name.value,
      email: email.value,
    });

    // Update store state with refreshed user payload
    await authStore.fetchUser();

    successMessage.value = response.message || 'Profile updated successfully.';
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.errors?.email?.[0] || 'Failed to update profile.';
  } finally {
    isSubmitting.value = false;
  }
};

const handleResendVerification = async () => {
  isResendingVerification.value = true;
  verificationMessage.value = null;
  try {
    await authStore.resendVerification();
    verificationMessage.value = 'Verification email sent! Please check your inbox.';
  } catch (err: any) {
    verificationMessage.value = err.response?.data?.message || 'Failed to send verification email.';
  } finally {
    isResendingVerification.value = false;
  }
};
</script>

<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
      <div class="flex items-center gap-3">
        <UserCheck class="h-6 w-6 text-primary" />
        <div>
          <h2 class="text-lg font-display font-black text-primary">Profile Information</h2>
          <p class="text-xs text-neutral-black/60 font-medium">Manage your display name and email address.</p>
        </div>
      </div>
    </div>

    <!-- Alert Messages -->
    <div v-if="successMessage" class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl p-4 text-xs font-bold flex items-center gap-2">
      <CheckCircle2 class="h-4 w-4 text-emerald-600 shrink-0" />
      {{ successMessage }}
    </div>

    <div v-if="errorMessage" class="bg-red-50 border border-red-200 text-red-800 rounded-2xl p-4 text-xs font-bold flex items-center gap-2">
      <AlertCircle class="h-4 w-4 text-red-600 shrink-0" />
      {{ errorMessage }}
    </div>

    <!-- Form & Verification Card -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Form Column -->
      <div class="lg:col-span-2 bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft">
        <form @submit.prevent="handleUpdateProfile" class="space-y-6">
          <div>
            <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black mb-2">
              Full Name
            </label>
            <input 
              v-model="name"
              type="text"
              required
              class="w-full px-4 py-3 rounded-2xl border border-neutral-ivory bg-neutral-background/50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-xs font-bold transition-all"
              placeholder="Your Name"
            />
          </div>

          <div>
            <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black mb-2">
              Email Address
            </label>
            <input 
              v-model="email"
              type="email"
              required
              class="w-full px-4 py-3 rounded-2xl border border-neutral-ivory bg-neutral-background/50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-xs font-bold transition-all"
              placeholder="yourname@sfu.ca"
            />
            <p v-if="authStore.isVolunteer" class="text-[10px] text-neutral-black/50 mt-1 font-medium">
              Note: Volunteers must maintain an active @sfu.ca email address.
            </p>
          </div>

          <div class="pt-4 flex items-center justify-end">
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-6 py-3 bg-primary text-white rounded-2xl text-xs font-extrabold uppercase tracking-wider hover:bg-secondary transition-all disabled:opacity-50 cursor-pointer shadow-soft hover:shadow-premium"
            >
              {{ isSubmitting ? 'Saving Changes...' : 'Save Profile Changes' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Verification Info Column -->
      <div class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft flex flex-col justify-between">
        <div>
          <h3 class="text-xs font-extrabold uppercase tracking-wider text-primary border-b border-neutral-ivory pb-3 mb-4">
            Email Verification
          </h3>

          <div v-if="authStore.isVerified" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-emerald-800 space-y-2">
            <div class="flex items-center gap-2 text-xs font-black">
              <CheckCircle2 class="h-4 w-4 text-emerald-600" />
              Email Verified
            </div>
            <p class="text-[11px] text-emerald-700 font-medium">
              Your registered email address is verified and active on the SFU MSA Platform.
            </p>
          </div>

          <div v-else class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-amber-800 space-y-3">
            <div class="flex items-center gap-2 text-xs font-black">
              <AlertCircle class="h-4 w-4 text-amber-600" />
              Unverified Email
            </div>
            <p class="text-[11px] text-amber-700 font-medium">
              Please verify your email address to unlock full member features.
            </p>

            <button
              @click="handleResendVerification"
              :disabled="isResendingVerification"
              class="w-full py-2 bg-amber-600 text-white rounded-xl text-[11px] font-bold hover:bg-amber-700 transition-all disabled:opacity-50 inline-flex items-center justify-center gap-1.5 cursor-pointer"
            >
              <RefreshCw :class="['h-3 w-3', isResendingVerification ? 'animate-spin' : '']" />
              Resend Verification Link
            </button>

            <p v-if="verificationMessage" class="text-[10px] font-bold text-amber-900 mt-1">
              {{ verificationMessage }}
            </p>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-neutral-ivory text-[10px] text-neutral-black/50">
          User UUID: <span class="font-mono text-neutral-black/70">{{ authStore.user?.uuid }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
