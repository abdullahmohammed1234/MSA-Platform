<script setup lang="ts">
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import {
  FolderKanban,
  Lock,
  Mail,
  AlertCircle,
  Loader2,
  ExternalLink,
} from 'lucide-vue-next';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const email = ref('');
const password = ref('');
const errorMessage = ref<string | null>(authStore.ssoError || authStore.forbiddenReason);
const isSubmitting = ref(false);
const portalUrl = import.meta.env.VITE_PORTAL_URL || 'https://sfumsa.ca';

const handleLogin = async () => {
  if (!email.value.trim() || !password.value.trim() || isSubmitting.value) return;

  isSubmitting.value = true;
  errorMessage.value = null;

  try {
    const success = await authStore.login(email.value.trim(), password.value);
    if (success) {
      const redirect = (route.query.redirect as string) || '/';
      router.push(redirect);
    } else if (authStore.isForbidden) {
      errorMessage.value = authStore.forbiddenReason || 'Access Denied: You must be a formally appointed SFU MSA team member to access this system.';
    }
  } catch (err: any) {
    if (err.response?.status === 403) {
      errorMessage.value = err.response?.data?.message || 'Access Denied: You do not have permission to access the Project Management System.';
    } else if (err.response?.status === 401 || err.response?.status === 422) {
      errorMessage.value = 'Invalid email or password. Please verify your credentials.';
    } else {
      errorMessage.value = err.response?.data?.message || 'Authentication failed. Please try again.';
    }
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="min-h-screen flex items-center justify-center p-4 bg-[#fbfbf9]">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-elevated border border-[#ebe8de] p-8 space-y-6 animate-in fade-in zoom-in-95 duration-200">
      <!-- Brand & Header -->
      <div class="text-center space-y-2">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#640c0e] to-[#b02e32] text-white flex items-center justify-center mx-auto shadow-brand">
          <FolderKanban class="w-6 h-6" />
        </div>
        <h1 class="text-xl font-extrabold text-[#640c0e] font-display">
          SFU MSA Project Management
        </h1>
        <p class="text-xs text-[#6b7280]">
          Sign in with your appointed MSA team member account.
        </p>
      </div>

      <!-- Error Alert -->
      <div
        v-if="errorMessage"
        class="p-4 rounded-xl bg-[#ea2128]/10 border border-[#ea2128]/20 text-[#ea2128] text-xs flex items-start gap-2.5"
      >
        <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" />
        <div class="space-y-1">
          <p class="font-bold">Access Restricted</p>
          <p class="leading-relaxed">{{ errorMessage }}</p>
        </div>
      </div>

      <!-- Sign In Form -->
      <form @submit.prevent="handleLogin" class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Email Address</label>
          <div class="relative">
            <Mail class="w-4 h-4 text-[#6b7280] absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              v-model="email"
              type="email"
              required
              autocomplete="email"
              placeholder="your.name@sfumsa.ca"
              class="w-full pl-9 pr-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Password</label>
          <div class="relative">
            <Lock class="w-4 h-4 text-[#6b7280] absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              v-model="password"
              type="password"
              required
              autocomplete="current-password"
              placeholder="••••••••"
              class="w-full pl-9 pr-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
            />
          </div>
        </div>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="w-full flex items-center justify-center gap-2 py-3 rounded-xl font-bold text-xs bg-gradient-to-r from-[#640c0e] to-[#b02e32] text-white shadow-xs hover:shadow-brand hover:opacity-95 disabled:opacity-50 transition-all cursor-pointer"
        >
          <Loader2 v-if="isSubmitting" class="w-4 h-4 animate-spin" />
          <span>Sign In to Projects</span>
        </button>
      </form>

      <!-- Explanatory note -->
      <div class="pt-4 border-t border-[#ebe8de] text-center space-y-2">
        <p class="text-[11px] text-[#6b7280]">
          PMS is reserved for SFU MSA Executive, Department Leads, Coordinators, and team members.
        </p>
        <a
          :href="`${portalUrl}/login`"
          target="_blank"
          class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#640c0e] hover:underline"
        >
          <span>Main Portal Login</span>
          <ExternalLink class="w-3 h-3" />
        </a>
      </div>
    </div>
  </div>
</template>
