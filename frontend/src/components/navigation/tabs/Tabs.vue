<script setup lang="ts">
import { computed, provide, ref, watch } from 'vue';

export interface TabOption {
  id: string;
  label: string;
  disabled?: boolean;
  badge?: string | number;
}

const props = withDefaults(
  defineProps<{
    modelValue?: string;
    options?: TabOption[];
    variant?: 'underline' | 'pill' | 'simple';
    defaultValue?: string;
  }>(),
  {
    variant: 'underline',
    defaultValue: '',
  }
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
  change: [value: string];
}>();

const internalActive = ref(props.modelValue || props.defaultValue || props.options?.[0]?.id || '');

watch(
  () => props.modelValue,
  (value) => {
    if (value !== undefined) internalActive.value = value;
  }
);

const activeId = computed({
  get: () => props.modelValue ?? internalActive.value,
  set: (value: string) => {
    internalActive.value = value;
    emit('update:modelValue', value);
    emit('change', value);
  },
});

const setActiveId = (id: string) => {
  activeId.value = id;
};

provide('tabsContext', {
  activeId,
  setActiveId,
  variant: computed(() => props.variant),
});
</script>

<template>
  <div class="w-full text-left space-y-4">
    <div
      v-if="options?.length"
      role="tablist"
      class="flex gap-1 border-b border-neutral-ivory items-end overflow-x-auto no-scrollbar"
      :class="variant === 'pill' && 'border-none bg-neutral-background/40 p-1 rounded-xl'"
    >
      <button
        v-for="opt in options"
        :key="opt.id"
        type="button"
        role="tab"
        :aria-selected="activeId === opt.id"
        :disabled="opt.disabled"
        class="relative text-xs font-bold tracking-wider uppercase py-2.5 px-4 transition-all capitalize whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
        :class="[
          variant === 'underline' && (activeId === opt.id
            ? 'text-primary font-black border-b-2 border-primary -mb-px'
            : 'text-neutral-muted hover:text-primary/80'),
          variant === 'pill' && (activeId === opt.id
            ? 'bg-primary text-white rounded-lg'
            : 'text-neutral-muted hover:text-primary rounded-lg'),
          variant === 'simple' && (activeId === opt.id
            ? 'text-primary font-semibold opacity-100'
            : 'text-neutral-muted opacity-60 hover:opacity-100'),
          opt.disabled && 'opacity-40 cursor-not-allowed',
        ]"
        @click="!opt.disabled && setActiveId(opt.id)"
      >
        {{ opt.label }}
        <span
          v-if="opt.badge !== undefined"
          class="ml-1.5 text-[9px] font-mono px-1.5 py-0.5 rounded-md bg-primary/10 text-primary"
        >
          {{ opt.badge }}
        </span>
      </button>
    </div>
    <slot />
  </div>
</template>
