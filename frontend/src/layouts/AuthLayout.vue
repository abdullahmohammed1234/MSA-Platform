<script setup lang="ts">
import { PUBLIC_LOGO } from '@/constants/publicAssets';
</script>

<template>
  <div class="min-h-screen bg-neutral-background flex flex-col items-center justify-center px-4 py-10 selection:bg-secondary selection:text-white">
    <div class="w-full max-w-md space-y-8">
      <div class="text-center space-y-3">
        <router-link to="/" class="inline-flex items-center justify-center">
          <img :src="PUBLIC_LOGO" alt="SFU MSA" class="h-14 w-14 object-contain" />
        </router-link>
        <p class="text-xs uppercase tracking-[0.25em] text-neutral-muted font-bold">SFU Muslim Students Association</p>
      </div>

      <div class="rounded-[2rem] border border-neutral-ivory bg-white p-8 shadow-premium">
        <router-view />
      </div>
    </div>
  </div>
</template>
