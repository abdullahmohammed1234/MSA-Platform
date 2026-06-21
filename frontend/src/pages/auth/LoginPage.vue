<script setup lang="ts">
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Alert from '@/components/feedback/alert/Alert.vue';
import { Motion, Presence } from '@motionone/vue';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const email = ref('');
const password = ref('');
const rememberMe = ref(false);
const showPassword = ref(false);
const formError = ref<string | null>(null);

const handleLogin = async () => {
  formError.value = null;
  
  if (!email.value || !password.value) {
    formError.value = 'Please fill in all fields.';
    return;
  }

  try {
    await authStore.login({
      email: email.value,
      password: password.value,
      remember: rememberMe.value
    });

    // Handle Redirect after login
    if (authStore.needsEmailVerification) {
      router.push({ name: 'verify-email' });
      return;
    }

    const redirectPath = route.query.redirect as string;
    if (redirectPath) {
      router.push(redirectPath);
    } else if (authStore.roles.includes('admin') || authStore.roles.includes('super-admin')) {
      router.push({ name: 'admin-dashboard' });
    } else {
      router.push({ path: '/' });
    }
  } catch (error: any) {
    console.error('Login error details', error);
    if (error.response?.data?.errors?.email) {
      formError.value = error.response.data.errors.email[0];
    } else if (error.code === 'ECONNABORTED' || !error.response) {
      formError.value = error.message || 'Unable to reach the server. Please try again later.';
    } else {
      formError.value = error.response?.data?.message || 'Invalid credentials. Please try again.';
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
        <h2 class="text-2xl font-display font-bold text-primary">Assalamu Alaikum</h2>
        <p class="text-xs text-neutral-muted mt-1">Welcome back. Enter your credentials to access your account.</p>
      </div>

      <!-- General Form Error Alert -->
      <Presence>
        <Motion
          v-if="formError"
          :initial="{ opacity: 0, height: 0, y: -10 }"
          :animate="{ opacity: 1, height: 'auto', y: 0 }"
          :exit="{ opacity: 0, height: 0, y: -10 }"
          class="overflow-hidden"
        >
          <Alert type="error" class="mb-4" title="Login Failed">
            {{ formError }}
          </Alert>
        </Motion>
      </Presence>

      <form class="space-y-4" @submit.prevent="handleLogin">
        <!-- Email Input -->
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

        <!-- Password Input -->
        <Input
          label="Password"
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

        <!-- Remember Me and Forgot Password -->
        <div class="flex items-center justify-between pb-2">
          <Checkbox
            label="Remember Me"
            v-model="rememberMe"
          />

          <router-link
            to="/forgot-password"
            class="text-xs text-secondary hover:text-primary font-semibold hover:underline"
          >
            Forgot Password?
          </router-link>
        </div>

        <!-- Submit Button -->
        <Button
          type="submit"
          variant="primary"
          is-full-width
          is-shiny
          :is-loading="authStore.isLoading"
        >
          Sign In
        </Button>

        <p class="text-xs text-center text-neutral-muted mt-6">
          Don't have an account? 
          <router-link to="/register" class="text-secondary hover:underline font-semibold hover:text-primary transition-colors">Create Account</router-link>
        </p>
      </form>
    </div>
  </Motion>
</template>
