<script setup lang="ts">
import { computed } from 'vue';
import type { Question } from '@/types/academy';

const props = defineProps<{
  question: Question;
  modelValue: string[];
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string[]): void;
}>();

// Parse options helper (since options might be stored as array or JSON string)
const optionsList = computed((): string[] => {
  if (!props.question.options) return [];
  if (Array.isArray(props.question.options)) return props.question.options;
  try {
    return JSON.parse(props.question.options as any) as string[];
  } catch {
    return [];
  }
});

const handleRadioSelect = (opt: string) => {
  emit('update:modelValue', [opt]);
};

const handleCheckboxToggle = (opt: string) => {
  const current = [...props.modelValue];
  const idx = current.indexOf(opt);
  if (idx > -1) {
    current.splice(idx, 1);
  } else {
    current.push(opt);
  }
  emit('update:modelValue', current);
};

const handleTextChange = (e: Event) => {
  const target = e.target as HTMLInputElement;
  emit('update:modelValue', [target.value]);
};
</script>

<template>
  <div class="space-y-6">
    <!-- Question Title -->
    <div class="bg-neutral-background border border-neutral-ivory/60 p-5 rounded-2xl">
      <span class="text-xs font-mono font-bold uppercase tracking-wider text-neutral-muted block mb-1">
        Question {{ question.order }} ({{ question.points }} Points)
      </span>
      <h3 class="text-lg sm:text-xl font-display font-semibold text-primary leading-snug">
        {{ question.question }}
      </h3>
    </div>

    <!-- Render Options -->
    <div class="space-y-3">
      <!-- 1. Multiple Choice (Radio list) -->
      <template v-if="question.type === 'multiple_choice'">
        <div
          v-for="(opt, idx) in optionsList"
          :key="idx"
          @click="handleRadioSelect(opt)"
          :class="[
            'flex items-center gap-3 px-5 py-4 border rounded-2xl cursor-pointer transition-all duration-200 select-none',
            modelValue.includes(opt)
              ? 'border-primary bg-primary/5 text-primary font-medium'
              : 'border-neutral-ivory hover:border-neutral-gray bg-white text-neutral-black/85'
          ]"
        >
          <div
            :class="[
              'h-5 w-5 rounded-full border flex items-center justify-center flex-shrink-0',
              modelValue.includes(opt) ? 'border-primary' : 'border-neutral-gray'
            ]"
          >
            <div v-if="modelValue.includes(opt)" class="h-2.5 w-2.5 rounded-full bg-primary"></div>
          </div>
          <span class="text-sm sm:text-base">{{ opt }}</span>
        </div>
      </template>

      <!-- 2. True / False (Radio list) -->
      <template v-else-if="question.type === 'true_false'">
        <div
          v-for="opt in ['True', 'False']"
          :key="opt"
          @click="handleRadioSelect(opt)"
          :class="[
            'flex items-center gap-3 px-5 py-4 border rounded-2xl cursor-pointer transition-all duration-200 select-none',
            modelValue.includes(opt)
              ? 'border-primary bg-primary/5 text-primary font-medium'
              : 'border-neutral-ivory hover:border-neutral-gray bg-white text-neutral-black/85'
          ]"
        >
          <div
            :class="[
              'h-5 w-5 rounded-full border flex items-center justify-center flex-shrink-0',
              modelValue.includes(opt) ? 'border-primary' : 'border-neutral-gray'
            ]"
          >
            <div v-if="modelValue.includes(opt)" class="h-2.5 w-2.5 rounded-full bg-primary"></div>
          </div>
          <span class="text-sm sm:text-base">{{ opt }}</span>
        </div>
      </template>

      <!-- 3. Multiple Select (Checkboxes) -->
      <template v-else-if="question.type === 'multiple_select'">
        <div
          v-for="(opt, idx) in optionsList"
          :key="idx"
          @click="handleCheckboxToggle(opt)"
          :class="[
            'flex items-center gap-3 px-5 py-4 border rounded-2xl cursor-pointer transition-all duration-200 select-none',
            modelValue.includes(opt)
              ? 'border-primary bg-primary/5 text-primary font-medium'
              : 'border-neutral-ivory hover:border-neutral-gray bg-white text-neutral-black/85'
          ]"
        >
          <div
            :class="[
              'h-5 w-5 rounded-md border flex items-center justify-center flex-shrink-0',
              modelValue.includes(opt) ? 'border-primary bg-primary' : 'border-neutral-gray bg-white'
            ]"
          >
            <svg v-if="modelValue.includes(opt)" class="h-3.5 w-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <span class="text-sm sm:text-base">{{ opt }}</span>
        </div>
      </template>

      <!-- 4. Short Answer (Text input) -->
      <template v-else-if="question.type === 'short_answer'">
        <div class="space-y-2">
          <input
            type="text"
            :value="modelValue[0] || ''"
            @input="handleTextChange"
            placeholder="Type your answer here..."
            class="input-base text-sm sm:text-base"
          />
          <p class="text-[10px] text-neutral-muted italic px-2">
            Answers are graded automatically. Double-check your spelling!
          </p>
        </div>
      </template>
    </div>
  </div>
</template>
