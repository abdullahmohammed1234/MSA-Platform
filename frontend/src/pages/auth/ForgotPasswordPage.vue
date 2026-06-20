<script setup lang="ts">
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Alert from '@/components/feedback/alert/Alert.vue';
import { Motion, Presence } from '@motionone/vue';

const authStore = useAuthStore();
const email = ref('');
const errorMsg = ref<string | null>(null);
const successMsg = ref<string | null>(null);

const handleForgotPassword = async () => {
  errorMsg.value = null;
  successMsg.value = null;

  if (!email.value) {
    errorMsg.value = 'Please enter your email address.';
    return;
  }

  try {
    const data = await authStore.forgotPassword(email.value);
    successMsg.value = data.message || 'Check your email for reset instructions.';
    email.value = '';
  } catch (error: any) {
    console.error('Forgot password error', error);
    if (error.response?.data?.errors?.email) {
      errorMsg.value = error.response.data.errors.email[0];
    } else {
      errorMsg.value = error.response?.data?.message || 'Failed to request password reset. Please try again.';
    }
  }
};
</script>

<template>
  <Motion
    :initial="{ opacity: 0, y: 15 }"
    :animate="{ opacity: 1, y: 0 }"
    :transition="{ duration: 0.4 }"
  >
    <div class="space-y-6">
      <div class="text-left mb-6">
        <h2 class="text-2xl font-display font-bold text-primary">Reset Password</h2>
        <p class="text-xs text-neutral-muted mt-1">Enter your email and we'll send you instructions to reset your password.</p>
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
          <Alert type="success" class="mb-4" title="Email Sent">
            {{ successMsg }}
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
          <Alert type="error" class="mb-4" title="Error">
            {{ errorMsg }}
          </Alert>
        </Motion>
      </Presence>

      <form v-if="!successMsg" class="space-y-4" @submit.prevent="handleForgotPassword">
        <!-- Email Address -->
        <Input
          label="Email Address"
          type="email"
          placeholder="student@sfu.ca"
          v-model="email"
          required
        >
          <template #prefix>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
            </svg>
          </template>
        </Input>

        <!-- Submit -->
        <Button
          type="submit"
          variant="primary"
          is-full-width
          is-shiny
          :is-loading="authStore.isLoading"
        >
          Send Reset Link
        </Button>
      </form>

      <div class="text-xs text-center text-neutral-muted mt-6">
        <router-link to="/login" class="text-secondary hover:underline font-semibold hover:text-primary transition-colors inline-flex items-center gap-1">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back to Login
        </router-link>
      </div>
    </div>
  </Motion>
</template>
