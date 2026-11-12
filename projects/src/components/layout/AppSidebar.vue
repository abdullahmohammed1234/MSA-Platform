<script setup lang="ts">
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { PM_NAV_ITEMS, isPmNavActive } from '../../navigation';
import { PlusCircle } from 'lucide-vue-next';

defineEmits<{
  (e: 'open-create-modal'): void;
}>();

const route = useRoute();
const authStore = useAuthStore();
</script>

<template>
  <aside class="w-64 border-r border-[#ebe8de] bg-white h-[calc(100vh-4rem)] sticky top-16 flex-col justify-between p-4 hidden lg:flex shrink-0">
    <div class="space-y-6">
      <button
        type="button"
        class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl font-bold text-xs bg-gradient-to-r from-[#640c0e] to-[#b02e32] text-white shadow-xs hover:shadow-brand hover:opacity-95 transition-all"
        @click="$emit('open-create-modal')"
      >
        <PlusCircle class="w-4 h-4" /> New Project
      </button>

      <nav class="space-y-1" aria-label="Primary">
        <router-link
          v-for="item in PM_NAV_ITEMS"
          :key="item.path"
          :to="item.path"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all group"
          :class="[
            isPmNavActive(route.path, item.path)
              ? 'bg-[#640c0e]/10 text-[#640c0e]'
              : 'text-[#6b7280] hover:bg-[#fffbf4] hover:text-[#1e1e1e]',
          ]"
        >
          <component
            :is="item.icon"
            class="w-4 h-4 transition-colors"
            :class="isPmNavActive(route.path, item.path) ? 'text-[#640c0e]' : 'text-[#6b7280] group-hover:text-[#1e1e1e]'"
          />
          <span>{{ item.name }}</span>
        </router-link>
      </nav>

      <div class="pt-4 border-t border-[#ebe8de]">
        <h3 class="text-[11px] font-bold uppercase tracking-wider text-[#6b7280] mb-2 px-3">
          My Departments
        </h3>
        <div class="space-y-1">
          <div
            v-for="dept in authStore.departments"
            :key="dept.id"
            class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium text-[#1e1e1e] hover:bg-[#fffbf4]"
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

    <div class="pt-4 border-t border-[#ebe8de] text-[11px] text-[#6b7280] flex items-center justify-between gap-2">
      <span class="flex items-center gap-2 min-w-0">
        <img src="/logo.webp" alt="" class="w-4 h-4 object-contain opacity-80" />
        <span class="truncate">SFU MSA &copy; 2026</span>
      </span>
      <span class="font-medium text-[#640c0e] shrink-0">v1.0.0</span>
    </div>
  </aside>
</template>
