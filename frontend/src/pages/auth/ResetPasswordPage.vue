<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Alert from '@/components/feedback/alert/Alert.vue';
import { Motion, Presence } from '@motionone/vue';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const password = ref('');
const passwordConfirmation = ref('');
const errorMsg = ref<string | null>(null);
const successMsg = ref<string | null>(null);
const showPassword = ref(false);

const token = ref('');
const email = ref('');

onMounted(() => {
  token.value = (route.query.token as string) || '';
  email.value = (route.query.email as string) || '';

  if (!token.value || !email.value) {
    errorMsg.value = 'Invalid or missing password reset parameters. Please check your email link.';
  }
});

// Password Strength Evaluation
const passwordStrength = computed(() => {
  const p = password.value;
  if (!p) return { score: 0, label: 'None', color: 'bg-neutral-gray' };
  
  let score = 0;
  if (p.length >= 8) score++;
  if (/[a-z]/.test(p) && /[A-Z]/.test(p)) score++;
  if (/\d/.test(p)) score++;
  if (/[^A-Za-z0-9]/.test(p)) score++;

  const states = [
    { score: 0, label: 'Very Weak', color: 'bg-red-500' },
    { score: 1, label: 'Weak', color: 'bg-orange-500' },
    { score: 2, label: 'Medium', color: 'bg-amber-500' },
    { score: 3, label: 'Strong', color: 'bg-emerald-500' },
    { score: 4, label: 'Very Strong', color: 'bg-emerald-600' }
  ];

  return states[score];
});

const handleResetPassword = async () => {
  errorMsg.value = null;
  successMsg.value = null;

  if (!password.value || !passwordConfirmation.value) {
    errorMsg.value = 'Please fill in all fields.';
    return;
  }

  if (password.value !== passwordConfirmation.value) {
    errorMsg.value = 'Passwords do not match.';
    return;
  }

  if (!token.value || !email.value) {
    errorMsg.value = 'Reset token or email is missing. Request a new password reset.';
    return;
  }

  try {
    const data = await authStore.resetPassword({
      email: email.value,
      token: token.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value
    });

    successMsg.value = data.message || 'Password reset successful. Redirecting to login...';
    
    // Redirect after 3 seconds
    setTimeout(() => {
      router.push({ name: 'login' });
    }, 3000);
  } catch (error: any) {
    console.error('Password reset failed', error);
    if (error.response?.data?.errors?.password) {
      errorMsg.value = error.response.data.errors.password[0];
    } else {
      errorMsg.value = error.response?.data?.message || 'Failed to reset password. The link may have expired.';
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
        <h2 class="text-2xl font-display font-bold text-primary">Choose New Password</h2>
        <p class="text-xs text-neutral-muted mt-1">Please enter your new password below.</p>
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
          <Alert type="success" class="mb-4" title="Success">
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

      <form v-if="!successMsg && token && email" class="space-y-4" @submit.prevent="handleResetPassword">
        <!-- New Password -->
        <Input
          label="New Password"
          :type="showPassword ? 'text' : 'password'"
          placeholder="••••••••"
          v-model="password"
          required
        >
          <template #prefix>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </template>
          <template #suffix>
            <button 
              type="button" 
              class="focus:outline-none cursor-pointer hover:text-primary transition-colors text-neutral-muted" 
              @click="showPassword = !showPassword"
              aria-label="Toggle password visibility"
            >
              <svg v-if="showPassword" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              </svg>
              <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
          </template>
        </Input>

        <!-- Password Strength Meter -->
        <div v-if="password" class="space-y-1.5 pb-2">
          <div class="flex items-center justify-between text-[10px] font-bold text-neutral-muted uppercase tracking-wider">
            <span>Password Strength</span>
            <span :class="passwordStrength.color.replace('bg-', 'text-')">{{ passwordStrength.label }}</span>
          </div>
          <div class="h-1 w-full bg-neutral-ivory rounded-full overflow-hidden">
            <div 
              :class="['h-full transition-all duration-300 rounded-full', passwordStrength.color]"
              :style="{ width: `${(passwordStrength.score / 4) * 100}%` }"
            ></div>
          </div>
        </div>

        <!-- Confirm Password -->
        <Input
          label="Confirm Password"
          :type="showPassword ? 'text' : 'password'"
          placeholder="••••••••"
          v-model="passwordConfirmation"
          required
        >
          <template #prefix>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
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
          Reset Password
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
