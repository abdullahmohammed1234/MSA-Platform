<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Account Settings"
      title="Personal Details & Identity"
      description="Manage your university contact information, verified student membership, and application installation."
    >
      <template #badge>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-[10.5px] font-semibold">
          <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
          <span>{{ authStore.isVerified ? 'Verified Member' : 'Pending Verification' }}</span>
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

    <!-- TAB CONTENT: Modern 2-Column Responsive Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      <!-- Left Column: Student Membership & Identity Card -->
      <div class="space-y-4 lg:col-span-1">
        <!-- Membership Card -->
        <div class="p-6 rounded-2xl bg-gradient-to-br from-white via-white to-[#fffbf4] border border-[#ebe8de] shadow-2xs space-y-4">
          <div class="flex items-center gap-3.5">
            <div class="w-13 h-13 rounded-2xl bg-[#640c0e] text-[#ffdc83] font-bold text-lg flex items-center justify-center shrink-0 shadow-sm ring-2 ring-[#ffdc83]/60">
              {{ initials }}
            </div>
            <div class="min-w-0">
              <h2 class="text-base font-bold text-[#640c0e] truncate font-display">
                {{ authStore.user?.name || 'Member' }}
              </h2>
              <p class="text-xs text-neutral-500 truncate">
                {{ authStore.user?.email || 'student@sfu.ca' }}
              </p>
            </div>
          </div>

          <div class="pt-3 border-t border-[#ebe8de]/80 space-y-2.5 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-neutral-500 font-medium">Status</span>
              <span class="px-2 py-0.5 rounded-full text-[10.5px] font-bold" :class="authStore.isVerified ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                {{ authStore.isVerified ? 'Verified Student' : 'Pending' }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-neutral-500 font-medium">Roles</span>
              <span class="font-semibold text-neutral-800 capitalize">
                {{ authStore.roles.join(', ') || 'Community Member' }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-neutral-500 font-medium">Member Since</span>
              <span class="font-medium text-neutral-700">
                {{ memberSinceFormatted || 'Active Term' }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-neutral-500 font-medium">Account ID</span>
              <span class="font-mono text-[10.5px] text-neutral-500 truncate max-w-[140px]">
                {{ authStore.user?.uuid || '—' }}
              </span>
            </div>
          </div>
        </div>

        <!-- PWA Application Status Box -->
        <div class="p-5 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-[#640c0e]">App Install Status</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="pwa.isInstalled.value ? 'bg-emerald-100 text-emerald-800' : 'bg-[#fffbf4] border border-[#ebe8de] text-neutral-600'">
              {{ pwa.isInstalled.value ? 'Installed (PWA)' : 'Web Browser' }}
            </span>
          </div>
          <p class="text-xs text-neutral-500 leading-relaxed">
            {{ pwa.isInstalled.value
              ? 'Connect is operating as an installed Progressive Web App on this device.'
              : 'Add Connect to your home screen or dock for instant launch and offline access.'
            }}
          </p>
          <Button
            v-if="!pwa.isInstalled.value"
            type="button"
            variant="outline"
            size="sm"
            block
            @click="handleProfileInstall"
            class="text-xs font-semibold bg-[#fffbf4]"
          >
            <template #left-icon>
              <Smartphone class="w-3.5 h-3.5 text-[#b02e32]" />
            </template>
            <span>Install App on Device</span>
          </Button>
        </div>
      </div>

      <!-- Right Column: Editable Profile Form -->
      <div class="lg:col-span-2 space-y-4">
        <div class="p-6 sm:p-7 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs space-y-5">
          <div class="space-y-1 border-b border-[#ebe8de]/70 pb-4">
            <h2 class="text-base font-bold text-[#640c0e]">Edit Contact Information</h2>
            <p class="text-xs text-neutral-500">
              Update your preferred display name and primary student notification email.
            </p>
          </div>

          <form @submit.prevent="handleSaveProfile" class="space-y-4">
            <!-- Success Banner -->
            <div
              v-if="profileSuccessMessage"
              class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center justify-between gap-2"
            >
              <div class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span class="font-medium">{{ profileSuccessMessage }}</span>
              </div>
              <button
                type="button"
                @click="profileSuccessMessage = null"
                class="text-emerald-700 hover:text-emerald-900 cursor-pointer"
                aria-label="Dismiss message"
              >
                <X class="w-4 h-4" />
              </button>
            </div>

            <!-- Error Banner -->
            <div
              v-if="profileErrorMessage"
              class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-xs text-red-800 flex items-start gap-2"
            >
              <AlertCircle class="w-4 h-4 text-red-600 shrink-0 mt-0.5" />
              <div class="space-y-0.5">
                <span class="font-bold">Unable to save profile</span>
                <p>{{ profileErrorMessage }}</p>
              </div>
            </div>

            <!-- Editable Inputs Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                id="member-name"
                v-model="profileForm.name"
                label="Full Name *"
                placeholder="Your Full Name"
                required
                :error="profileErrors.name?.[0]"
              />

              <Input
                id="member-email"
                v-model="profileForm.email"
                type="email"
                label="Email Address *"
                placeholder="student@sfu.ca"
                required
                :error="profileErrors.email?.[0]"
              />
            </div>

            <!-- Form Actions -->
            <div class="pt-4 border-t border-[#ebe8de]/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <span class="text-[11px] text-neutral-500">
                Updating your email address changes your login username.
              </span>

              <div class="flex items-center gap-2 shrink-0">
                <Button
                  v-if="hasProfileChanges"
                  type="button"
                  variant="outline"
                  size="sm"
                  :disabled="isSavingProfile"
                  @click="discardProfileChanges"
                  class="bg-white border-[#ebe8de] text-xs font-semibold"
                >
                  Discard Changes
                </Button>

                <Button
                  type="submit"
                  variant="primary"
                  size="sm"
                  :is-loading="isSavingProfile"
                  :disabled="!hasProfileChanges || isSavingProfile"
                  class="shadow-2xs text-xs font-semibold"
                >
                  <span>Save Changes</span>
                </Button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import Input from '@/components/ui/Input.vue';
import Button from '@/components/ui/Button.vue';
import type { ApiError } from '@/types/api';
import {
  User,
  KeyRound,
  ExternalLink,
  CheckCircle2,
  AlertCircle,
  X,
  Smartphone,
} from 'lucide-vue-next';
import { usePwaInstall } from '@/composables/usePwaInstall';

const authStore = useAuthStore();
const pwa = usePwaInstall();

const initials = computed(() => {
  const name = authStore.user?.name || '';
  if (!name) return 'M';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }
  return name.slice(0, 2).toUpperCase();
});

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

const memberSinceFormatted = computed(() => {
  if (!authStore.user?.created_at) return null;
  return new Date(authStore.user.created_at).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
});

// Profile Form State
const isSavingProfile = ref<boolean>(false);
const profileSuccessMessage = ref<string | null>(null);
const profileErrorMessage = ref<string | null>(null);
const profileErrors = ref<Record<string, string[]>>({});

const profileForm = ref<{
  name: string;
  email: string;
}>({
  name: authStore.user?.name || '',
  email: authStore.user?.email || '',
});

// Watch auth user to sync form when loaded/updated
watch(
  () => authStore.user,
  (newUser) => {
    if (newUser) {
      profileForm.value.name = newUser.name || '';
      profileForm.value.email = newUser.email || '';
    }
  },
  { immediate: true }
);

const hasProfileChanges = computed(() => {
  const currentName = authStore.user?.name || '';
  const currentEmail = authStore.user?.email || '';
  return profileForm.value.name.trim() !== currentName || profileForm.value.email.trim() !== currentEmail;
});

function discardProfileChanges() {
  profileForm.value.name = authStore.user?.name || '';
  profileForm.value.email = authStore.user?.email || '';
  profileErrorMessage.value = null;
  profileErrors.value = {};
}

async function handleSaveProfile() {
  if (!hasProfileChanges.value || isSavingProfile.value) return;

  isSavingProfile.value = true;
  profileErrorMessage.value = null;
  profileSuccessMessage.value = null;
  profileErrors.value = {};

  try {
    await authStore.updateProfile({
      name: profileForm.value.name.trim(),
      email: profileForm.value.email.trim(),
    });
    profileSuccessMessage.value = 'Profile updated successfully!';
  } catch (err: unknown) {
    const apiErr = err as ApiError;
    if (apiErr.errors) {
      profileErrors.value = apiErr.errors;
    }
    profileErrorMessage.value = apiErr.message || 'Unable to update profile.';
  } finally {
    isSavingProfile.value = false;
  }
}

async function handleProfileInstall() {
  if (pwa.isInstallable.value) {
    await pwa.promptInstall();
  } else if (pwa.platform.value === 'ios') {
    // Navigate or link to help
  }
}
</script>
