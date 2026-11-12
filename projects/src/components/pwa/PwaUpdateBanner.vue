<script setup lang="ts">
import { ref, computed } from 'vue';
import { pwaUpdateAvailable, applyPwaUpdate } from '../../pwa/registerServiceWorker';
import { RefreshCw, X } from 'lucide-vue-next';

const dismissing = ref(false);
const applying = ref(false);

const visible = computed(() => pwaUpdateAvailable.value && !dismissing.value);

async function onUpdate() {
  applying.value = true;
  try {
    await applyPwaUpdate();
  } finally {
    applying.value = false;
  }
}

function onDismiss() {
  dismissing.value = true;
}
</script>

<template>
  <aside
    v-if="visible"
    class="pm-card p-4 border border-amber-200 bg-amber-50"
    role="status"
    aria-live="polite"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0 space-y-1">
        <h2 class="text-sm font-bold text-[#640c0e]">Update available</h2>
        <p class="text-[11px] sm:text-xs text-[#6b7280] leading-relaxed">
          A newer version of MSA Projects is ready. Save any open edits, then update when convenient.
        </p>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-[#640c0e] text-white text-xs font-semibold disabled:opacity-50 touch-target"
          :disabled="applying"
          @click="onUpdate"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': applying }" />
          <span>{{ applying ? 'Updating…' : 'Update now' }}</span>
        </button>
        <button
          type="button"
          class="p-2 rounded-lg text-[#6b7280] hover:bg-white touch-target"
          aria-label="Dismiss update notice"
          @click="onDismiss"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </div>
  </aside>
</template>
