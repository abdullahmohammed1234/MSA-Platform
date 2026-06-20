<script setup lang="ts">
import { ref, onMounted, watch, nextTick } from 'vue';
import { Motion } from '@motionone/vue';
import type { TextareaProps } from './types';
import { inputFocus } from '@/design-system/animations/hover';

const props = withDefaults(defineProps<TextareaProps>(), {
  placeholder: '',
  disabled: false,
  required: false,
  rows: 4,
  autoResize: false
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'focus', event: FocusEvent): void;
  (e: 'blur', event: FocusEvent): void;
}>();

const textareaId = ref(`textarea-${Math.random().toString(36).substring(2, 9)}`);
const isFocused = ref(false);
const textareaRef = ref<HTMLTextAreaElement | null>(null);

const handleInput = (e: Event) => {
  const target = e.target as HTMLTextAreaElement;
  emit('update:modelValue', target.value);
  adjustHeight();
};

const handleFocus = (e: FocusEvent) => {
  isFocused.value = true;
  emit('focus', e);
};

const handleBlur = (e: FocusEvent) => {
  isFocused.value = false;
  emit('blur', e);
};

const adjustHeight = () => {
  if (!props.autoResize || !textareaRef.value) return;
  textareaRef.value.style.height = 'auto';
  textareaRef.value.style.height = `${textareaRef.value.scrollHeight}px`;
};

onMounted(() => {
  adjustHeight();
});

watch(() => props.modelValue, () => {
  nextTick(() => adjustHeight());
});
</script>

<template>
  <div class="mb-6 space-y-2 w-full">
    <label :for="textareaId" class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted block mb-2">
      {{ label }} <span v-if="required" class="text-secondary">*</span>
    </label>
    
    <div class="relative group w-full">
      <!-- Animated Textarea Wrapper -->
      <Motion
        :animate="isFocused ? inputFocus.focus : { scale: 1 }"
        :transition="inputFocus.transition"
        class="w-full block"
      >
        <textarea
          ref="textareaRef"
          :id="textareaId"
          :value="modelValue"
          :placeholder="placeholder"
          :disabled="disabled"
          :rows="rows"
          @input="handleInput"
          @focus="handleFocus"
          @blur="handleBlur"
          :class="[
            'w-full px-4 py-3 bg-white border rounded-xl outline-none transition-all duration-300 text-neutral-black text-sm resize-y',
            props.autoResize ? 'resize-none overflow-hidden' : '',
            error 
              ? 'border-secondary focus:ring-2 focus:ring-secondary/15' 
              : 'border-neutral-ivory focus:ring-2 focus:ring-primary/15 focus:border-primary',
            disabled && 'bg-neutral-gray/10 cursor-not-allowed opacity-50'
          ]"
        />
      </Motion>

      <!-- Underline Slide-in Focus Indicator -->
      <div 
        :class="[
          'absolute inset-x-0 bottom-0 h-0.5 bg-accent-gold transition-transform duration-500 rounded-full pointer-events-none',
          isFocused ? 'scale-x-100' : 'scale-x-0'
        ]"
      />
    </div>

    <!-- Error or Help Text -->
    <p v-if="error" class="text-[10.5px] text-secondary font-semibold block mt-1" aria-live="assertive">
      {{ error }}
    </p>
    <p v-else-if="description" class="text-[11px] text-neutral-muted leading-relaxed mt-1">
      {{ description }}
    </p>
  </div>
</template>
