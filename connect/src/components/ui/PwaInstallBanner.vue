<script setup lang="ts">
import { ref } from 'vue';
import { usePwaInstall } from '@/composables/usePwaInstall';
import {
  Download,
  Share2,
  X,
  Smartphone,
  PlusSquare,
  CheckCircle2,
  Info,
} from 'lucide-vue-next';

const {
  platform,
  isInstalled,
  isInstallable,
  canShowBanner,
  promptInstall,
  dismissBanner,
} = usePwaInstall();

const isInstalling = ref(false);
const showIosModal = ref(false);
const installMessage = ref<string | null>(null);

async function handleInstallClick() {
  if (isInstallable.value) {
    isInstalling.value = true;
    try {
      const outcome = await promptInstall();
      if (outcome === 'accepted') {
        installMessage.value = 'Thank you for installing SFU MSA Connect!';
      }
    } finally {
      isInstalling.value = false;
    }
  } else if (platform.value === 'ios') {
    showIosModal.value = true;
  }
}

function handleDismiss() {
  dismissBanner();
}
</script>

<template>
  <div>
    <!-- Main Install Banner (shown if not installed and not dismissed) -->
    <aside
      v-if="canShowBanner && !isInstalled"
      class="community-card p-4 sm:p-5 bg-gradient-to-r from-white via-white to-[#fffbf4] border border-[#640c0e]/20 shadow-brand relative overflow-hidden"
      role="region"
      aria-label="Install MSA Connect Application"
    >
      <div class="flex items-start justify-between gap-3 sm:gap-4">
        <!-- Icon & Content -->
        <div class="flex items-start gap-3 sm:gap-3.5 min-w-0">
          <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#640c0e] text-[#ffdc83] flex items-center justify-center shrink-0 shadow-xs ring-2 ring-[#ffdc83]/40">
            <Smartphone class="w-5 h-5 sm:w-6 sm:h-6" />
          </div>

          <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <h2 class="text-xs sm:text-sm font-bold text-[#640c0e]">
                Install SFU MSA Connect App
              </h2>
              <span class="px-1.5 py-0.2 rounded-full text-[9.5px] font-bold bg-[#ffdc83]/60 text-[#640c0e]">
                PWA
              </span>
            </div>
            <p class="text-[11px] sm:text-xs text-neutral-600 leading-relaxed">
              {{
                platform === 'ios'
                  ? 'Add to your Home Screen for faster access, a standalone full-screen experience, and instant push alerts.'
                  : 'Install to your device for 1-tap access, native app windows, and synchronous event notifications.'
              }}
            </p>
          </div>
        </div>

        <!-- Action & Dismiss Buttons -->
        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            @click="handleInstallClick"
            :disabled="isInstalling"
            class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-1.5 sm:py-2 rounded-xl bg-[#640c0e] text-white text-xs font-semibold hover:bg-[#500a0b] active:scale-95 transition-all shadow-xs cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#640c0e]/30 disabled:opacity-50"
          >
            <Download v-if="platform !== 'ios'" class="w-3.5 h-3.5" :class="{ 'animate-bounce': isInstalling }" />
            <Share2 v-else class="w-3.5 h-3.5 text-[#ffdc83]" />
            <span>{{ platform === 'ios' ? 'How to Install' : (isInstalling ? 'Installing...' : 'Install App') }}</span>
          </button>

          <button
            type="button"
            @click="handleDismiss"
            class="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#640c0e]/30"
            aria-label="Dismiss installation prompt"
            title="Dismiss"
          >
            <X class="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>

    <!-- iOS Manual Install Guidance Modal -->
    <teleport to="body">
      <div
        v-if="showIosModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs transition-opacity"
        @click.self="showIosModal = false"
        @keydown.esc="showIosModal = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="ios-modal-title"
      >
        <div class="bg-white rounded-2xl border border-[#ebe8de] max-w-md w-full p-6 shadow-xl space-y-5 animate-in fade-in zoom-in-95 duration-150">
          <div class="flex items-start justify-between gap-3 border-b border-[#ebe8de]/60 pb-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-[#640c0e]/10 text-[#640c0e] flex items-center justify-center">
                <Smartphone class="w-4 h-4" />
              </div>
              <div>
                <h3 id="ios-modal-title" class="text-sm font-bold text-[#640c0e]">
                  Install on iPhone or iPad
                </h3>
                <p class="text-[11px] text-neutral-500">
                  Safari requires 3 quick steps to add Connect to your Home Screen.
                </p>
              </div>
            </div>

            <button
              type="button"
              @click="showIosModal = false"
              class="p-1 rounded-lg text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 transition-colors cursor-pointer"
              aria-label="Close modal"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Step by Step Instructions -->
          <ol class="space-y-3.5 text-xs text-neutral-700">
            <li class="flex items-start gap-3 p-2.5 rounded-xl bg-[#fffbf4] border border-[#ebe8de]/70">
              <span class="w-5 h-5 rounded-full bg-[#640c0e] text-[#ffdc83] font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5">
                1
              </span>
              <div class="space-y-0.5">
                <p class="font-semibold text-neutral-900 flex items-center gap-1.5">
                  <span>Tap the Share Button</span>
                  <Share2 class="w-3.5 h-3.5 text-[#640c0e] inline" />
                </p>
                <p class="text-[11px] text-neutral-500 leading-relaxed">
                  In the bottom navigation toolbar in Safari (or top bar on iPad), tap the square icon with an arrow pointing up.
                </p>
              </div>
            </li>

            <li class="flex items-start gap-3 p-2.5 rounded-xl bg-[#fffbf4] border border-[#ebe8de]/70">
              <span class="w-5 h-5 rounded-full bg-[#640c0e] text-[#ffdc83] font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5">
                2
              </span>
              <div class="space-y-0.5">
                <p class="font-semibold text-neutral-900 flex items-center gap-1.5">
                  <span>Scroll & Tap "Add to Home Screen"</span>
                  <PlusSquare class="w-3.5 h-3.5 text-[#640c0e] inline" />
                </p>
                <p class="text-[11px] text-neutral-500 leading-relaxed">
                  Scroll down the share sheet menu and select the <strong>Add to Home Screen</strong> option.
                </p>
              </div>
            </li>

            <li class="flex items-start gap-3 p-2.5 rounded-xl bg-[#fffbf4] border border-[#ebe8de]/70">
              <span class="w-5 h-5 rounded-full bg-[#640c0e] text-[#ffdc83] font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5">
                3
              </span>
              <div class="space-y-0.5">
                <p class="font-semibold text-neutral-900 flex items-center gap-1.5">
                  <span>Tap "Add" in Top Right</span>
                  <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 inline" />
                </p>
                <p class="text-[11px] text-neutral-500 leading-relaxed">
                  Confirm the name "MSA Connect" and tap Add. The application icon will appear on your Home Screen.
                </p>
              </div>
            </li>
          </ol>

          <!-- iOS Push Notification Notice -->
          <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-[11px] flex items-start gap-2">
            <Info class="w-3.5 h-3.5 text-amber-700 shrink-0 mt-0.5" />
            <p class="leading-relaxed">
              <strong>Web Push on iOS:</strong> Apple requires web applications to be added to the Home Screen to enable Web Push notifications.
            </p>
          </div>

          <div class="pt-2 flex justify-end">
            <button
              type="button"
              @click="showIosModal = false"
              class="px-4 py-2 rounded-xl bg-[#640c0e] text-white text-xs font-semibold hover:bg-[#500a0b] transition-colors cursor-pointer"
            >
              Got It
            </button>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>
