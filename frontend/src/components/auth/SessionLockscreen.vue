<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Lock } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { authService } from '@/services/auth/auth.service';

const authStore = useAuthStore();

const isLocked = ref(false);
const password = ref('');
const error = ref('');
const verifying = ref(false);

const IDLE_MS = 20 * 60 * 1000;
let idleTimer: ReturnType<typeof setTimeout> | null = null;

const resetIdleTimer = () => {
  if (idleTimer) clearTimeout(idleTimer);
  idleTimer = setTimeout(() => {
    if (authStore.isAuthenticated) {
      isLocked.value = true;
    }
  }, IDLE_MS);
};

const activityEvents = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];

onMounted(() => {
  activityEvents.forEach((event) => window.addEventListener(event, resetIdleTimer, { passive: true }));
  resetIdleTimer();
});

onBeforeUnmount(() => {
  activityEvents.forEach((event) => window.removeEventListener(event, resetIdleTimer));
  if (idleTimer) clearTimeout(idleTimer);
});

const unlock = async () => {
  if (!authStore.user?.email) return;

  verifying.value = true;
  error.value = '';

  try {
    await authService.login({
      email: authStore.user.email,
      password: password.value,
    });
    isLocked.value = false;
    password.value = '';
    resetIdleTimer();
  } catch {
    error.value = 'Incorrect password. Please try again.';
  } finally {
    verifying.value = false;
  }
};
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isLocked && authStore.user"
      class="fixed inset-0 z-[130] flex items-center justify-center bg-primary/95 backdrop-blur-md px-6"
    >
      <div class="w-full max-w-md rounded-3xl border border-accent-gold/20 bg-white p-8 shadow-premium-lg">
        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-white">
          <Lock class="h-6 w-6" />
        </div>

        <h2 class="text-center text-2xl font-display font-bold text-primary">Session Locked</h2>
        <p class="mt-2 text-center text-sm text-neutral-muted">
          Re-enter your password to continue in Dawah Academy.
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="unlock">
          <input
            v-model="password"
            type="password"
            placeholder="Password"
            class="input-base"
            autocomplete="current-password"
          />
          <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
          <button
            type="submit"
            class="w-full rounded-2xl bg-primary py-3 text-sm font-bold text-white disabled:opacity-60"
            :disabled="verifying || !password"
          >
            {{ verifying ? 'Verifying...' : 'Unlock Session' }}
          </button>
        </form>
      </div>
    </div>
  </Teleport>
</template>
