<script setup lang="ts">
import { ref } from 'vue';

/**
 * A `datetime-local` field styled to match the design-system Input, which
 * only accepts text-like types.
 *
 * The bound value is whatever the input reports (`YYYY-MM-DDTHH:mm`); callers
 * convert to and from ISO 8601 with useEventFormatting.
 */
withDefaults(
  defineProps<{
    modelValue: string;
    label: string;
    description?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
    min?: string;
  }>(),
  { required: false, disabled: false }
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const fieldId = ref(`ems-datetime-${Math.random().toString(36).slice(2, 9)}`);

const onInput = (event: Event) => {
  emit('update:modelValue', (event.target as HTMLInputElement).value);
};
</script>

<template>
  <div class="mb-6 w-full space-y-2">
    <label
      :for="fieldId"
      class="mb-2 block text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted"
    >
      {{ label }} <span v-if="required" class="text-secondary">*</span>
    </label>

    <input
      :id="fieldId"
      type="datetime-local"
      :value="modelValue"
      :required="required"
      :disabled="disabled"
      :min="min"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${fieldId}-error` : undefined"
      class="w-full rounded-xl border bg-white px-4 py-3 text-sm text-neutral-black outline-none transition-all duration-300"
      :class="[
        error
          ? 'border-secondary focus:ring-2 focus:ring-secondary/15'
          : 'border-neutral-ivory focus:border-primary focus:ring-2 focus:ring-primary/15',
        disabled ? 'cursor-not-allowed bg-neutral-gray/10 opacity-50' : '',
      ]"
      @input="onInput"
    />

    <p
      v-if="error"
      :id="`${fieldId}-error`"
      class="mt-1 block text-[10.5px] font-semibold text-secondary"
      aria-live="assertive"
    >
      {{ error }}
    </p>
    <p v-else-if="description" class="mt-1 text-[11px] leading-relaxed text-neutral-muted">
      {{ description }}
    </p>
  </div>
</template>
