<script setup lang="ts">
import { FileQuestion } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
</script>

<template>
  <div class="mx-auto flex min-h-[60vh] max-w-lg flex-col items-center justify-center text-center">
    <FileQuestion class="h-12 w-12 text-neutral-gray" aria-hidden="true" />

    <h1 class="mt-4 text-xl font-bold text-neutral-black">Page not found</h1>
    <p class="mt-2 text-sm text-neutral-muted">
      That page does not exist in the Event Management System. It may have been moved or deleted.
    </p>

    <div class="mt-6 flex flex-wrap justify-center gap-2">
      <Button variant="primary" @click="$router.push({ name: 'ems-dashboard' })">
        Go to dashboard
      </Button>
      <Button variant="outline" @click="$router.back()">Go back</Button>
    </div>
  </div>
</template>
