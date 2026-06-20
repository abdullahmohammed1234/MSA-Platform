<script setup lang="ts">
import { computed } from 'vue';
import { Presence, Motion } from '@motionone/vue';
import type { DrawerProps } from './types';
import { modalOverlay } from '@/design-system/animations/modal';
import { drawerLeft, drawerRight, drawerBottom } from '@/design-system/animations/drawer';

const props = withDefaults(defineProps<DrawerProps>(), {
  position: 'right',
  title: '',
  size: ''
});

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const close = () => {
  emit('close');
};

const animPreset = computed(() => {
  if (props.position === 'left') return drawerLeft;
  if (props.position === 'bottom') return drawerBottom;
  return drawerRight;
});

const positionClasses = computed(() => {
  const base = 'fixed bg-white shadow-premium-lg border-neutral-ivory flex flex-col z-50';
  
  if (props.position === 'left') {
    return `${base} left-0 inset-y-0 h-full border-r ${props.size || 'w-80'}`;
  }
  if (props.position === 'bottom') {
    return `${base} bottom-0 inset-x-0 w-full border-t ${props.size || 'h-80'}`;
  }
  // Right side (default)
  return `${base} right-0 inset-y-0 h-full border-l ${props.size || 'w-80'}`;
});
</script>

<template>
  <Presence>
    <div v-if="isOpen" class="fixed inset-0 z-50 overflow-hidden">
      
      <!-- Backdrop Dark blur Overlay -->
      <Motion
        :initial="modalOverlay.initial"
        :animate="modalOverlay.animate"
        :exit="modalOverlay.exit"
        :transition="modalOverlay.transition"
        class="absolute inset-0 bg-black/40 backdrop-blur-xs cursor-pointer z-40"
        @click="close"
      />

      <!-- Sliding Panel -->
      <Motion
        :initial="animPreset.initial"
        :animate="animPreset.animate"
        :exit="animPreset.exit"
        :transition="animPreset.transition"
        :class="positionClasses"
      >
        <!-- Header -->
        <div class="px-5 py-4 border-b border-neutral-ivory/50 flex items-center justify-between flex-shrink-0">
          <slot name="header">
            <h3 class="text-base font-bold text-neutral-black font-display truncate">
              {{ title }}
            </h3>
          </slot>
          
          <button
            @click="close"
            class="p-1 rounded-lg hover:bg-neutral-background text-neutral-muted hover:text-primary transition-colors cursor-pointer"
            aria-label="Close panel"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body Content -->
        <div class="flex-1 overflow-y-auto p-5">
          <slot></slot>
        </div>

        <!-- Footer -->
        <div v-if="$slots.footer" class="px-5 py-4 border-t border-neutral-ivory/50 bg-neutral-background/30 flex-shrink-0">
          <slot name="footer"></slot>
        </div>

      </Motion>

    </div>
  </Presence>
</template>
