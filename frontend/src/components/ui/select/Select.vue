<script setup lang="ts">
import { ref, computed } from 'vue';
import type { SelectProps, SelectOption } from './types';

const props = withDefaults(defineProps<SelectProps>(), {
  placeholder: 'Select an option',
  disabled: false,
  required: false,
  searchable: false
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number): void;
  (e: 'change', value: string | number): void;
}>();

const selectId = ref(`select-${Math.random().toString(36).substring(2, 9)}`);
const isFocused = ref(false);
const isOpen = ref(false);
const searchQuery = ref('');

const filteredOptions = computed(() => {
  if (!searchQuery.value) return props.options;
  return props.options.filter(option =>
    option.label.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});

const selectedLabel = computed(() => {
  const selected = props.options.find(opt => opt.value === props.modelValue);
  return selected ? selected.label : '';
});

const handleNativeChange = (e: Event) => {
  const target = e.target as HTMLSelectElement;
  emit('update:modelValue', target.value);
  emit('change', target.value);
};

const toggleDropdown = () => {
  if (props.disabled) return;
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    isFocused.value = true;
  }
};

const selectOption = (option: SelectOption) => {
  if (option.disabled) return;
  emit('update:modelValue', option.value);
  emit('change', option.value);
  isOpen.value = false;
  searchQuery.value = '';
  isFocused.value = false;
};

const closeDropdown = () => {
  setTimeout(() => {
    isOpen.value = false;
    isFocused.value = false;
  }, 200);
};
</script>

<template>
  <div class="mb-6 space-y-2 w-full relative">
    <label :for="selectId" class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted block mb-2">
      {{ label }} <span v-if="required" class="text-secondary">*</span>
    </label>
    
    <!-- Custom Searchable Select -->
    <div v-if="searchable" class="relative w-full">
      <div
        :id="selectId"
        tabindex="0"
        @click="toggleDropdown"
        @blur="closeDropdown"
        :class="[
          'w-full px-4 py-3 bg-white border rounded-xl outline-none transition-all duration-300 text-neutral-black text-sm flex items-center justify-between cursor-pointer select-none',
          error ? 'border-secondary focus:ring-2 focus:ring-secondary/15' : 'border-neutral-ivory focus:ring-2 focus:ring-primary/15 focus:border-primary',
          disabled && 'bg-neutral-gray/10 cursor-not-allowed opacity-50 pointer-events-none'
        ]"
      >
        <span v-if="selectedLabel" class="text-neutral-black">{{ selectedLabel }}</span>
        <span v-else class="text-neutral-muted">{{ placeholder }}</span>
        
        <!-- Chevron Icon -->
        <svg
          class="h-4 w-4 text-neutral-muted transition-transform duration-300"
          :class="{ 'transform rotate-180': isOpen }"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>

      <!-- Searchable Options Panel -->
      <div
        v-if="isOpen"
        class="absolute z-50 w-full mt-2 bg-white border border-neutral-ivory rounded-xl shadow-premium-md max-h-60 overflow-y-auto"
      >
        <div class="p-2 border-b border-neutral-ivory/50 sticky top-0 bg-white">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search..."
            class="w-full px-3 py-1.5 bg-neutral-background border border-neutral-ivory rounded-lg outline-none text-xs text-neutral-black focus:border-primary"
            @click.stop
          />
        </div>
        <ul class="py-1">
          <li
            v-for="option in filteredOptions"
            :key="option.value"
            @click.stop="selectOption(option)"
            :class="[
              'px-4 py-2 text-sm text-neutral-black cursor-pointer transition-colors',
              option.value === modelValue ? 'bg-neutral-ivory text-primary font-semibold' : 'hover:bg-neutral-background',
              option.disabled ? 'opacity-50 cursor-not-allowed hover:bg-transparent' : ''
            ]"
          >
            {{ option.label }}
          </li>
          <li v-if="filteredOptions.length === 0" class="px-4 py-3 text-xs text-neutral-muted text-center">
            No matches found
          </li>
        </ul>
      </div>
      
      <!-- Underline Slide-in Focus Indicator -->
      <div 
        :class="[
          'absolute inset-x-0 bottom-0 h-0.5 bg-accent-gold transition-transform duration-500 rounded-full pointer-events-none',
          isFocused ? 'scale-x-100' : 'scale-x-0'
        ]"
      />
    </div>

    <!-- Native Standard Select -->
    <div v-else class="relative w-full">
      <select
        :id="selectId"
        :value="modelValue"
        :disabled="disabled"
        @change="handleNativeChange"
        @focus="isFocused = true"
        @blur="isFocused = false"
        :class="[
          'w-full px-4 py-3 bg-white border rounded-xl outline-none transition-all duration-300 text-neutral-black text-sm appearance-none cursor-pointer',
          error 
            ? 'border-secondary focus:ring-2 focus:ring-secondary/15' 
            : 'border-neutral-ivory focus:ring-2 focus:ring-primary/15 focus:border-primary',
          disabled && 'bg-neutral-gray/10 cursor-not-allowed opacity-50'
        ]"
      >
        <option value="" disabled selected>{{ placeholder }}</option>
        <option
          v-for="option in options"
          :key="option.value"
          :value="option.value"
          :disabled="option.disabled"
        >
          {{ option.label }}
        </option>
      </select>

      <!-- Custom Chevron overlay for native select -->
      <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-neutral-muted">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
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
    <p v-if="error" class="text-[10.5px] text-secondary font-semibold block mt-1" aria-live="assertive">
      {{ error }}
    </p>
    <p v-else-if="description" class="text-[11px] text-neutral-muted leading-relaxed mt-1">
      {{ description }}
    </p>
  </div>
</template>
