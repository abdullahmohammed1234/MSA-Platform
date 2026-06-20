<script setup lang="ts">
import { provide, toRef } from 'vue';
import type { RadioGroupProps } from './types';

const props = withDefaults(defineProps<RadioGroupProps>(), {
  disabled: false,
  required: false,
  inline: false
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number | boolean): void;
  (e: 'change', value: string | number | boolean): void;
}>();

const updateValue = (value: string | number | boolean) => {
  if (props.disabled) return;
  emit('update:modelValue', value);
  emit('change', value);
};

// Provide properties to children items
provide('radioGroup', {
  modelValue: toRef(props, 'modelValue'),
  name: toRef(props, 'name'),
  disabled: toRef(props, 'disabled'),
  updateValue
});
</script>

<template>
  <div class="mb-6 w-full">
    <!-- Group Label -->
    <span v-if="label" class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted block mb-3">
      {{ label }} <span v-if="required" class="text-secondary">*</span>
    </span>

    <!-- Radio Options container -->
    <div :class="[inline ? 'flex flex-row flex-wrap gap-6' : 'flex flex-col gap-3']">
      <slot></slot>
    </div>

    <!-- Error or Description -->
    <p v-if="error" class="text-[10.5px] text-secondary font-semibold block mt-2" aria-live="assertive">
      {{ error }}
    </p>
    <p v-else-if="description" class="text-[11px] text-neutral-muted leading-relaxed mt-2">
      {{ description }}
    </p>
  </div>
</template>
