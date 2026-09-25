<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const attempted = computed(() => (route.query.from as string) ?? null);
</script>

<template>
  <div class="mx-auto flex min-h-[60vh] max-w-lg flex-col items-center justify-center text-center">
    <div class="p-3 bg-red-100 rounded-full text-red-600 mb-4">
      <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
    </div>

    <h1 class="text-xl font-bold text-neutral-black">Access Denied</h1>

    <p class="mt-2 text-sm text-neutral-muted">
      You do not have permission to access the Volunteer Management System or open
      <span v-if="attempted" class="font-mono text-neutral-black">{{ attempted }}</span>.
      Please request VMS application access from an MSA administrator.
    </p>

    <div class="mt-6 flex flex-wrap justify-center gap-3">
      <button
        type="button"
        class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-primary-dark transition cursor-pointer"
        @click="$router.push('/vms')"
      >
        Go to VMS Opportunities
      </button>
      <button
        type="button"
        class="px-4 py-2 border border-neutral-ivory bg-white text-neutral-black text-sm font-semibold rounded-xl hover:bg-neutral-background transition cursor-pointer"
        @click="$router.push('/')"
      >
        Back to MSA Platform
      </button>
    </div>
  </div>
</template>
