<script setup lang="ts">
import { usePwaStore } from '@/stores/pwa';
import { Download, X, Smartphone, Share } from 'lucide-vue-next';

const pwaStore = usePwaStore();

const handleInstall = async () => {
  await pwaStore.promptInstall();
};

const handleDismiss = () => {
  pwaStore.dismissInstall();
};
</script>

<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="transform translate-y-8 opacity-0"
    enter-to-class="transform translate-y-0 opacity-100"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="transform translate-y-0 opacity-100"
    leave-to-class="transform translate-y-8 opacity-0"
  >
    <div
      v-if="pwaStore.canShowPrompt"
      class="fixed bottom-6 left-4 right-4 sm:left-auto sm:right-6 sm:w-full sm:max-w-sm z-[90] bg-white p-5 rounded-3xl border border-neutral-ivory shadow-premium space-y-4"
      role="dialog"
      aria-label="Install SFU MSA Web App"
    >
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0 border border-primary/20">
            <Smartphone class="h-6 w-6 stroke-[2]" />
          </div>
          <div>
            <h3 class="font-display font-black text-sm text-neutral-black">Install SFU MSA App</h3>
            <p v-if="pwaStore.canShowIosPrompt" class="text-xs text-neutral-black/70 font-medium leading-relaxed">
              Tap the <Share class="inline h-3.5 w-3.5 text-primary stroke-[2.5] mx-0.5" /> Share button below, then tap <span class="font-bold text-neutral-black">"Add to Home Screen"</span>.
            </p>
            <p v-else class="text-xs text-neutral-black/60 font-medium">
              Add to your home screen for quick access on your device.
            </p>
          </div>
        </div>

        <button
          @click="handleDismiss"
          class="text-neutral-black/40 hover:text-neutral-black p-1 rounded-lg transition-colors cursor-pointer shrink-0"
          aria-label="Dismiss app install banner"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <div class="flex items-center gap-2 pt-1">
        <button
          v-if="pwaStore.canInstall"
          @click="handleInstall"
          class="flex-1 py-2.5 px-4 bg-primary hover:bg-secondary text-white rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all inline-flex items-center justify-center gap-2 shadow-soft cursor-pointer"
        >
          <Download class="h-4 w-4" />
          Install App
        </button>

        <button
          @click="handleDismiss"
          class="flex-1 px-4 py-2.5 bg-neutral-background hover:bg-neutral-ivory text-neutral-black rounded-xl text-xs font-bold transition-all border border-neutral-ivory cursor-pointer text-center"
        >
          {{ pwaStore.canShowIosPrompt ? 'Got It' : 'Not Now' }}
        </button>
      </div>
    </div>
  </Transition>
</template>
