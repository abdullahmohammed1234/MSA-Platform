<script setup lang="ts">
import { ChevronRight, Home } from 'lucide-vue-next';

export interface BreadcrumbItem {
  id: string;
  label: string;
  to?: string;
}

defineProps<{
  items: BreadcrumbItem[];
  homeLabel?: string;
}>();

const emit = defineEmits<{
  home: [];
  navigate: [item: BreadcrumbItem];
}>();
</script>

<template>
  <nav class="hidden md:flex items-center gap-2.5 text-sm select-none">
    <button
      type="button"
      class="flex items-center gap-2 group cursor-pointer"
      @click="emit('home')"
    >
      <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-background border border-neutral-ivory/60 group-hover:bg-white group-hover:text-primary transition-all shadow-sm">
        <Home class="h-3.5 w-3.5" />
      </div>
      <span class="text-neutral-muted/60 font-medium group-hover:text-neutral-muted transition-colors">
        {{ homeLabel || 'Academy HQ' }}
      </span>
    </button>

    <template v-for="(item, idx) in items" :key="item.id">
      <ChevronRight class="h-3 w-3 text-neutral-muted/20 stroke-[3px]" />
      <button
        type="button"
        class="font-bold text-xs tracking-tight capitalize px-3 py-1 rounded-lg border shadow-sm transition-opacity"
        :class="idx === items.length - 1
          ? 'bg-primary/5 border-primary/10 text-primary cursor-default'
          : 'bg-white border-neutral-ivory text-neutral-muted hover:opacity-80 cursor-pointer'"
        :disabled="idx === items.length - 1"
        @click="idx < items.length - 1 && emit('navigate', item)"
      >
        {{ item.label }}
      </button>
    </template>
  </nav>
</template>
