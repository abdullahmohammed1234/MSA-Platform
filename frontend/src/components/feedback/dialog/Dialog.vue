<script setup lang="ts">
import { computed, watch, onMounted, onUnmounted } from 'vue';
import { Presence, Motion } from '@motionone/vue';
import type { DialogProps } from './types';
import { modalOverlay, modalContent } from '@/design-system/animations/modal';

const props = withDefaults(defineProps<DialogProps>(), {
  title: '',
  size: 'md'
});

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const close = () => {
  emit('close');
};

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.isOpen) {
    close();
  }
};

const toggleBodyScroll = (disable: boolean) => {
  if (disable) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
};

watch(() => props.isOpen, (open) => {
  toggleBodyScroll(open);
});

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
  if (props.isOpen) {
    toggleBodyScroll(true);
  }
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
  toggleBodyScroll(false);
});

const sizeClasses = computed(() => {
  const base = 'w-full bg-white border border-neutral-ivory rounded-2xl shadow-premium-lg flex flex-col z-50 overflow-hidden relative';
  
  const sizes = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-5xl',
    fullscreen: 'max-w-full h-full rounded-none border-none'
  };

  return `${base} ${sizes[props.size]}`;
});
</script>

<template>
  <Presence>
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
    >
      
      <!-- Backdrop blur Overlay -->
      <Motion
        :initial="modalOverlay.initial"
        :animate="modalOverlay.animate"
        :exit="modalOverlay.exit"
        :transition="modalOverlay.transition"
        class="fixed inset-0 bg-black/40 backdrop-blur-xs cursor-pointer z-40"
        @click="close"
      />

      <!-- Dialogue panel contents -->
      <Motion
        :initial="modalContent.initial"
        :animate="modalContent.animate"
        :exit="modalContent.exit"
        :transition="modalContent.transition"
        :class="sizeClasses"
        role="dialog"
        aria-modal="true"
      >
        <!-- Header -->
        <div class="px-6 py-4 border-b border-neutral-ivory/50 flex items-center justify-between flex-shrink-0">
          <slot name="header">
            <h3 class="text-base font-bold text-neutral-black font-display truncate">
              {{ title }}
            </h3>
          </slot>
          
          <button
            @click="close"
            class="p-1 rounded-lg hover:bg-neutral-background text-neutral-muted hover:text-primary transition-colors cursor-pointer"
            aria-label="Close dialog"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto p-6 text-sm text-neutral-muted">
          <slot></slot>
        </div>

        <!-- Footer -->
        <div v-if="$slots.footer" class="px-6 py-4 border-t border-neutral-ivory/50 bg-neutral-background/35 flex justify-end gap-3 flex-shrink-0">
          <slot name="footer"></slot>
        </div>

      </Motion>

    </div>
  </Presence>
</template>
