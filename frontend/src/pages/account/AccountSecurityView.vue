<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { accountService } from '@/services/account/accountService';
import { 
  Shield, 
  CheckCircle2, 
  AlertCircle, 
  Lock, 
  Laptop, 
  Smartphone, 
  KeyRound, 
  LogOut,
  ShieldAlert
} from 'lucide-vue-next';

const authStore = useAuthStore();
const router = useRouter();

const currentPassword = ref<string>('');
const newPassword = ref<string>('');
const newPasswordConfirmation = ref<string>('');

const isSubmitting = ref<boolean>(false);
const isLoggingOutAll = ref<boolean>(false);
const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const clientInfo = computed(() => {
  if (typeof window === 'undefined') return { browser: 'Modern Web Browser', os: 'Desktop Client' };
  const ua = window.navigator.userAgent;
  let browser = 'Web Browser';
  let os = 'Desktop';

  if (ua.includes('Firefox')) browser = 'Mozilla Firefox';
  else if (ua.includes('Chrome')) browser = 'Google Chrome';
  else if (ua.includes('Safari')) browser = 'Apple Safari';
  else if (ua.includes('Edge')) browser = 'Microsoft Edge';

  if (ua.includes('Windows')) os = 'Windows OS';
  else if (ua.includes('Macintosh') || ua.includes('Mac OS')) os = 'macOS';
  else if (ua.includes('Linux')) os = 'Linux OS';
  else if (ua.includes('Android')) os = 'Android Device';
  else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS Device';

  const isMobile = /Android|iPhone|iPad/i.test(ua);

  return { browser, os, isMobile };
});

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

    successMessage.value = 'Password updated successfully! Previous tokens have been revoked.';
    currentPassword.value = '';
    newPassword.value = '';
    newPasswordConfirmation.value = '';
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.errors?.current_password?.[0] || 'Failed to update password.';
  } finally {
    isSubmitting.value = false;
  }
};

const handleRevokeAllSessions = async () => {
  if (!confirm('Are you sure you want to terminate your current session and sign out of all devices?')) {
    return;
  }

  isLoggingOutAll.value = true;
  try {
    await authStore.logout();
    router.push({ name: 'login' });
  } catch {
    // Fallback direct cleanup
    localStorage.removeItem('auth_token');
    authStore.token = null;
    authStore.user = null;
    router.push({ name: 'login' });
  } finally {
    isLoggingOutAll.value = false;
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
          <p class="text-xs text-neutral-black/60 font-medium">Manage credentials, active browser sessions, and security protections.</p>
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

    <!-- Main Grid: Password Update & Active Sessions (ACCOUNT-03) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Password Form Card (2 Cols) -->
      <div class="lg:col-span-2 bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft">
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-6">
          <div class="flex items-center gap-2">
            <KeyRound class="h-4 w-4 text-primary" />
            <h3 class="text-sm font-extrabold uppercase tracking-wider text-primary">Change Password</h3>
          </div>
          <span class="text-[10px] font-extrabold text-neutral-black/50 uppercase">Credentials</span>
        </div>

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

      <!-- Active Sessions & Device Security Column (ACCOUNT-03) -->
      <div class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft flex flex-col justify-between space-y-6">
        <div>
          <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-4">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-primary">
              Session & Device
            </h3>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
              <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
              Active Now
            </span>
          </div>

          <!-- Current Device Information -->
          <div class="p-4 rounded-2xl bg-neutral-background/50 border border-neutral-ivory space-y-3">
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                <Smartphone v-if="clientInfo.isMobile" class="h-4 w-4" />
                <Laptop v-else class="h-4 w-4" />
              </div>
              <div class="min-w-0">
                <h4 class="text-xs font-extrabold text-neutral-black truncate">{{ clientInfo.browser }}</h4>
                <p class="text-[10px] text-neutral-black/50 font-medium truncate">{{ clientInfo.os }} &bull; Current Device</p>
              </div>
            </div>

            <div class="pt-3 border-t border-neutral-ivory/60 space-y-1.5 text-[10px]">
              <div class="flex justify-between text-neutral-black/60">
                <span>Auth Protocol:</span>
                <span class="font-bold text-neutral-black">Sanctum Bearer Token</span>
              </div>
              <div class="flex justify-between text-neutral-black/60">
                <span>Connection Security:</span>
                <span class="font-bold text-emerald-700">Encrypted (TLS/HTTPS)</span>
              </div>
              <div class="flex justify-between text-neutral-black/60">
                <span>Session State:</span>
                <span class="font-bold text-neutral-black">Authenticated</span>
              </div>
            </div>
          </div>

          <!-- Security Recommendations -->
          <div class="mt-4 p-4 rounded-2xl bg-primary/5 border border-primary/15 space-y-2">
            <div class="flex items-center gap-2 text-xs font-extrabold text-primary">
              <ShieldAlert class="h-4 w-4 shrink-0" />
              Security Best Practices
            </div>
            <ul class="text-[10px] text-neutral-black/70 space-y-1 font-medium list-disc list-inside">
              <li>Use a distinct password unique to SFU MSA.</li>
              <li>Sign out if accessing via public campus terminals.</li>
              <li>Changing password immediately revokes all prior tokens.</li>
            </ul>
          </div>
        </div>

        <div class="pt-4 border-t border-neutral-ivory">
          <button
            type="button"
            @click="handleRevokeAllSessions"
            :disabled="isLoggingOutAll"
            class="w-full px-4 py-2.5 rounded-2xl text-xs font-extrabold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
          >
            <LogOut class="h-3.5 w-3.5" />
            {{ isLoggingOutAll ? 'Terminating...' : 'Sign Out Of Current Session' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
