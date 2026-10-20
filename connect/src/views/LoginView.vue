<template>
  <div class="min-h-screen flex flex-col bg-[#fffbf4] pattern-islamic-lattice">
    <!-- Minimal Header -->
    <header class="py-5 px-4 sm:px-8 flex items-center justify-between border-b border-[#ebe8de]/70 bg-white/80 backdrop-blur-md sticky top-0 z-20">
      <router-link :to="logoDestination" :aria-label="logoAriaLabel" class="flex items-center gap-3 group focus:outline-none focus-visible:ring-2 focus-visible:ring-[#640c0e] rounded-xl p-1">
        <div class="w-8 h-8 rounded-xl bg-white border border-[#ebe8de] shadow-xs flex items-center justify-center overflow-hidden shrink-0 group-hover:border-[#640c0e]/40 transition-colors">
          <img
            src="/logo.webp"
            alt="SFU MSA logo"
            class="w-6 h-6 object-contain"
            onerror="this.style.display='none'"
          />
        </div>
        <div class="flex flex-col">
          <span class="font-extrabold tracking-tight text-base sm:text-lg text-[#640c0e] leading-tight font-display">
            SFU MSA <span class="font-normal text-[#b02e32]">Connect</span>
          </span>
          <span class="text-[9px] uppercase tracking-widest text-neutral-500 font-semibold leading-none">
            Member Portal
          </span>
        </div>
      </router-link>

      <router-link
        :to="logoDestination"
        class="inline-flex items-center gap-1.5 text-xs text-neutral-600 hover:text-[#640c0e] font-semibold px-3 py-1.5 rounded-xl border border-[#ebe8de] bg-white hover:bg-[#fffbf4] transition-all shadow-2xs"
      >
        <ArrowLeft class="w-3.5 h-3.5" />
        <span>{{ authStore.isAuthenticated ? 'Back to Dashboard' : 'Back to Home' }}</span>
      </router-link>
    </header>

    <!-- Main Container -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8 relative">
      <!-- Ambient Glow -->
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[350px] bg-gradient-to-b from-[#ffdc83]/20 via-[#640c0e]/5 to-transparent rounded-full blur-3xl pointer-events-none"></div>

      <div class="w-full max-w-md space-y-4 relative z-10">
        <!-- Auth Card -->
        <div class="community-card p-6 sm:p-8 bg-white border border-[#ebe8de] border-t-4 border-t-[#d4af37] shadow-lg relative overflow-hidden">
          <!-- Card Header Motif -->
          <div class="text-center mb-6 relative z-10">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-white border border-[#ebe8de] shadow-2xs flex items-center justify-center mb-3.5 ring-2 ring-[#ffdc83]/60">
              <img
                src="/logo.webp"
                alt="SFU MSA logo"
                class="w-10 h-10 object-contain"
                onerror="this.style.display='none'"
              />
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#640c0e] tracking-tight font-display">
              Welcome to MSA Connect
            </h1>
            <p class="text-xs text-neutral-600 mt-1.5 max-w-xs mx-auto leading-relaxed">
              Sign in with your SFU MSA platform account to access your event wallet, announcements, and member support.
            </p>
          </div>

          <!-- Session Expired Reauthentication Banner -->
          <div
            v-if="isSessionExpired"
            class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-3 text-left"
            role="status"
          >
            <Clock class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
            <div class="flex-1">
              <p class="text-xs font-semibold text-amber-900">Session Re-authentication Required</p>
              <p class="text-xs text-amber-800 mt-0.5 leading-relaxed">
                Your session has ended or re-authentication is required to access that page. Please sign in to continue.
              </p>
            </div>
          </div>

          <!-- General Error Banner -->
          <div
            v-if="generalError"
            class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-start gap-3 text-left"
            role="alert"
          >
            <AlertCircle class="w-4 h-4 text-red-600 shrink-0 mt-0.5" />
            <div class="flex-1">
              <p class="text-xs font-semibold text-red-800">Authentication Failed</p>
              <p class="text-xs text-red-700 mt-0.5 leading-relaxed">{{ generalError }}</p>
              <div class="mt-2 pt-2 border-t border-red-200/60">
                <button
                  type="button"
                  @click="showForgotModal = true"
                  class="text-[11px] font-semibold text-[#b02e32] hover:underline inline-flex items-center gap-1 cursor-pointer"
                >
                  <span>Forgot or need to reset your password?</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Login Form -->
          <form @submit.prevent="handleLoginSubmit" class="space-y-4" novalidate>
            <!-- Email Field -->
            <Input
              id="login-email"
              v-model="email"
              type="email"
              label="Email Address"
              placeholder="e.g. student@sfu.ca"
              :error="emailError"
              :disabled="isLoading"
              required
              autocomplete="email"
              @blur="validateEmailField"
            >
              <template #left-icon>
                <Mail class="w-4 h-4 text-neutral-400" />
              </template>
            </Input>

            <!-- Password Field -->
            <Input
              id="login-password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              label="Password"
              placeholder="Enter your account password"
              :error="passwordError"
              :disabled="isLoading"
              required
              autocomplete="current-password"
              @blur="validatePasswordField"
            >
              <template #left-icon>
                <Lock class="w-4 h-4 text-neutral-400" />
              </template>
              <template #right-icon>
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="text-neutral-400 hover:text-neutral-600 focus:outline-none transition-colors"
                  :aria-label="showPassword ? 'Hide password' : 'Show password'"
                >
                  <EyeOff v-if="showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </template>
            </Input>

            <!-- Help / Forgot Row -->
            <div class="flex items-center justify-between text-xs pt-1">
              <button
                type="button"
                @click="showForgotModal = true"
                class="font-medium text-[#b02e32] hover:text-[#640c0e] hover:underline transition-colors cursor-pointer"
              >
                Forgot Password?
              </button>

              <router-link
                to="/help"
                class="font-medium text-neutral-500 hover:text-[#640c0e] transition-colors"
              >
                Help & Troubleshooting
              </router-link>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
              <Button
                type="submit"
                variant="primary"
                size="lg"
                block
                :is-loading="isLoading"
                :disabled="isLoading"
              >
                <span>Sign In to Connect</span>
                <template #right-icon>
                  <ArrowRight class="w-4 h-4" />
                </template>
              </Button>
            </div>
          </form>

          <!-- Divider -->
          <div class="mt-6 pt-5 border-t border-[#ebe8de] text-center space-y-2">
            <p class="text-xs text-neutral-500">
              New to SFU or haven't registered on the platform yet?
            </p>
            <a
              href="https://sfumsa.ca"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1 text-xs font-semibold text-[#640c0e] hover:text-[#b02e32] transition-colors"
            >
              <span>Visit SFU MSA Central Platform</span>
              <ExternalLink class="w-3 h-3" />
            </a>
          </div>
        </div>

        <!-- Exploring / Guest note -->
        <div class="p-3.5 rounded-xl bg-white/70 border border-[#ebe8de] text-center text-xs text-neutral-600 flex items-center justify-center gap-1.5">
          <span>Exploring faith or visiting campus?</span>
          <router-link to="/" class="font-bold text-[#640c0e] hover:underline">
            Browse public information
          </router-link>
        </div>
      </div>
    </main>

    <!-- Modal: Forgot Password / Account Recovery -->
    <Modal
      v-model="showForgotModal"
      title="Reset Account Password"
      subtitle="Enter your email address. If an account matches, reset instructions will be sent."
    >
      <form @submit.prevent="handleForgotSubmit" class="space-y-4 text-xs">
        <p class="text-neutral-600 leading-relaxed">
          If an account matches that email address, password reset instructions will be sent.
        </p>

        <!-- Success Feedback -->
        <div
          v-if="forgotSuccessMessage"
          class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-start gap-2.5"
        >
          <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
          <div class="space-y-0.5">
            <span class="font-bold">Request received</span>
            <p>{{ forgotSuccessMessage }}</p>
          </div>
        </div>

        <!-- Error Feedback -->
        <div
          v-if="forgotErrorMessage"
          class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-800 flex items-start gap-2"
        >
          <AlertCircle class="w-4 h-4 text-red-600 shrink-0 mt-0.5" />
          <p>{{ forgotErrorMessage }}</p>
        </div>

        <Input
          id="forgot-email"
          v-model="forgotEmail"
          type="email"
          label="Student Email Address *"
          placeholder="student@sfu.ca"
          required
          :disabled="isSendingForgot || Boolean(forgotSuccessMessage)"
          autocomplete="email"
        >
          <template #left-icon>
            <Mail class="w-4 h-4 text-neutral-400" />
          </template>
        </Input>

        <div class="pt-2 flex items-center justify-end gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            @click="showForgotModal = false"
          >
            {{ forgotSuccessMessage ? 'Close' : 'Cancel' }}
          </Button>

          <Button
            v-if="!forgotSuccessMessage"
            type="submit"
            variant="primary"
            size="sm"
            :is-loading="isSendingForgot"
            :disabled="isSendingForgot || !forgotEmail"
          >
            Send Reset Instructions
          </Button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { authService } from '@/services/auth.service';
import { sanitizeRedirectPath } from '@/utils/security';
import { interpretPasswordResetRequest } from '@/utils/passwordResetNotice';
import type { ApiError } from '@/types/api';
import Input from '@/components/ui/Input.vue';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import {
  Mail,
  Lock,
  Eye,
  EyeOff,
  ArrowRight,
  ArrowLeft,
  ExternalLink,
  AlertCircle,
  Clock,
  CheckCircle2,
} from 'lucide-vue-next';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const logoDestination = computed(() => {
  return '/';
});
const logoAriaLabel = computed(() => {
  return 'SFU MSA Connect Home';
});

const email = ref('');
const password = ref('');
const showPassword = ref(false);

const emailError = ref('');
const passwordError = ref('');
const generalError = ref('');
const isLoading = ref(false);

function validateEmailField(): boolean {
  emailError.value = '';
  if (!email.value) {
    emailError.value = 'Email address is required.';
    return false;
  }
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(email.value)) {
    emailError.value = 'Please enter a valid email address.';
    return false;
  }
  return true;
}

function validatePasswordField(): boolean {
  passwordError.value = '';
  if (!password.value) {
    passwordError.value = 'Password is required.';
    return false;
  }
  return true;
}

async function handleLoginSubmit() {
  generalError.value = '';
  const isEmailValid = validateEmailField();
  const isPasswordValid = validatePasswordField();

  if (!isEmailValid || !isPasswordValid) {
    return;
  }

  isLoading.value = true;
  try {
    await authStore.login({
      email: email.value.trim(),
      password: password.value,
    });

    const redirectQuery = route.query.redirect as string | undefined;
    const safePath = sanitizeRedirectPath(redirectQuery, '/app/dashboard');
    await router.push(safePath);
  } catch (err: unknown) {
    const apiErr = err as ApiError;
    if (apiErr.errors?.email && apiErr.errors.email.length > 0) {
      emailError.value = apiErr.errors.email[0];
    } else if (apiErr.errors?.password && apiErr.errors.password.length > 0) {
      passwordError.value = apiErr.errors.password[0];
    } else {
      generalError.value = apiErr.message || 'Invalid credentials. Please verify your email and password.';
    }
  } finally {
    isLoading.value = false;
  }
}

// Session Expiry Detection
const isSessionExpired = computed(() => Boolean(route.query.redirect));

// Account Recovery / Forgot Password State
const showForgotModal = ref<boolean>(false);
const forgotEmail = ref<string>('');
const isSendingForgot = ref<boolean>(false);
const forgotSuccessMessage = ref<string>('');
const forgotErrorMessage = ref<string>('');

async function handleForgotSubmit() {
  if (!forgotEmail.value || isSendingForgot.value) return;

  isSendingForgot.value = true;
  forgotErrorMessage.value = '';
  forgotSuccessMessage.value = '';

  try {
    await authService.requestPasswordReset(forgotEmail.value.trim());
    forgotSuccessMessage.value = interpretPasswordResetRequest({ ok: true, status: 200 }).text;
  } catch (err: unknown) {
    const apiErr = err as ApiError;
    const notice = interpretPasswordResetRequest({
      ok: false,
      status: apiErr.statusCode,
      message: apiErr.message,
      emailErrors: apiErr.errors?.email,
    });
    if (notice.kind === 'success') {
      forgotSuccessMessage.value = notice.text;
    } else {
      forgotErrorMessage.value = notice.text;
    }
  } finally {
    isSendingForgot.value = false;
  }
}
</script>
