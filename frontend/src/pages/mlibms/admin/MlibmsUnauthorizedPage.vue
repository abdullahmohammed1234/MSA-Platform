<script setup lang="ts">
import { ShieldAlert, ArrowLeft } from 'lucide-vue-next';
</script>

<template>
  <div class="min-h-screen bg-neutral-background text-neutral-black flex items-center justify-center p-6 font-sans">
    <div class="max-w-md w-full bg-white border border-neutral-ivory rounded-3xl p-8 text-center space-y-6 shadow-soft">
      <div class="w-16 h-16 rounded-2xl bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-center mx-auto shadow-sm">
        <ShieldAlert class="w-8 h-8" />
      </div>
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black">Access Denied</h1>
        <p class="text-xs text-neutral-muted mt-2 leading-relaxed">
          You do not have administrative access permissions for the MSA Library Management System (MLibMS).
        </p>
      </div>
      <div class="pt-2">
        <router-link
          to="/library"
          class="inline-flex items-center space-x-2 px-5 py-2.5 rounded-xl bg-neutral-background hover:bg-neutral-ivory text-neutral-black font-bold text-xs border border-neutral-ivory transition-all shadow-sm"
        >
          <ArrowLeft class="w-4 h-4 text-primary" />
          <span>Return to Public Library</span>
        </router-link>
      </div>
    </div>
  </div>
</template>

