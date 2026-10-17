<template>
  <div class="overflow-x-auto scrollbar-none -mx-4 px-4 sm:mx-0 sm:px-0">
    <nav
      :aria-label="ariaLabel"
      class="inline-flex sm:flex items-center gap-1.5 p-1.5 rounded-2xl bg-white/90 backdrop-blur-xs border border-[#ebe8de] shadow-2xs min-w-max sm:min-w-0"
    >
      <router-link
        v-for="tab in tabs"
        :key="typeof tab.to === 'string' ? tab.to : tab.to.path || tab.label"
        :to="tab.to"
        class="group relative flex items-center gap-2 py-2 px-3.5 sm:px-4 text-xs sm:text-sm font-semibold transition-all duration-200 select-none cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#640c0e] rounded-xl"
        :class="isTabActive(tab)
          ? 'bg-[#640c0e] text-white shadow-xs font-bold ring-1 ring-[#640c0e]'
          : 'text-neutral-700 hover:text-[#640c0e] hover:bg-[#fffbf4] border border-transparent hover:border-[#ebe8de]/70'"
        :aria-current="isTabActive(tab) ? 'page' : undefined"
      >
        <!-- Icon -->
        <component
          :is="tab.icon"
          v-if="tab.icon"
          class="w-4 h-4 transition-colors shrink-0"
          :class="isTabActive(tab) ? 'text-[#ffdc83]' : 'text-neutral-400 group-hover:text-[#640c0e]'"
        />

        <!-- Label -->
        <span class="whitespace-nowrap">{{ tab.label }}</span>

        <!-- Badge -->
        <span
          v-if="tab.badge !== undefined && tab.badge !== null && tab.badge !== 0 && tab.badge !== '0'"
          class="ml-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold shrink-0 transition-colors"
          :class="isTabActive(tab) ? 'bg-[#ffdc83] text-[#640c0e]' : 'bg-rose-100 text-[#640c0e] group-hover:bg-rose-200'"
        >
          {{ typeof tab.badge === 'number' && tab.badge > 9 ? '9+' : tab.badge }}
        </span>
      </router-link>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { useRoute, type RouteLocationRaw } from 'vue-router';

export interface RouteTabItem {
  to: RouteLocationRaw;
  label: string;
  icon?: any;
  badge?: number | string | null;
  badgeVariant?: 'red' | 'gold' | 'green';
  exact?: boolean;
}

interface Props {
  tabs: RouteTabItem[];
  ariaLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
  ariaLabel: 'Section Navigation',
});

const route = useRoute();

function isTabActive(tab: RouteTabItem): boolean {
  const currentPath = route.path;
  const targetPath = typeof tab.to === 'string' ? tab.to : (tab.to as any).path || '';

  if (tab.exact) {
    return currentPath === targetPath;
  }

  if (currentPath === targetPath) {
    return true;
  }

  // Check prefix match for child routes, but yield if a sibling tab is a closer match
  const isPrefix = targetPath !== '/app' && targetPath !== '/app/dashboard' && currentPath.startsWith(targetPath + '/');
  if (!isPrefix) return false;

  const moreSpecificMatch = props.tabs.some((other) => {
    const otherPath = typeof other.to === 'string' ? other.to : (other.to as any).path || '';
    return (
      otherPath !== targetPath &&
      otherPath.startsWith(targetPath) &&
      (currentPath === otherPath || currentPath.startsWith(otherPath + '/'))
    );
  });

  return !moreSpecificMatch;
}
</script>
