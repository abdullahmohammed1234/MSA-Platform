<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Button from '@/components/ui/button/Button.vue';
import Alert from '@/components/feedback/alert/Alert.vue';
import { Motion, Presence } from '@motionone/vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const resendMessage = ref<string | null>(null);

const redirectAfterVerification = () => {
  const redirectPath = route.query.redirect as string | undefined;

  if (redirectPath) {
    router.push(redirectPath);
    return;
  }

  if (authStore.roles.includes('admin') || authStore.roles.includes('super-admin')) {
    router.push({ name: 'admin-dashboard' });
    return;
  }

  router.push({ name: 'home' });
};

onMounted(async () => {
  if (!authStore.requiresEmailVerification) {
    redirectAfterVerification();
    return;
  }

  const token = route.query.token as string | undefined;

  if (!token) {
    return;
  }

  try {
    const result = await authStore.verifyEmail(token);
    successMessage.value = result.message || 'Your email has been verified successfully.';

    setTimeout(() => {
      redirectAfterVerification();
    }, 3000);
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Email verification failed. Please request a new link.';
  }
});

const handleResend = async () => {
  errorMessage.value = null;
  resendMessage.value = null;

  try {
    const result = await authStore.resendVerification();
    resendMessage.value = result.message || 'Verification email sent. Please check your inbox.';
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Unable to resend verification email.';
  }
};
</script>

<template>
  <Motion
    :initial="{ opacity: 0, y: 15 }"
    :animate="{ opacity: 1, y: 0 }"
    :transition="{ duration: 0.4 }"
  >
    <div class="space-y-6 text-left">
      <div>
        <h2 class="text-2xl font-display font-bold text-primary">Verify Your Email</h2>
        <p class="text-xs text-neutral-muted mt-1">
          Confirm your SFU email address to activate your MSA account.
        </p>
      </div>

      <Presence>
        <Motion
          v-if="successMessage"
          :initial="{ opacity: 0, height: 0, y: -10 }"
          :animate="{ opacity: 1, height: 'auto', y: 0 }"
          :exit="{ opacity: 0, height: 0, y: -10 }"
          class="overflow-hidden"
        >
          <Alert type="success" title="Email Verified">
            {{ successMessage }}
          </Alert>
        </Motion>
      </Presence>

      <Presence>
        <Motion
          v-if="errorMessage"
          :initial="{ opacity: 0, height: 0, y: -10 }"
          :animate="{ opacity: 1, height: 'auto', y: 0 }"
          :exit="{ opacity: 0, height: 0, y: -10 }"
          class="overflow-hidden"
        >
          <Alert type="error" title="Verification Error">
            {{ errorMessage }}
          </Alert>
        </Motion>
      </Presence>

      <Presence>
        <Motion
          v-if="resendMessage"
          :initial="{ opacity: 0, height: 0, y: -10 }"
          :animate="{ opacity: 1, height: 'auto', y: 0 }"
          :exit="{ opacity: 0, height: 0, y: -10 }"
          class="overflow-hidden"
        >
          <Alert type="success" title="Email Sent">
            {{ resendMessage }}
          </Alert>
        </Motion>
      </Presence>

      <div v-if="!successMessage" class="space-y-4">
        <p class="text-sm text-neutral-muted leading-relaxed">
          We sent a verification link to
          <span class="font-semibold text-primary">{{ authStore.user?.email || 'your SFU email' }}</span>.
          Open the link in your inbox to continue.
        </p>

        <Button
          type="button"
          variant="primary"
          is-full-width
          :is-loading="authStore.isLoading"
          @click="handleResend"
        >
          Resend Verification Email
        </Button>
      </div>

      <p v-else class="text-xs text-neutral-muted">
        Redirecting you shortly...
      </p>
    </div>
  </Motion>
</template>
