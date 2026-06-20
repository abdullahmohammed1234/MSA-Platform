<script setup lang="ts">
import { inject, computed, type ComputedRef } from 'vue';

const props = defineProps<{
  value: string;
}>();

const context = inject<{
  activeId: ComputedRef<string> | { value: string };
  variant: ComputedRef<string>;
}>('tabsContext');

const isActive = computed(() => {
  const active = 'value' in context!.activeId ? context!.activeId.value : context!.activeId;
  return active === props.value;
});
</script>

<template>
  <div
    v-if="isActive"
    role="tabpanel"
    :id="`panel-${value}`"
    :aria-labelledby="`tab-${value}`"
    class="w-full pt-1 focus:outline-none animate-fade-in"
  >
    <slot />
  </div>
</template>
