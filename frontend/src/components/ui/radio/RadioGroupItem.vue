<script setup lang="ts">
import { inject, computed, ref } from 'vue';
import type { RadioProps } from './types';

const props = withDefaults(defineProps<RadioProps>(), {
  disabled: false
});

const radioId = ref(`radio-${Math.random().toString(36).substring(2, 9)}`);
const radioGroup = inject<any>('radioGroup', null);

const isChecked = computed(() => {
  if (radioGroup) {
    return radioGroup.modelValue.value === props.value;
  }
  return props.modelValue === props.value;
});

const nameValue = computed(() => {
  return radioGroup ? radioGroup.name.value : props.name;
});

const disabledState = computed(() => {
  return (radioGroup ? radioGroup.disabled.value : false) || props.disabled;
});

const handleChange = () => {
  if (disabledState.value) return;
  if (radioGroup) {
    radioGroup.updateValue(props.value);
  }
};
</script>

<template>
  <div class="flex items-start gap-3 select-none cursor-pointer" @click="handleChange">
    <div class="relative flex items-center h-5 mt-0.5">
      <input
        type="radio"
        :id="radioId"
        :name="nameValue"
        :checked="isChecked"
        :disabled="disabledState"
        @change="handleChange"
        class="h-5 w-5 rounded-full border border-neutral-ivory text-primary focus:ring-primary/20 bg-white transition-all cursor-pointer accent-primary appearance-none flex items-center justify-center"
        :class="[disabledState ? 'cursor-not-allowed opacity-50 bg-neutral-gray/10' : '']"
      />
      <!-- Circular dot when selected -->
      <div
        v-if="isChecked"
        class="absolute pointer-events-none h-2.5 w-2.5 rounded-full bg-primary left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 transition-all"
        :class="[disabledState ? 'bg-primary/50' : '']"
      />
    </div>

    <div class="flex flex-col">
      <label
        :for="radioId"
        :class="[
          'text-sm font-medium text-neutral-black cursor-pointer leading-tight',
          disabledState ? 'cursor-not-allowed opacity-50' : ''
        ]"
        @click.prevent
      >
        {{ label }}
      </label>
      <p v-if="description" class="text-[11px] text-neutral-muted leading-relaxed mt-0.5">
        {{ description }}
      </p>
    </div>
  </div>
</template>
