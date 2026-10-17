<script setup lang="ts">
import { usePwaInstall } from '@/composables/usePwaInstall';
import { Download, X, Smartphone, Share2 } from 'lucide-vue-next';

const {
  canInstall,
  canShowIosPrompt,
  canShowPrompt,
  promptInstall,
  dismissInstall,
} = usePwaInstall();

const handleInstall = async () => {
  await promptInstall();
};

const handleDismiss = () => {
  dismissInstall();
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
      v-if="canShowPrompt"
      class="fixed bottom-20 sm:bottom-6 left-4 right-4 sm:left-auto sm:right-6 sm:w-full sm:max-w-sm z-[90] bg-white p-5 rounded-3xl border border-[#ebe8de] shadow-premium space-y-4"
      role="dialog"
      aria-label="Install SFU MSA Connect App"
    >
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-2xl bg-[#640c0e]/10 text-[#640c0e] flex items-center justify-center shrink-0 border border-[#640c0e]/20">
            <Smartphone class="h-6 w-6 stroke-[2]" />
          </div>
          <div>
            <div class="flex items-center gap-1.5">
              <h3 class="font-display font-black text-sm text-neutral-900">Install MSA Connect</h3>
              <span class="px-1.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-[#ffdc83] text-[#640c0e] tracking-wide">
                Portal
              </span>
            </div>
            <p v-if="canShowIosPrompt" class="text-xs text-neutral-700 font-medium leading-relaxed mt-0.5">
              Tap the <Share2 class="inline h-3.5 w-3.5 text-[#640c0e] stroke-[2.5] mx-0.5" /> Share button below, then tap <span class="font-bold text-neutral-900">"Add to Home Screen"</span>.
            </p>
            <p v-else class="text-xs text-neutral-600 font-medium mt-0.5">
              Add to your home screen for quick access on your device.
            </p>
          </div>
        </div>

        <button
          @click="handleDismiss"
          class="text-neutral-400 hover:text-neutral-900 p-1 rounded-lg transition-colors cursor-pointer shrink-0"
          aria-label="Dismiss app install banner"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <div class="flex items-center gap-2 pt-1">
        <button
          v-if="canInstall"
          @click="handleInstall"
          class="flex-1 py-2.5 px-4 bg-[#640c0e] hover:bg-[#b02e32] text-white rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all inline-flex items-center justify-center gap-2 shadow-soft cursor-pointer"
        >
          <Download class="h-4 w-4" />
          Install App
        </button>

        <button
          @click="handleDismiss"
          class="flex-1 px-4 py-2.5 bg-[#fffbf4] hover:bg-[#ebe8de] text-neutral-900 rounded-xl text-xs font-bold transition-all border border-[#ebe8de] cursor-pointer text-center"
        >
          {{ canShowIosPrompt ? 'Got It' : 'Not Now' }}
        </button>
      </div>
    </div>
  </Transition>
</template>
