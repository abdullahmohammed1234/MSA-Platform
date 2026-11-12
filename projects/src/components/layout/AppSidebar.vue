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
  <aside
    class="border-r border-[#ebe8de] bg-white h-[calc(100vh-3.5rem)] sm:h-[calc(100vh-4rem)] sticky top-14 sm:top-16 flex-col justify-between hidden lg:flex shrink-0"
    :style="{ width: 'var(--sidebar-width)', padding: '1rem 0.875rem' }"
  >
    <div class="space-y-5">
      <button
        type="button"
        class="w-full flex items-center justify-center gap-2 h-10 px-3 rounded-xl font-bold text-xs bg-gradient-to-r from-[#640c0e] to-[#b02e32] text-white shadow-xs hover:opacity-95 transition-all"
        @click="$emit('open-create-modal')"
      >
        <PlusCircle class="w-4 h-4" /> New Project
      </button>

      <nav class="space-y-0.5" aria-label="Primary">
        <router-link
          v-for="item in PM_NAV_ITEMS"
          :key="item.path"
          :to="item.path"
          class="flex items-center gap-2.5 h-10 px-3 rounded-xl text-[13px] font-semibold transition-all group"
          :class="[
            isPmNavActive(route.path, item.path)
              ? 'bg-[#640c0e]/10 text-[#640c0e]'
              : 'text-[#6b7280] hover:bg-[#fffbf4] hover:text-[#1e1e1e]',
          ]"
        >
          <component
            :is="item.icon"
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isPmNavActive(route.path, item.path) ? 'text-[#640c0e]' : 'text-[#6b7280] group-hover:text-[#1e1e1e]'"
          />
          <span class="truncate">{{ item.name }}</span>
        </router-link>
      </nav>

      <div class="pt-4 border-t border-[#ebe8de]">
        <h3 class="text-[10px] font-bold uppercase tracking-wider text-[#6b7280] mb-2 px-3">
          My Departments
        </h3>
        <div class="space-y-0.5">
          <div
            v-for="dept in authStore.departments"
            :key="dept.id"
            class="flex items-center justify-between gap-2 h-9 px-3 rounded-xl text-xs font-medium text-[#1e1e1e] hover:bg-[#fffbf4]"
          >
            <span class="flex items-center gap-2 min-w-0">
              <span class="w-1.5 h-1.5 rounded-full bg-[#640c0e] shrink-0" />
              <span class="truncate">{{ dept.name }}</span>
            </span>
            <span class="text-[10px] text-[#6b7280] uppercase tracking-wider font-semibold shrink-0">
              {{ dept.role }}
            </span>
          </div>
          <p
            v-if="!authStore.departments.length"
            class="px-3 py-2 text-[11px] text-[#6b7280]"
          >
            No departments assigned.
          </p>
        </div>
      </div>
    </div>

    <div class="pt-3 border-t border-[#ebe8de] text-[11px] text-[#6b7280] flex items-center justify-between gap-2 px-1">
      <span class="flex items-center gap-2 min-w-0">
        <img src="/logo.webp" alt="" class="w-4 h-4 object-contain opacity-80" />
        <span class="truncate">SFU MSA &copy; 2026</span>
      </span>
      <span class="font-medium text-[#640c0e] shrink-0">v1.0.0</span>
    </div>
  </aside>
</template>
