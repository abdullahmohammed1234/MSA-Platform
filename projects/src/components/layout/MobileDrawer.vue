<script setup lang="ts">
import { watch, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { PM_NAV_ITEMS, isPmNavActive } from '../../navigation';
import {
  PlusCircle,
  X,
  ExternalLink,
} from 'lucide-vue-next';

const props = defineProps<{
  open: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'open-create-modal'): void;
}>();

const route = useRoute();
const authStore = useAuthStore();
const portalUrl = import.meta.env.VITE_PORTAL_URL || 'https://sfumsa.ca';

watch(
  () => props.open,
  (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
  }
);

watch(
  () => route.fullPath,
  () => {
    if (props.open) emit('close');
  }
);

onUnmounted(() => {
  document.body.style.overflow = '';
});

const onCreate = () => {
  emit('close');
  emit('open-create-modal');
};
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 lg:hidden"
      role="dialog"
      aria-modal="true"
      aria-label="Main navigation"
    >
      <button
        type="button"
        class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"
        aria-label="Close navigation"
        @click="emit('close')"
      />

      <aside
        class="absolute inset-y-0 left-0 w-[min(100%,20rem)] bg-white shadow-elevated border-r border-[#ebe8de] flex flex-col safe-area-pad safe-area-top animate-in slide-in-from-left duration-200"
      >
        <div class="flex items-center justify-between gap-3 px-4 py-4 border-b border-[#ebe8de]">
          <router-link
            to="/"
            class="flex items-center gap-2.5 min-w-0"
            @click="emit('close')"
          >
            <div class="relative w-10 h-10 rounded-xl bg-white border border-[#ebe8de] shadow-xs flex items-center justify-center overflow-hidden shrink-0">
              <img src="/logo.webp" alt="SFU MSA logo" class="w-8 h-8 object-contain" />
            </div>
            <div class="min-w-0">
              <p class="font-extrabold text-[#640c0e] text-sm tracking-tight font-display leading-tight">
                SFU MSA <span class="font-semibold text-[#b02e32]">Projects</span>
              </p>
              <p class="text-[10px] uppercase tracking-widest text-[#6b7280] font-semibold">
                Project Management
              </p>
            </div>
          </router-link>

          <button
            type="button"
            class="touch-target p-2 rounded-xl text-[#6b7280] hover:bg-[#fffbf4] hover:text-[#640c0e] transition-colors"
            aria-label="Close menu"
            @click="emit('close')"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-6">
          <button
            type="button"
            class="w-full touch-target flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl font-bold text-xs bg-gradient-to-r from-[#640c0e] to-[#b02e32] text-white shadow-xs hover:opacity-95 transition-all"
            @click="onCreate"
          >
            <PlusCircle class="w-4 h-4" /> New Project
          </button>

          <nav class="space-y-1" aria-label="Primary">
            <router-link
              v-for="item in PM_NAV_ITEMS"
              :key="item.path"
              :to="item.path"
              class="flex items-center gap-3 px-3.5 py-3 rounded-xl text-sm font-semibold transition-all touch-target"
              :class="[
                isPmNavActive(route.path, item.path)
                  ? 'bg-[#640c0e]/10 text-[#640c0e]'
                  : 'text-[#6b7280] hover:bg-[#fffbf4] hover:text-[#1e1e1e]',
              ]"
            >
              <component
                :is="item.icon"
                class="w-5 h-5 shrink-0"
                :class="isPmNavActive(route.path, item.path) ? 'text-[#640c0e]' : 'text-[#6b7280]'"
              />
              <span>{{ item.name }}</span>
            </router-link>
          </nav>

          <div v-if="authStore.departments.length" class="pt-2 border-t border-[#ebe8de]">
            <h3 class="text-[11px] font-bold uppercase tracking-wider text-[#6b7280] mb-2 px-3">
              My Departments
            </h3>
            <div class="space-y-1">
              <div
                v-for="dept in authStore.departments"
                :key="dept.id"
                class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-medium text-[#1e1e1e]"
              >
                <span class="flex items-center gap-2 min-w-0">
                  <span class="w-2 h-2 rounded-full bg-[#640c0e] shrink-0" />
                  <span class="truncate">{{ dept.name }}</span>
                </span>
                <span class="text-[10px] text-[#6b7280] uppercase tracking-wider font-semibold shrink-0">
                  {{ dept.role }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-[#ebe8de] space-y-3">
          <a
            :href="portalUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-xs font-semibold text-[#640c0e] hover:bg-[#fffbf4] transition-colors"
          >
            <ExternalLink class="w-4 h-4" />
            Main MSA Platform
          </a>
          <p class="text-[11px] text-[#6b7280] px-3">SFU MSA &copy; 2026</p>
        </div>
      </aside>
    </div>
  </Teleport>
</template>
