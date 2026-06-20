<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import type { CheckboxProps } from './types';

const props = withDefaults(defineProps<CheckboxProps>(), {
  disabled: false,
  required: false,
  indeterminate: false
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean | any[]): void;
}>();

const checkboxId = ref(`checkbox-${Math.random().toString(36).substring(2, 9)}`);
const inputRef = ref<HTMLInputElement | null>(null);

const isChecked = computed(() => {
  if (Array.isArray(props.modelValue)) {
    return props.modelValue.includes(props.value);
  }
  return props.modelValue;
});

const handleChange = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (props.disabled) return;
  
  if (Array.isArray(props.modelValue)) {
    const newValue = [...props.modelValue];
    if (target.checked) {
      newValue.push(props.value);
    } else {
      const index = newValue.indexOf(props.value);
      if (index > -1) {
        newValue.splice(index, 1);
      }
    }
    emit('update:modelValue', newValue);
  } else {
    emit('update:modelValue', target.checked);
  }
};

const setIndeterminate = () => {
  if (inputRef.value) {
    inputRef.value.indeterminate = props.indeterminate;
  }
};

watch(() => props.indeterminate, () => {
  setIndeterminate();
});

onMounted(() => {
  setIndeterminate();
});
</script>

<template>
  <div class="flex items-start gap-3 select-none">
    <div class="relative flex items-center h-5 mt-0.5">
      <input
        ref="inputRef"
        type="checkbox"
        :id="checkboxId"
        :checked="isChecked"
        :disabled="disabled"
        @change="handleChange"
        :class="[
          'h-5 w-5 rounded border border-neutral-ivory text-primary focus:ring-primary/20 bg-white transition-all cursor-pointer accent-primary appearance-none flex items-center justify-center',
          error ? 'border-secondary' : '',
          disabled ? 'cursor-not-allowed opacity-50 bg-neutral-gray/10' : ''
        ]"
      />
      <!-- Checkmark SVG when checked (since appearance-none hides browser defaults) -->
      <svg
        v-if="isChecked && !indeterminate"
        class="absolute pointer-events-none h-3 w-3 text-primary left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7" />
      </svg>
      <!-- Dash SVG when indeterminate -->
      <svg
        v-if="indeterminate"
        class="absolute pointer-events-none h-3.5 w-3.5 text-primary left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M20 12H4" />
      </svg>
    </div>

    <div class="flex flex-col">
      <label
        :for="checkboxId"
        :class="[
          'text-sm font-medium text-neutral-black cursor-pointer leading-tight',
          disabled ? 'cursor-not-allowed opacity-50' : ''
        ]"
      >
        <slot>{{ label }}</slot> <span v-if="required" class="text-secondary">*</span>
      </label>
      
      <p v-if="error" class="text-[10.5px] text-secondary font-semibold mt-0.5">
        {{ error }}
      </p>
      <p v-else-if="description" class="text-[11px] text-neutral-muted leading-relaxed mt-0.5">
        {{ description }}
      </p>
    </div>
  </div>
</template>
