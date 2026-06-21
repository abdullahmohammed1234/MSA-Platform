<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import Alert from '@/components/feedback/alert/Alert.vue';
import { Motion } from '@motionone/vue';

const router = useRouter();
const authStore = useAuthStore();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const termsAccepted = ref(false);
const selectedRole = ref<'member' | 'volunteer'>('member');

const formError = ref<string | null>(null);
const validationErrors = ref<Record<string, string[]>>({});
const showPassword = ref(false);

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

const handleRegister = async () => {
  formError.value = null;
  validationErrors.value = {};

  if (!name.value || !email.value || !password.value || !passwordConfirmation.value) {
    formError.value = 'Please fill in all fields.';
    return;
  }

  if (password.value !== passwordConfirmation.value) {
    formError.value = 'Passwords do not match.';
    return;
  }

  if (!termsAccepted.value) {
    formError.value = 'You must accept the terms and conditions.';
    return;
  }

  try {
    await authStore.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
      terms: termsAccepted.value,
      role: selectedRole.value,
    });

    if (authStore.needsEmailVerification) {
      router.push({ name: 'verify-email' });
      return;
    }

    router.push({ path: '/' });
  } catch (error: any) {
    console.error('Registration failed', error);
    if (error.response?.data?.errors) {
      validationErrors.value = error.response.data.errors;
      formError.value = 'Validation failed. Please check the errors below.';
    } else {
      formError.value = error.response?.data?.message || 'Registration failed. Please try again.';
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
        <h2 class="text-2xl font-display font-bold text-primary">Join the MSA</h2>
        <p class="text-xs text-neutral-muted mt-1">SFU students can join as a member or volunteer using their @sfu.ca email.</p>
      </div>

      <!-- General Errors -->
      <Presence>
        <Motion
          v-if="formError"
          :initial="{ opacity: 0, height: 0, y: -10 }"
          :animate="{ opacity: 1, height: 'auto', y: 0 }"
          :exit="{ opacity: 0, height: 0, y: -10 }"
          class="overflow-hidden"
        >
          <Alert type="error" class="mb-4" title="Registration Error">
            {{ formError }}
          </Alert>
        </Motion>
      </Presence>

      <form class="space-y-4" @submit.prevent="handleRegister">
        <!-- Full Name -->
        <Input
          label="Full Name"
          type="text"
          placeholder="Abdullah bin Muhammad"
          v-model="name"
          :error="validationErrors.name?.[0]"
          required
        >
          <template #prefix>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </template>
        </Input>

        <!-- Register As -->
        <div class="space-y-2">
          <label class="text-xs font-bold text-neutral-muted uppercase tracking-wider block">Register As</label>
          <div class="flex flex-col sm:flex-row gap-4 bg-neutral-ivory/30 border border-neutral-gray/10 rounded-2xl p-4">
            <button
              type="button"
              class="flex-1 text-left rounded-xl border p-4 transition-colors"
              :class="selectedRole === 'member'
                ? 'border-primary bg-primary/5'
                : 'border-transparent hover:border-neutral-gray/20'"
              @click="selectedRole = 'member'"
            >
              <span class="text-xs font-semibold text-neutral-black block">Member</span>
              <span class="text-[10px] text-neutral-muted block font-normal leading-normal mt-1">
                Access events, RSVP, and community features (@sfu.ca required).
              </span>
            </button>
            <button
              type="button"
              class="flex-1 text-left rounded-xl border p-4 transition-colors"
              :class="selectedRole === 'volunteer'
                ? 'border-primary bg-primary/5'
                : 'border-transparent hover:border-neutral-gray/20'"
              @click="selectedRole = 'volunteer'"
            >
              <span class="text-xs font-semibold text-neutral-black block">Volunteer</span>
              <span class="text-[10px] text-neutral-muted block font-normal leading-normal mt-1">
                Everything members get, plus access to the Dawah Academy (@sfu.ca required).
              </span>
            </button>
          </div>
          <p v-if="validationErrors.role?.[0]" class="text-xs text-red-500">{{ validationErrors.role[0] }}</p>
        </div>

        <!-- Email Address -->
        <Input
          label="SFU Email Address"
          type="email"
          placeholder="student@sfu.ca"
          v-model="email"
          :error="validationErrors.email?.[0]"
          required
        >
          <template #prefix>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
            </svg>
          </template>
        </Input>

        <!-- Password -->
        <Input
          label="Password"
          :type="showPassword ? 'text' : 'password'"
          placeholder="••••••••"
          v-model="password"
          :error="validationErrors.password?.[0]"
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

        <!-- Terms Acceptance -->
        <div class="pb-2">
          <Checkbox
            v-model="termsAccepted"
            :error="validationErrors.terms?.[0]"
            required
          >
            <span class="text-xs text-neutral-muted">
              I accept the 
              <a href="#" class="text-secondary hover:text-primary hover:underline font-semibold">Terms of Service</a> 
              and 
              <a href="#" class="text-secondary hover:text-primary hover:underline font-semibold">Privacy Policy</a>
            </span>
          </Checkbox>
        </div>

        <!-- Submit -->
        <Button
          type="submit"
          variant="primary"
          is-full-width
          is-shiny
          :is-loading="authStore.isLoading"
        >
          Create Account
        </Button>

        <p class="text-xs text-center text-neutral-muted mt-6">
          Already have an account? 
          <router-link to="/login" class="text-secondary hover:underline font-semibold hover:text-primary transition-colors">Sign In</router-link>
        </p>
      </form>
    </div>
  </Motion>
</template>
