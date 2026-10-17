<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Account Settings"
      title="Security & Credentials"
      description="Update your account password, manage active session security, and access central SFU MSA security controls."
    >
      <template #badge>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-stone-100 border border-stone-200 text-stone-700 text-[10.5px] font-semibold">
          <KeyRound class="w-3.5 h-3.5 text-stone-500" />
          <span>Encrypted Session</span>
        </span>
      </template>

      <template #actions>
        <a
          href="https://sfumsa.ca/account"
          target="_blank"
          rel="noopener noreferrer"
        >
          <Button variant="outline" size="sm" class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs">
            <span>Central Account Hub</span>
            <template #right-icon>
              <ExternalLink class="w-3.5 h-3.5 text-[#b02e32]" />
            </template>
          </Button>
        </a>
      </template>
    </PageHero>

    <!-- Route-Based Tabs -->
    <RouteTabs :tabs="navTabs" aria-label="Profile and Security Navigation" />

    <!-- TAB CONTENT: Security & Credentials Only -->
    <div class="space-y-6">
      <!-- 1. In-App Password Change Form -->
      <Card
        title="Update Account Password"
        subtitle="Ensure your account is protected with a unique, secure password."
      >
        <form @submit.prevent="handleUpdatePassword" class="space-y-5">
          <!-- Password Success Banner -->
          <div
            v-if="passwordSuccessMessage"
            class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center justify-between gap-2"
          >
            <div class="flex items-center gap-2">
              <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
              <span class="font-medium">{{ passwordSuccessMessage }}</span>
            </div>
            <button
              type="button"
              @click="passwordSuccessMessage = null"
              class="text-emerald-700 hover:text-emerald-900 cursor-pointer"
              aria-label="Dismiss message"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Password Error Banner -->
          <div
            v-if="passwordErrorMessage"
            class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-xs text-red-800 flex items-start gap-2"
          >
            <AlertCircle class="w-4 h-4 text-red-600 shrink-0 mt-0.5" />
            <div class="space-y-0.5">
              <span class="font-bold">Unable to update password</span>
              <p>{{ passwordErrorMessage }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Input
              id="current-password"
              v-model="passwordForm.current_password"
              type="password"
              label="Current Password *"
              placeholder="Current password"
              required
              :error="passwordErrors.current_password?.[0]"
            />

            <Input
              id="new-password"
              v-model="passwordForm.new_password"
              type="password"
              label="New Password *"
              placeholder="Min. 8 characters"
              required
              :error="passwordErrors.new_password?.[0]"
            />

            <Input
              id="confirm-password"
              v-model="passwordForm.new_password_confirmation"
              type="password"
              label="Confirm New Password *"
              placeholder="Repeat new password"
              required
              :error="passwordErrors.new_password_confirmation?.[0]"
            />
          </div>

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-[#ebe8de]">
            <span class="text-[11px] text-neutral-500">
              Must be at least 8 characters and different from your current password.
            </span>

            <Button
              type="submit"
              variant="primary"
              size="sm"
              :is-loading="isUpdatingPassword"
              :disabled="isUpdatingPassword || !isPasswordFormFilled"
            >
              <template #left-icon>
                <KeyRound class="w-3.5 h-3.5" />
              </template>
              <span>Save New Password</span>
            </Button>
          </div>
        </form>
      </Card>

      <!-- 2. Central Account Hub Sessions & SSO Security -->
      <Card
        title="Multi-Device Sessions & Central Hub"
        subtitle="Manage cross-platform authentication, OAuth authorizations, and active login sessions."
      >
        <div class="space-y-4 text-xs text-neutral-700">
          <p class="leading-relaxed">
            SFU MSA Connect uses central single sign-on (SSO). Changing your password here immediately refreshes your session token. To review active logins across campus lab workstations and personal mobile devices, visit the central security hub:
          </p>

          <div class="p-4 rounded-xl bg-[#fffbf4] border border-[#ebe8de] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
              <span class="font-bold text-[#640c0e]">SFU MSA Central Account Hub</span>
              <p class="text-neutral-600">
                Inspect active sessions, authorized OAuth apps, and multi-factor authentication preferences.
              </p>
            </div>

            <a
              href="https://sfumsa.ca/account"
              target="_blank"
              rel="noopener noreferrer"
              class="shrink-0"
            >
              <Button variant="outline" size="sm">
                <span>Open Central Hub</span>
                <template #right-icon>
                  <ExternalLink class="w-3.5 h-3.5" />
                </template>
              </Button>
            </a>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import Button from '@/components/ui/Button.vue';
import { accountService } from '@/services/account.service';
import type { ApiError } from '@/types/api';
import {
  User,
  KeyRound,
  ExternalLink,
  CheckCircle2,
  AlertCircle,
  X,
} from 'lucide-vue-next';

const authStore = useAuthStore();

const navTabs = computed<RouteTabItem[]>(() => [
  {
    to: '/app/profile',
    label: 'Personal Details',
    icon: User,
    exact: true,
  },
  {
    to: '/app/profile/security',
    label: 'Security & Credentials',
    icon: KeyRound,
  },
]);

const isUpdatingPassword = ref<boolean>(false);
const passwordSuccessMessage = ref<string | null>(null);
const passwordErrorMessage = ref<string | null>(null);
const passwordErrors = ref<Record<string, string[]>>({});

const passwordForm = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});

const isPasswordFormFilled = computed(() => {
  return (
    passwordForm.value.current_password.length > 0 &&
    passwordForm.value.new_password.length >= 8 &&
    passwordForm.value.new_password === passwordForm.value.new_password_confirmation
  );
});

async function handleUpdatePassword() {
  if (!isPasswordFormFilled.value || isUpdatingPassword.value) return;

  isUpdatingPassword.value = true;
  passwordErrorMessage.value = null;
  passwordSuccessMessage.value = null;
  passwordErrors.value = {};

  try {
    const res = await accountService.updatePassword({
      current_password: passwordForm.value.current_password,
      new_password: passwordForm.value.new_password,
      new_password_confirmation: passwordForm.value.new_password_confirmation,
    });
    if (res.token) {
      authStore.replaceToken(res.token);
    }
    passwordSuccessMessage.value = res.message || 'Password updated successfully. Session token renewed.';
    passwordForm.value = {
      current_password: '',
      new_password: '',
      new_password_confirmation: '',
    };
  } catch (err: unknown) {
    const apiErr = err as ApiError;
    if (apiErr.errors) {
      passwordErrors.value = apiErr.errors;
    }
    passwordErrorMessage.value = apiErr.message || 'Unable to update password. Please check the requirements.';
  } finally {
    isUpdatingPassword.value = false;
  }
}
</script>
