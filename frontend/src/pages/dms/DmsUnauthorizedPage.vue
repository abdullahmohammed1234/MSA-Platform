<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAppAccess } from '@/composables/auth/useAppAccess';
import { ShieldAlert } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const route = useRoute();
const router = useRouter();
const appAccess = useAppAccess();

const hasDmsAccess = computed(() => appAccess.hasDonationsAccess.value);
const attemptedPath = computed(() => (route.query.from as string) ?? null);
</script>

<template>
  <div class="mx-auto flex min-h-[60vh] max-w-lg flex-col items-center justify-center text-center p-6">
    <ShieldAlert class="h-12 w-12 text-secondary" aria-hidden="true" />
    <h1 class="mt-4 text-xl font-bold text-neutral-black">Access Denied</h1>

    <p v-if="hasDmsAccess" class="mt-2 text-sm text-neutral-muted">
      You do not have permission to open
      <span v-if="attemptedPath" class="font-mono text-neutral-black">{{ attemptedPath }}</span>
      <span v-else>this page</span>.
    </p>

    <p v-else class="mt-2 text-sm text-neutral-muted">
      Your account does not have access to the Donation Management System (DMS). Ask an MSA administrator to grant you DMS access or assign a DMS role.
    </p>

    <div class="mt-6 flex flex-wrap justify-center gap-3">
      <Button
        v-if="hasDmsAccess"
        variant="primary"
        @click="router.push({ name: 'dms-dashboard' })"
      >
        Go to DMS Dashboard
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
