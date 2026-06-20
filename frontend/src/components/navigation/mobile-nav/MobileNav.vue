<script setup lang="ts">
import { Presence, Motion } from '@motionone/vue';
import type { MobileNavProps } from './types';
import { modalOverlay } from '@/design-system/animations/modal';
import { drawerLeft } from '@/design-system/animations/drawer';

const props = withDefaults(defineProps<MobileNavProps>(), {
  brandName: 'SFU MSA',
  isAuthenticated: false
});

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'logout'): void;
}>();

const close = () => {
  emit('close');
};
</script>

<template>
  <Presence>
    <div v-if="isOpen" class="fixed inset-0 z-50 md:hidden flex">
      
      <!-- Backdrop Overlay -->
      <Motion
        :initial="modalOverlay.initial"
        :animate="modalOverlay.animate"
        :exit="modalOverlay.exit"
        :transition="modalOverlay.transition"
        class="absolute inset-0 bg-black/40 backdrop-blur-xs cursor-pointer z-40"
        @click="close"
      />

      <!-- Drawer Content -->
      <Motion
        :initial="drawerLeft.initial"
        :animate="drawerLeft.animate"
        :exit="drawerLeft.exit"
        :transition="drawerLeft.transition"
        class="relative flex-1 flex flex-col max-w-xs w-full bg-white shadow-premium-lg h-full border-r border-neutral-ivory z-50 py-4"
      >
        <!-- Top row: Brand & Close -->
        <div class="px-4 flex items-center justify-between border-b border-neutral-ivory/50 pb-4 flex-shrink-0">
          <div class="flex items-center gap-2">
            <div class="h-8 w-8 rounded-full bg-primary flex items-center justify-center text-white font-display font-bold text-sm">
              M
            </div>
            <span class="font-display font-bold text-sm text-primary tracking-wide">
              {{ brandName }}
            </span>
          </div>
          
          <button
            @click="close"
            class="p-1 rounded-lg hover:bg-neutral-background text-neutral-muted hover:text-primary transition-colors cursor-pointer"
            aria-label="Close menu"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Navigation items list -->
        <div class="flex-1 overflow-y-auto px-2 py-4 space-y-1">
          <router-link
            v-for="item in navItems"
            :key="item.path"
            :to="item.path"
            class="block px-4 py-2 rounded-xl text-base font-medium text-neutral-muted hover:text-primary hover:bg-neutral-background transition-colors"
            active-class="text-primary font-semibold bg-neutral-ivory/50"
            @click="close"
          >
            {{ item.label }}
          </router-link>
        </div>

        <!-- Footer / Auth controls -->
        <div class="px-4 border-t border-neutral-ivory/50 pt-4 pb-2 flex-shrink-0">
          <div v-if="!isAuthenticated" class="space-y-2">
            <router-link to="/login" class="w-full block" @click="close">
              <button class="w-full text-center text-primary font-semibold py-2.5 hover:bg-neutral-background rounded-lg text-sm transition-colors border border-primary/20">
                Sign In
              </button>
            </router-link>
            <router-link to="/register" class="w-full block" @click="close">
              <button class="w-full text-center bg-primary text-white font-semibold py-2.5 rounded-lg hover:bg-primary/95 text-sm transition-colors shadow-sm">
                Join Us
              </button>
            </router-link>
          </div>
          <div v-else class="space-y-2">
            <button
              @click="$emit('logout'); close()"
              class="w-full text-center text-secondary font-semibold py-2.5 hover:bg-neutral-background rounded-lg text-sm transition-colors cursor-pointer border border-secondary/20"
            >
              Sign Out
            </button>
          </div>
        </div>

      </Motion>

    </div>
  </Presence>
</template>
