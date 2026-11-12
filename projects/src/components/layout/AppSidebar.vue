<script setup lang="ts">
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import {
  LayoutDashboard,
  FolderKanban,
  CheckSquare,
  Calendar,
  Building2,
  Users,
  PlusCircle,
} from 'lucide-vue-next';

defineEmits<{
  (e: 'open-create-modal'): void;
}>();

const route = useRoute();
const authStore = useAuthStore();

const navItems = [
  { name: 'Dashboard', path: '/', icon: LayoutDashboard },
  { name: 'Projects', path: '/projects', icon: FolderKanban },
  { name: 'My Tasks', path: '/my-tasks', icon: CheckSquare },
  { name: 'Calendar', path: '/calendar', icon: Calendar },
  { name: 'Resources', path: '/resources', icon: Users },
  { name: 'Departments', path: '/departments', icon: Building2 },
];

const isActive = (path: string) => {
  if (path === '/') return route.path === '/';
  return route.path.startsWith(path);
};
</script>

<template>
  <aside class="w-64 border-r border-[#ebe8de] bg-white h-[calc(100vh-4rem)] sticky top-16 flex flex-col justify-between p-4 hidden md:flex shrink-0">
    <div class="space-y-6">
      <!-- Create New Project Quick Action -->
      <button
        type="button"
        @click="$emit('open-create-modal')"
        class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl font-bold text-xs bg-gradient-to-r from-[#640c0e] to-[#b02e32] text-white shadow-xs hover:shadow-brand hover:opacity-95 transition-all"
      >
        <PlusCircle class="w-4 h-4" /> New Project
      </button>

      <!-- Main Navigation Menu -->
      <nav class="space-y-1">
        <router-link
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all group"
          :class="[
            isActive(item.path)
              ? 'bg-[#640c0e]/10 text-[#640c0e]'
              : 'text-[#6b7280] hover:bg-[#fbfbf9] hover:text-[#1e1e1e]',
          ]"
        >
          <component
            :is="item.icon"
            class="w-4 h-4 transition-colors"
            :class="isActive(item.path) ? 'text-[#640c0e]' : 'text-[#6b7280] group-hover:text-[#1e1e1e]'"
          />
          <span>{{ item.name }}</span>
        </router-link>
      </nav>

      <!-- Active Departments -->
      <div class="pt-4 border-t border-[#ebe8de]">
        <h3 class="text-[11px] font-bold uppercase tracking-wider text-[#6b7280] mb-2 px-3">
          My Departments
        </h3>
        <div class="space-y-1">
          <div
            v-for="dept in authStore.departments"
            :key="dept.id"
            class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium text-[#1e1e1e] hover:bg-[#fbfbf9]"
          >
            <span class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#640c0e]"></span>
              <span>{{ dept.name }}</span>
            </span>
            <span class="text-[10px] text-[#6b7280] uppercase tracking-wider font-semibold">
              {{ dept.role }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer info -->
    <div class="pt-4 border-t border-[#ebe8de] text-[11px] text-[#6b7280] flex items-center justify-between">
      <span>SFU MSA &copy; 2026</span>
      <span class="font-medium text-[#640c0e]">v1.0.0</span>
    </div>
  </aside>
</template>
