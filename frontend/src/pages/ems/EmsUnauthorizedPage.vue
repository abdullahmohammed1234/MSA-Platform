<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { ShieldAlert } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useEmsAccessStore } from '@/stores/ems/emsAccess';

/**
 * Shown when the viewer is signed in but the EMS will not admit them, either
 * because they hold no EMS permissions at all or because the route they asked
 * for needs one they do not have.
 */
const route = useRoute();
const access = useEmsAccessStore();

const hasAnyAccess = computed(() => access.hasEmsAccess);
const attempted = computed(() => (route.query.from as string) ?? null);
</script>

<template>
  <div class="mx-auto flex min-h-[60vh] max-w-lg flex-col items-center justify-center text-center">
    <ShieldAlert class="h-12 w-12 text-secondary" aria-hidden="true" />

    <h1 class="mt-4 text-xl font-bold text-neutral-black">Access denied</h1>

    <p v-if="!hasAnyAccess" class="mt-2 text-sm text-neutral-muted">
      Your account does not have access to the Event Management System. Ask an MSA administrator to
      assign you an EMS role.
    </p>
    <p v-else class="mt-2 text-sm text-neutral-muted">
      You do not have permission to open
      <span v-if="attempted" class="font-mono text-neutral-black">{{ attempted }}</span>
      <span v-else>this page</span>.
    </p>

    <div class="mt-6 flex flex-wrap justify-center gap-2">
      <Button v-if="hasAnyAccess" variant="primary" @click="$router.push({ name: 'ems-dashboard' })">
        Go to dashboard
      </Button>
      <Button variant="outline" @click="$router.push('/')">Back to MSA Platform</Button>
    </div>
  </div>
</template>
