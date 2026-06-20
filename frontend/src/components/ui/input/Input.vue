<script setup lang="ts">
import { ref, computed } from 'vue';
import { Motion } from '@motionone/vue';
import type { InputProps } from './types';
import { inputFocus } from '@/design-system/animations/hover';

const props = withDefaults(defineProps<InputProps>(), {
  type: 'text',
  placeholder: '',
  disabled: false,
  required: false
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'focus', event: FocusEvent): void;
  (e: 'blur', event: FocusEvent): void;
}>();

const inputId = ref(`input-${Math.random().toString(36).substring(2, 9)}`);
const isFocused = ref(false);

const handleInput = (e: Event) => {
  const target = e.target as HTMLInputElement;
  emit('update:modelValue', target.value);
};

const handleFocus = (e: FocusEvent) => {
  isFocused.value = true;
  emit('focus', e);
};

const handleBlur = (e: FocusEvent) => {
  isFocused.value = false;
  emit('blur', e);
};

// Check if slots exist
const hasPrefix = computed(() => !!useSlots().prefix);
const hasSuffix = computed(() => !!useSlots().suffix);

import { useSlots } from 'vue';
</script>

<template>
  <div class="mb-6 space-y-2 w-full">
    <label v-if="label" :for="inputId" class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted block mb-2">
      {{ label }} <span v-if="required" class="text-secondary">*</span>
    </label>
    
    <div class="relative group w-full">
      <!-- Prefix Icon Container -->
      <div v-if="hasPrefix" class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-neutral-muted">
        <slot name="prefix"></slot>
      </div>

      <!-- Animated Input Wrapper -->
      <Motion
        :animate="isFocused ? inputFocus.focus : { scale: 1 }"
        :transition="inputFocus.transition"
        class="w-full block"
      >
        <input
          :id="inputId"
          :type="type"
          :value="modelValue"
          :placeholder="placeholder"
          :disabled="disabled"
          @input="handleInput"
          @focus="handleFocus"
          @blur="handleBlur"
          :aria-invalid="error ? 'true' : undefined"
          :aria-describedby="error ? `${inputId}-error` : undefined"
          :class="[
            'w-full px-4 py-3 bg-white border rounded-xl outline-none transition-all duration-300 text-neutral-black text-sm',
            hasPrefix ? 'pl-10' : '',
            hasSuffix ? 'pr-10' : '',
            error 
              ? 'border-secondary focus:ring-2 focus:ring-secondary/15' 
              : 'border-neutral-ivory focus:ring-2 focus:ring-primary/15 focus:border-primary',
            disabled && 'bg-neutral-gray/10 cursor-not-allowed opacity-50'
          ]"
        />
      </Motion>

      <!-- Suffix Icon Container -->
      <div v-if="hasSuffix" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-neutral-muted">
        <div class="pointer-events-auto flex items-center">
          <slot name="suffix"></slot>
        </div>
      </div>

      <!-- Underline Slide-in Focus Indicator -->
      <div 
        :class="[
          'absolute inset-x-0 bottom-0 h-0.5 bg-accent-gold transition-transform duration-500 rounded-full pointer-events-none',
          isFocused ? 'scale-x-100' : 'scale-x-0'
        ]"
      />
    </div>

    <!-- Error or Help Text -->
    <p v-if="error" :id="`${inputId}-error`" class="text-[10.5px] text-secondary font-semibold block mt-1" aria-live="assertive">
      {{ error }}
    </p>
    <p v-else-if="description" class="text-[11px] text-neutral-muted leading-relaxed mt-1">
      {{ description }}
    </p>
  </div>
</template>
