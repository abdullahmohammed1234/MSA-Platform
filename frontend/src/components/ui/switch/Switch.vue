<script setup lang="ts">
import { ref } from 'vue';
import { Motion } from '@motionone/vue';
import type { SwitchProps } from './types';
import { springs } from '@/design-system/animations/presets';

const props = withDefaults(defineProps<SwitchProps>(), {
  disabled: false
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
}>();

const switchId = ref(`switch-${Math.random().toString(36).substring(2, 9)}`);

const toggle = () => {
  if (props.disabled) return;
  emit('update:modelValue', !props.modelValue);
};
</script>

<template>
  <div class="flex items-center gap-3 select-none cursor-pointer" @click="toggle">
    <!-- Toggle Track -->
    <div
      :id="switchId"
      role="switch"
      :aria-checked="modelValue"
      :class="[
        'relative inline-flex items-center w-9 h-5 rounded-full transition-colors duration-300 outline-none',
        modelValue ? 'bg-primary' : 'bg-neutral-ivory',
        disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
      ]"
    >
      <!-- Toggle Thumb -->
      <Motion
        :animate="{ x: modelValue ? 16 : 0 }"
        :transition="springs.subtle"
        class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow-soft pointer-events-none"
      />
    </div>

    <!-- Toggle Labels -->
    <div v-if="label" class="flex flex-col">
      <span
        :class="[
          'text-sm font-medium text-neutral-black leading-tight',
          disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
        ]"
      >
        {{ label }}
      </span>
      <span v-if="description" class="text-[11px] text-neutral-muted leading-relaxed mt-0.5">
        {{ description }}
      </span>
    </div>
  </div>
</template>
