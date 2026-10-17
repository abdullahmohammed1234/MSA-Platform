<template>
  <div class="w-full space-y-1.5">
    <div class="flex items-center justify-between">
      <label
        v-if="label"
        :for="inputId"
        class="block text-xs font-semibold uppercase tracking-wider text-neutral-600"
      >
        {{ label }}
        <span v-if="required" class="text-red-500 font-bold" aria-hidden="true">*</span>
      </label>
      <slot name="label-extra"></slot>
    </div>

    <div class="relative rounded-xl">
      <div
        v-if="$slots['left-icon']"
        class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-neutral-400"
      >
        <slot name="left-icon"></slot>
      </div>

      <input
        :id="inputId"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :autocomplete="autocomplete"
        :aria-invalid="Boolean(error)"
        :aria-describedby="error ? `${inputId}-error` : description ? `${inputId}-desc` : undefined"
        :class="[
          'w-full py-2.5 bg-white border text-neutral-800 text-sm rounded-xl outline-none transition-all duration-200',
          $slots['left-icon'] ? 'pl-10' : 'pl-4',
          $slots['right-icon'] ? 'pr-10' : 'pr-4',
          error
            ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100 bg-red-50/20'
            : 'border-[#ebe8de] focus:border-[#640c0e] focus:ring-2 focus:ring-[#640c0e]/10',
          disabled && 'bg-neutral-100/70 text-neutral-400 cursor-not-allowed',
        ]"
        @input="handleInput"
        @blur="$emit('blur', $event)"
        @focus="$emit('focus', $event)"
      />

      <div
        v-if="$slots['right-icon']"
        class="absolute inset-y-0 right-0 pr-3.5 flex items-center"
      >
        <slot name="right-icon"></slot>
      </div>
    </div>

    <p
      v-if="error"
      :id="`${inputId}-error`"
      class="text-xs text-red-600 font-medium flex items-center gap-1 mt-1"
      role="alert"
    >
      <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path
          fill-rule="evenodd"
          d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
          clip-rule="evenodd"
        />
      </svg>
      <span>{{ error }}</span>
    </p>

    <p
      v-else-if="description"
      :id="`${inputId}-desc`"
      class="text-xs text-neutral-500 mt-1"
    >
      {{ description }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  modelValue: string | number;
  label?: string;
  type?: string;
  placeholder?: string;
  error?: string;
  description?: string;
  disabled?: boolean;
  required?: boolean;
  autocomplete?: string;
  id?: string;
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  placeholder: '',
  disabled: false,
  required: false,
  autocomplete: 'off',
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'blur', event: FocusEvent): void;
  (e: 'focus', event: FocusEvent): void;
}>();

const inputId = computed(() => {
  if (props.id) return props.id;
  return `input-${Math.random().toString(36).substring(2, 9)}`;
});

function handleInput(e: Event) {
  const target = e.target as HTMLInputElement;
  emit('update:modelValue', target.value);
}
</script>
