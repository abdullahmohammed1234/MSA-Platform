<script setup lang="ts">
import { ref } from 'vue';
import { usePwaInstall } from '../../composables/usePwaInstall';
import {
  Download,
  Share2,
  X,
  Smartphone,
  PlusSquare,
  CheckCircle2,
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

async function handleInstallClick() {
  if (isInstallable.value) {
    isInstalling.value = true;
    try {
      await promptInstall();
    } finally {
      isInstalling.value = false;
    }
  } else if (platform.value === 'ios') {
    showIosModal.value = true;
  }
}
</script>

<template>
  <div>
    <aside
      v-if="canShowBanner && !isInstalled"
      class="pm-card p-4 bg-gradient-to-r from-white via-white to-[#fffbf4] border border-[#640c0e]/20"
      role="region"
      aria-label="Install MSA Projects application"
    >
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-3 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-white border border-[#ebe8de] flex items-center justify-center shrink-0 overflow-hidden">
            <img src="/logo.webp" alt="" class="w-7 h-7 object-contain" />
          </div>
          <div class="space-y-1 min-w-0">
            <h2 class="text-sm font-bold text-[#640c0e]">Install MSA Projects</h2>
            <p class="text-[11px] sm:text-xs text-[#6b7280] leading-relaxed">
              {{
                platform === 'ios'
                  ? 'Add to your Home Screen for faster access and a standalone experience.'
                  : 'Install for one-tap access and a standalone application window.'
              }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-[#640c0e] text-white text-xs font-semibold hover:bg-[#500a0b] transition-all disabled:opacity-50 touch-target"
            :disabled="isInstalling"
            @click="handleInstallClick"
          >
            <Download v-if="platform !== 'ios'" class="w-3.5 h-3.5" />
            <Share2 v-else class="w-3.5 h-3.5 text-[#ffdc83]" />
            <span>{{ platform === 'ios' ? 'How to Install' : (isInstalling ? 'Installing…' : 'Install') }}</span>
          </button>
          <button
            type="button"
            class="p-2 rounded-lg text-[#6b7280] hover:bg-[#fffbf4] touch-target"
            aria-label="Dismiss installation prompt"
            @click="dismissBanner"
          >
            <X class="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>

    <Teleport to="body">
      <div
        v-if="showIosModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
        role="dialog"
        aria-modal="true"
        aria-labelledby="ios-pms-install-title"
        @click.self="showIosModal = false"
        @keydown.esc="showIosModal = false"
      >
        <div class="bg-white rounded-2xl border border-[#ebe8de] max-w-md w-full p-6 shadow-xl space-y-5">
          <div class="flex items-start justify-between gap-3 border-b border-[#ebe8de] pb-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-[#640c0e]/10 text-[#640c0e] flex items-center justify-center">
                <Smartphone class="w-4 h-4" />
              </div>
              <div>
                <h3 id="ios-pms-install-title" class="text-sm font-bold text-[#640c0e]">
                  Install on iPhone or iPad
                </h3>
                <p class="text-[11px] text-[#6b7280]">Add MSA PMS to your Home Screen in Safari.</p>
              </div>
            </div>
            <button
              type="button"
              class="p-1 rounded-lg text-[#6b7280] hover:bg-[#fffbf4]"
              aria-label="Close"
              @click="showIosModal = false"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <ol class="space-y-3 text-xs text-[#1e1e1e]">
            <li class="flex items-start gap-3 p-2.5 rounded-xl bg-[#fffbf4] border border-[#ebe8de]">
              <span class="w-5 h-5 rounded-full bg-[#640c0e] text-[#ffdc83] font-bold text-[11px] flex items-center justify-center shrink-0">1</span>
              <div>
                <p class="font-semibold flex items-center gap-1.5">Tap Share <Share2 class="w-3.5 h-3.5 text-[#640c0e]" /></p>
                <p class="text-[11px] text-[#6b7280] mt-0.5">Use the share icon in Safari’s toolbar.</p>
              </div>
            </li>
            <li class="flex items-start gap-3 p-2.5 rounded-xl bg-[#fffbf4] border border-[#ebe8de]">
              <span class="w-5 h-5 rounded-full bg-[#640c0e] text-[#ffdc83] font-bold text-[11px] flex items-center justify-center shrink-0">2</span>
              <div>
                <p class="font-semibold flex items-center gap-1.5">Add to Home Screen <PlusSquare class="w-3.5 h-3.5 text-[#640c0e]" /></p>
                <p class="text-[11px] text-[#6b7280] mt-0.5">Scroll the share sheet and choose Add to Home Screen.</p>
              </div>
            </li>
            <li class="flex items-start gap-3 p-2.5 rounded-xl bg-[#fffbf4] border border-[#ebe8de]">
              <span class="w-5 h-5 rounded-full bg-[#640c0e] text-[#ffdc83] font-bold text-[11px] flex items-center justify-center shrink-0">3</span>
              <div>
                <p class="font-semibold flex items-center gap-1.5">Confirm Add <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" /></p>
                <p class="text-[11px] text-[#6b7280] mt-0.5">Confirm the name “MSA PMS” and tap Add.</p>
              </div>
            </li>
          </ol>

          <div class="flex justify-end">
            <button
              type="button"
              class="px-4 py-2 rounded-xl bg-[#640c0e] text-white text-xs font-semibold"
              @click="showIosModal = false"
            >
              Got It
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
