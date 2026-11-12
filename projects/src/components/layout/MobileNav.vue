<script setup lang="ts">
import { useRoute } from 'vue-router';
import {
  LayoutDashboard,
  FolderKanban,
  CheckSquare,
  Calendar,
  Building2,
} from 'lucide-vue-next';

const route = useRoute();

const navItems = [
  { name: 'Dashboard', path: '/', icon: LayoutDashboard },
  { name: 'Projects', path: '/projects', icon: FolderKanban },
  { name: 'Tasks', path: '/my-tasks', icon: CheckSquare },
  { name: 'Calendar', path: '/calendar', icon: Calendar },
  { name: 'Depts', path: '/departments', icon: Building2 },
];

const isActive = (path: string) => {
  if (path === '/') return route.path === '/';
  return route.path.startsWith(path);
};
</script>

<template>
  <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-[#ebe8de] px-2 py-1 flex items-center justify-around shadow-lg">
    <router-link
      v-for="item in navItems"
      :key="item.path"
      :to="item.path"
      class="flex flex-col items-center justify-center py-1.5 px-3 rounded-xl transition-all"
      :class="[
        isActive(item.path)
          ? 'text-[#640c0e] font-bold'
          : 'text-[#6b7280] font-medium hover:text-[#1e1e1e]',
      ]"
    >
      <component
        :is="item.icon"
        class="w-5 h-5 mb-0.5 transition-colors"
        :class="isActive(item.path) ? 'text-[#640c0e]' : 'text-[#6b7280]'"
      />
      <span class="text-[10px] tracking-tight">{{ item.name }}</span>
    </router-link>
  </nav>
</template>
