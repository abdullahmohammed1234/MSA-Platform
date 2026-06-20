<script setup lang="ts">
import { ref, watch } from 'vue';
import type { SidebarProps } from './types';

const props = withDefaults(defineProps<SidebarProps>(), {
  collapsed: false,
  title: 'Academy',
  logoAlt: 'Dawah Academy logo',
});

const emit = defineEmits<{
  (e: 'collapse', collapsed: boolean): void;
}>();

const isCollapsed = ref(props.collapsed);
const expandedGroups = ref<Record<string, boolean>>({});

watch(() => props.collapsed, (val) => {
  isCollapsed.value = val;
});

const toggleCollapse = () => {
  isCollapsed.value = !isCollapsed.value;
  emit('collapse', isCollapsed.value);
};

const toggleGroup = (label: string) => {
  expandedGroups.value[label] = !expandedGroups.value[label];
};

const isGroupExpanded = (label: string) => {
  return expandedGroups.value[label] ?? true;
};
</script>

<template>
  <aside
    :class="[
      'h-screen bg-white border-r border-neutral-ivory flex flex-col justify-between transition-all duration-300 sticky top-0 z-30',
      isCollapsed ? 'w-20' : 'w-64'
    ]"
  >
    <div class="flex flex-col flex-1 overflow-y-auto">
      <!-- Sidebar Header -->
      <div
        :class="[
          'border-b border-neutral-ivory/50 shrink-0',
          isCollapsed
            ? 'flex flex-col items-center gap-2 py-3 px-2'
            : 'flex items-center gap-3 h-16 px-4'
        ]"
      >
        <div
          v-if="logoSrc"
          :class="[
            'flex items-center min-w-0',
            isCollapsed ? 'justify-center' : 'gap-3 flex-1'
          ]"
        >
          <div
            :class="[
              'shrink-0 flex items-center justify-center',
              isCollapsed ? 'h-9 w-9' : 'h-10 w-10'
            ]"
          >
            <img
              :src="logoSrc"
              :alt="logoAlt"
              class="h-full w-full object-contain"
            />
          </div>
          <div v-if="!isCollapsed" class="flex flex-col min-w-0">
            <span class="font-display font-bold text-primary text-sm tracking-wide truncate">
              {{ title }}
            </span>
            <span
              v-if="subtitle"
              class="text-[10px] uppercase tracking-[0.15em] text-neutral-muted font-bold truncate"
            >
              {{ subtitle }}
            </span>
          </div>
        </div>
        <span
          v-else-if="!isCollapsed"
          class="font-display font-bold text-primary text-base tracking-wide truncate flex-1"
        >
          {{ title }}
        </span>

        <button
          @click="toggleCollapse"
          :class="[
            'rounded-lg hover:bg-neutral-background text-neutral-muted hover:text-primary transition-colors cursor-pointer shrink-0',
            isCollapsed ? 'p-2 w-full flex justify-center' : 'p-1.5'
          ]"
          :aria-label="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              v-if="!isCollapsed"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M11 19l-7-7 7-7m8 14l-7-7 7-7"
            />
            <path
              v-else
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M13 5l7 7-7 7M5 5l7 7-7 7"
            />
          </svg>
        </button>
      </div>

      <!-- Navigation List -->
      <nav class="flex-1 px-3 py-4 space-y-1">
        <div v-for="item in items" :key="item.label" class="space-y-1">
          <!-- Item with children (Group Header) -->
          <div v-if="item.children && item.children.length > 0">
            <button
              v-if="!isCollapsed"
              @click="toggleGroup(item.label)"
              class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold uppercase tracking-widest text-neutral-muted hover:text-primary transition-colors cursor-pointer"
            >
              <span>{{ item.label }}</span>
              <svg
                class="h-3.5 w-3.5 transition-transform duration-200"
                :class="{ 'transform rotate-180': isGroupExpanded(item.label) }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="isGroupExpanded(item.label) || isCollapsed" class="space-y-1">
              <router-link
                v-for="subItem in item.children"
                :key="subItem.path"
                :to="subItem.path"
                class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-neutral-muted hover:text-primary hover:bg-neutral-background transition-all"
                active-class="text-primary font-semibold bg-neutral-ivory/50 border-l-4 border-primary"
              >
                <!-- Render Icon -->
                <div class="flex-shrink-0">
                  <slot :name="subItem.icon || 'default-icon'">
                    <!-- Default SVG dot/circle if none supplied -->
                    <span class="h-2 w-2 rounded-full bg-current block mx-1.5"></span>
                  </slot>
                </div>
                <span v-if="!isCollapsed" class="truncate">{{ subItem.label }}</span>
              </router-link>
            </div>
          </div>

          <!-- Direct Item -->
          <router-link
            v-else
            :to="item.path"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-neutral-muted hover:text-primary hover:bg-neutral-background transition-all"
            active-class="text-primary font-semibold bg-neutral-ivory/50 border-l-4 border-primary"
          >
            <div class="flex-shrink-0">
              <slot :name="item.icon || 'default-icon'">
                <!-- Default block/circle dot -->
                <svg class="h-5 w-5 text-current" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
              </slot>
            </div>
            <span v-if="!isCollapsed" class="truncate">{{ item.label }}</span>
          </router-link>
        </div>
      </nav>
    </div>

    <!-- Sidebar Footer (user info or action toggle) -->
    <div v-if="!isCollapsed" class="p-4 border-t border-neutral-ivory/50">
      <div class="flex items-center gap-3">
        <slot name="footer-user">
          <div class="h-9 w-9 rounded-full bg-neutral-background flex items-center justify-center font-bold text-primary">
            S
          </div>
          <div class="flex flex-col min-w-0">
            <span class="text-sm font-semibold text-neutral-black truncate">Scholar</span>
            <span class="text-xs text-neutral-muted truncate">Dawah Academy</span>
          </div>
        </slot>
      </div>
    </div>
  </aside>
</template>
