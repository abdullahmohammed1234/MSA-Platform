<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Alert from '@/components/feedback/alert/Alert.vue';
import { Motion, Presence } from '@motionone/vue';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const manualToken = ref('');
const errorMsg = ref<string | null>(null);
const successMsg = ref<string | null>(null);
const resendSuccess = ref<string | null>(null);
const status = ref<'awaiting' | 'verifying' | 'verified' | 'expired'>('awaiting');

const verifyToken = async (tokenValue: string) => {
  status.value = 'verifying';
  errorMsg.value = null;
  successMsg.value = null;
  
  try {
    const data = await authStore.verifyEmail(tokenValue);
    status.value = 'verified';
    successMsg.value = data.message || 'Email verified successfully!';
    
    // Redirect to dashboard after 3 seconds
    setTimeout(() => {
      if (authStore.roles.includes('admin')) {
        router.push({ name: 'admin-dashboard' });
      } else {
        router.push({ name: 'academy-dashboard' });
      }
    }, 3000);
  } catch (error: any) {
    console.error('Email verification error', error);
    status.value = 'expired';
    errorMsg.value = error.response?.data?.message || 'Verification failed. The link may have expired or is invalid.';
  }
};

const handleManualVerify = () => {
  if (!manualToken.value.trim()) {
    errorMsg.value = 'Please enter a verification token.';
    return;
  }
  verifyToken(manualToken.value.trim());
};

const handleResend = async () => {
  resendSuccess.value = null;
  errorMsg.value = null;
  try {
    const data = await authStore.resendVerification();
    resendSuccess.value = data.message || 'Verification link resent to your email.';
  } catch (error: any) {
    console.error('Resend verification error', error);
    errorMsg.value = error.response?.data?.message || 'Failed to resend verification link. Please try again later.';
  }
};

const handleLogout = async () => {
  await authStore.logout();
  router.push({ name: 'login' });
};

onMounted(() => {
  const tokenFromUrl = route.query.token as string;
  if (tokenFromUrl) {
    verifyToken(tokenFromUrl);
  }
});
</script>

<template>
  <Motion
    :initial="{ opacity: 0, y: 15 }"
    :animate="{ opacity: 1, y: 0 }"
    :transition="{ duration: 0.4 }"
  >
    <div class="space-y-6 text-center">
      <div class="text-center mb-6">
        <h2 class="text-2xl font-display font-bold text-primary">Verify Your Email</h2>
        <p class="text-xs text-neutral-muted mt-1">
          A verification link was sent to <span class="font-semibold text-neutral-black">{{ authStore.user?.email }}</span>.
        </p>
      </div>

      <Presence>
        <!-- Success Alert -->
        <Motion
          v-if="successMsg"
          :initial="{ opacity: 0, height: 0, y: -10 }"
          :animate="{ opacity: 1, height: 'auto', y: 0 }"
          :exit="{ opacity: 0, height: 0, y: -10 }"
          class="overflow-hidden"
        >
          <Alert type="success" class="mb-4 text-left" title="Verified">
            {{ successMsg }}
          </Alert>
        </Motion>

        <!-- Resend Success Alert -->
        <Motion
          v-if="resendSuccess"
          :initial="{ opacity: 0, height: 0, y: -10 }"
          :animate="{ opacity: 1, height: 'auto', y: 0 }"
          :exit="{ opacity: 0, height: 0, y: -10 }"
          class="overflow-hidden"
        >
          <Alert type="success" class="mb-4 text-left" title="Email Sent">
            {{ resendSuccess }}
          </Alert>
        </Motion>

        <!-- Error Alert -->
        <Motion
          v-if="errorMsg"
          :initial="{ opacity: 0, height: 0, y: -10 }"
          :animate="{ opacity: 1, height: 'auto', y: 0 }"
          :exit="{ opacity: 0, height: 0, y: -10 }"
          class="overflow-hidden"
        >
          <Alert type="error" class="mb-4 text-left" title="Error">
            {{ errorMsg }}
          </Alert>
        </Motion>
      </Presence>

      <!-- Loading / Verifying State -->
      <div v-if="status === 'verifying'" class="py-8 flex flex-col items-center justify-center space-y-4">
        <svg class="animate-spin h-10 w-10 text-primary" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-sm text-neutral-muted font-medium">Verifying your email address, please wait...</p>
      </div>

      <!-- Verified Success State -->
      <div v-else-if="status === 'verified'" class="py-8 flex flex-col items-center justify-center space-y-3">
        <div class="h-14 w-14 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-600 border border-emerald-200">
          <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <p class="text-sm font-semibold text-neutral-black">Email Confirmed!</p>
        <p class="text-xs text-neutral-muted">Redirecting you to the platform dashboard...</p>
      </div>

      <!-- Awaiting / Expired State Form -->
      <div v-else class="space-y-6">
        <div class="bg-neutral-background/40 p-4 border border-neutral-ivory rounded-xl text-left space-y-4">
          <p class="text-xs text-neutral-muted leading-relaxed">
            Click the link in the email we sent you, or paste the verification token here manually:
          </p>
          
          <div class="flex gap-2">
            <div class="flex-1">
              <Input
                label=""
                placeholder="Paste token here"
                v-model="manualToken"
                class="!mb-0"
              />
            </div>
            <Button 
              variant="primary" 
              class="self-end h-[46px]"
              @click="handleManualVerify"
              :is-loading="authStore.isLoading"
            >
              Verify
            </Button>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 justify-center items-center">
          <Button
            variant="outline"
            class="w-full sm:w-auto"
            @click="handleResend"
            :is-loading="authStore.isLoading"
          >
            Resend Email
          </Button>
          
          <Button
            variant="ghost"
            class="w-full sm:w-auto text-neutral-muted hover:text-primary"
            @click="handleLogout"
          >
            Sign Out
          </Button>
        </div>
      </div>
    </div>
  </Motion>
</template>
