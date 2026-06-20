<script setup lang="ts">
import { ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    title: string;
    defaultOpen?: boolean;
  }>(),
  { defaultOpen: false }
);

const open = ref(props.defaultOpen);
</script>

<template>
  <div class="rounded-2xl border border-neutral-ivory bg-white overflow-hidden shadow-soft">
    <button
      type="button"
      class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left cursor-pointer hover:bg-neutral-background/40 transition-colors"
      :aria-expanded="open"
      @click="open = !open"
    >
      <span class="text-sm font-bold text-primary">{{ title }}</span>
      <ChevronDown
        class="h-4 w-4 text-neutral-muted transition-transform duration-200"
        :class="open && 'rotate-180'"
      />
    </button>
    <div v-show="open" class="px-5 pb-5 border-t border-neutral-ivory/60">
      <slot />
    </div>
  </div>
</template>
