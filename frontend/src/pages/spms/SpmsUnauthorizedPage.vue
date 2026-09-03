<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAppAccess } from '@/composables/auth/useAppAccess';
import { ShieldAlert } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const route = useRoute();
const router = useRouter();
const appAccess = useAppAccess();

const hasSpmsAccess = computed(() => appAccess.hasSponsorshipAccess.value);
const attemptedPath = computed(() => (route.query.from as string) ?? null);
</script>

<template>
  <div class="mx-auto flex min-h-[60vh] max-w-lg flex-col items-center justify-center text-center p-6">
    <ShieldAlert class="h-12 w-12 text-secondary" aria-hidden="true" />
    <h1 class="mt-4 text-xl font-bold text-neutral-black">Access Denied</h1>

    <p v-if="hasSpmsAccess" class="mt-2 text-sm text-neutral-muted">
      You do not have permission to open
      <span v-if="attemptedPath" class="font-mono text-neutral-black">{{ attemptedPath }}</span>
      <span v-else>this page</span>.
    </p>

    <p v-else class="mt-2 text-sm text-neutral-muted">
      Your account does not have access to the Sponsorship & Partnerships Management System (SPMS). Ask an MSA administrator to grant you SPMS access or assign an SPMS role.
    </p>

    <div class="mt-6 flex flex-wrap justify-center gap-3">
      <Button
        v-if="hasSpmsAccess"
        variant="primary"
        @click="router.push({ name: 'spms-dashboard' })"
      >
        Go to SPMS Dashboard
      </Button>

      <Button
        variant="outline"
        @click="router.push('/')"
      >
        Back to SFU MSA Website
      </Button>
    </div>
  </div>
</template>
