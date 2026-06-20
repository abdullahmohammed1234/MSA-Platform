# Vue Migration Blueprint — SFU MSA & Dawah Academy

This document outlines the engineering specifications and blueprint configurations to recreate the audited React design system using **Vue 3, TypeScript, Tailwind CSS, Motion for Vue, and Pinia** with pixel-perfect accuracy.

---

## 1. Tailwind CSS Configuration

To support developers utilizing different Tailwind versions, we specify both the Tailwind v3 configuration and the Tailwind v4 custom property block.

### Option A: Tailwind v4 Theme Configuration (`src/index.css`)
```css
@import "tailwindcss";

@theme {
  /* Font Family Mappings */
  --font-sans: "Geist Variable", "Inter", system-ui, -apple-system, sans-serif;
  --font-display: "Playfair Display", Georgia, "Times New Roman", serif;
  --font-serif: "Cormorant Garamond", Georgia, serif;
  --font-mono: "JetBrains Mono", "SFMono-Regular", Consolas, monospace;

  /* Color Palette Mappings */
  --color-primary: #640c0e;
  --color-secondary: #b02e32;
  --color-accent-gold: #ffdc83;
  --color-accent-red: #ea2128;
  
  --color-neutral-background: #fffbf4;
  --color-neutral-ivory: #ebe8de;
  --color-neutral-gray: #c2c4c7;
  --color-neutral-black: #1e1e1e;
  --color-neutral-white: #ffffff;

  /* Radius System */
  --radius-xs: 0.25rem;
  --radius-sm: 0.5rem;
  --radius-md: 0.75rem;
  --radius-lg: 1.0rem;
  --radius-xl: 1.5rem;
  --radius-2xl: 2.0rem;
  --radius-3xl: 3.0rem;

  /* Shadows System */
  --shadow-premium: 0 10px 40px -10px rgba(100, 12, 14, 0.3);
  --shadow-premium-md: 0 20px 50px -12px rgba(0, 0, 0, 0.08);
  --shadow-brand: 0 10px 30px -10px rgba(138, 21, 56, 0.15);
  --shadow-glow: 0 0 25px rgba(176, 46, 50, 0.25);
  --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.08);
}
```

### Option B: Tailwind v3 Configuration (`tailwind.config.js`)
```javascript
module.exports = {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        primary: '#640c0e',
        secondary: '#b02e32',
        accent: {
          gold: '#ffdc83',
          red: '#ea2128',
        },
        neutral: {
          background: '#fffbf4',
          ivory: '#ebe8de',
          gray: '#c2c4c7',
          black: '#1e1e1e',
          white: '#ffffff',
        }
      },
      fontFamily: {
        sans: ['Geist Variable', 'Inter', 'sans-serif'],
        display: ['Playfair Display', 'Georgia', 'serif'],
        serif: ['Cormorant Garamond', 'Georgia', 'serif'],
        mono: ['JetBrains Mono', 'SFMono-Regular', 'monospace'],
      },
      borderRadius: {
        'xs': '0.25rem',
        'sm': '0.5rem',
        'md': '0.75rem',
        'lg': '1.0rem',
        'xl': '1.5rem',
        '2xl': '2.0rem',
        '3xl': '3.0rem',
      },
      boxShadow: {
        'premium': '0 10px 40px -10px rgba(100, 12, 14, 0.3)',
        'premium-md': '0 20px 50px -12px rgba(0, 0, 0, 0.08)',
        'brand': '0 10px 30px -10px rgba(138, 21, 56, 0.15)',
        'glow': '0 0 25px rgba(176, 46, 50, 0.25)',
        'soft': '0 4px 20px rgba(0, 0, 0, 0.08)',
      }
    },
  },
  plugins: [],
}
```

---

## 2. Vue 3 Core Component Specifications

These components demonstrate how to combine Vue 3's `<script setup lang="ts">` syntax with Tailwind CSS classes and Motion for Vue animation features.

### Button Component (`Button.vue`)
```vue
<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'destructive' | 'gold' | 'success';
  size?: 'sm' | 'md' | 'lg' | 'icon';
  isLoading?: boolean;
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  isLoading: false,
  disabled: false
});

const emit = defineEmits<{
  (e: 'click', event: MouseEvent): void
}>();

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#640c0e] focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 select-none cursor-pointer duration-300';
  
  const variants = {
    primary: 'bg-primary text-white shadow-sm hover:bg-primary/95',
    secondary: 'bg-neutral-ivory text-[#640c0e] hover:bg-neutral-ivory/80',
    outline: 'border border-neutral-ivory bg-white text-[#640c0e] hover:bg-neutral-background/60 hover:text-primary',
    ghost: 'hover:bg-neutral-background hover:text-[#640c0e] text-[#640c0e]',
    destructive: 'bg-secondary text-white hover:bg-secondary/95',
    gold: 'bg-accent-gold text-primary hover:bg-accent-gold/90 font-bold border border-accent-gold/30',
    success: 'bg-emerald-800 text-white hover:bg-emerald-800/90'
  };

  const sizes = {
    sm: 'h-8 px-3 text-xs rounded-md',
    md: 'h-9 px-4 py-2 text-sm rounded-md',
    lg: 'h-11 rounded-lg px-8 text-base',
    icon: 'h-9 w-9 p-0'
  };

  return `${base} ${variants[props.variant]} ${sizes[props.size]}`;
});

const handleClick = (e: MouseEvent) => {
  if (!props.isLoading && !props.disabled) {
    emit('click', e);
  }
};
</script>

<template>
  <button
    :class="buttonClasses"
    :disabled="disabled || isLoading"
    @click="handleClick"
    :aria-busy="isLoading"
    aria-live="polite"
  >
    <span v-if="isLoading" class="mr-1.5 inline-flex items-center">
      <!-- Standard spin loader -->
      <svg class="animate-spin h-4 w-4 text-current" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </span>
    <slot name="left-icon" v-if="!isLoading"></slot>
    <slot></slot>
    <slot name="right-icon" v-if="!isLoading"></slot>
  </button>
</template>
```

### TextInput & FormGroup (`Input.vue`)
```vue
<script setup lang="ts">
import { ref } from 'vue';

interface Props {
  modelValue: string;
  label: string;
  description?: string;
  error?: string;
  type?: string;
  placeholder?: string;
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  placeholder: '',
  disabled: false
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>();

const inputId = ref(`input-${Math.random().toString(36).substr(2, 9)}`);
const isFocused = ref(false);

const handleInput = (e: Event) => {
  const target = e.target as HTMLInputElement;
  emit('update:modelValue', target.value);
};
</script>

<template>
  <div class="mb-6 space-y-2">
    <label :for="inputId" class="text-[10px] font-bold uppercase tracking-[0.15em] text-muted-foreground/60 block mb-2">
      {{ label }}
    </label>
    
    <div class="relative group w-full">
      <input
        :id="inputId"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        @input="handleInput"
        @focus="isFocused = true"
        @blur="isFocused = false"
        :class="[
          'w-full px-4 py-3 bg-white border rounded-xl outline-none transition-all duration-300 text-[#1e1e1e]',
          error 
            ? 'border-red-250 focus:ring-2 focus:ring-red-200' 
            : 'border-neutral-ivory focus:ring-2 focus:ring-primary/20 focus:border-primary',
          disabled && 'bg-neutral-100/50 cursor-not-allowed opacity-50'
        ]"
      />
      <!-- Underline Slide-in Focus Indicator -->
      <div 
        :class="[
          'absolute inset-x-0 bottom-0 h-0.5 bg-accent-gold transition-transform duration-500 rounded-full',
          isFocused ? 'scale-x-100' : 'scale-x-0'
        ]"
      />
    </div>

    <p v-if="error" class="text-[10.5px] text-red-600 font-semibold block mt-1">
      {{ error }}
    </p>
    <p v-else-if="description" class="text-[11px] text-muted-foreground leading-relaxed mt-1">
      {{ description }}
    </p>
  </div>
</template>
```

---

## 3. Motion for Vue Integration

To animate routing page changes, we structure Vue Router's `<router-view>` transitions.

### Route Transitions Setup (`App.vue`)
```vue
<script setup lang="ts">
import { Motion, Presence } from 'motion/vue';
</script>

<template>
  <router-view v-slot="{ Component, route }">
    <Presence mode="wait">
      <Motion
        :key="route.path"
        :initial="{ opacity: 0, y: 10 }"
        :animate="{ opacity: 1, y: 0 }"
        :exit="{ opacity: 0, y: -6 }"
        :transition="{ duration: 0.38, easing: [0.16, 1, 0.3, 1] }"
        class="w-full h-full"
      >
        <component :is="Component" />
      </Motion>
    </Presence>
  </router-view>
</template>
```

---

## 4. Pinia State Management Stores

Pinia stores manage the client state variables and transitions for course progression and interactive quiz runners.

### A. The Curriculum Progress Store (`stores/progress.ts`)
```typescript
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useProgressStore = defineStore('progress', () => {
  const completedLessons = ref<string[]>([]);
  const completedModules = ref<string[]>([]);
  const courseProgress = ref<Record<string, number>>({});

  const toggleLesson = (lessonId: string, courseId: string, totalLessons: number) => {
    const index = completedLessons.value.indexOf(lessonId);
    if (index > -1) {
      completedLessons.value.splice(index, 1);
    } else {
      completedLessons.value.push(lessonId);
    }
    
    // Recalculate course completion percentage
    const courseFinishedLessons = completedLessons.value.filter(id => id.startsWith(courseId));
    courseProgress.value[courseId] = Math.round((courseFinishedLessons.length / totalLessons) * 100);
  };

  const isLessonCompleted = computed(() => (lessonId: string) => {
    return completedLessons.value.includes(lessonId);
  });

  return {
    completedLessons,
    completedModules,
    courseProgress,
    toggleLesson,
    isLessonCompleted
  };
});
```

### B. Stateful Quiz Runner Store (`stores/quiz.ts`)
```typescript
import { defineStore } from 'pinia';
import { ref } from 'vue';

interface Question {
  id: string;
  correctOptionIndex: number;
}

export const useQuizStore = defineStore('quiz', () => {
  const chosenOptions = ref<Record<string, number>>({});
  const secondsLeft = ref(0);
  const isCompleted = ref(false);
  const score = ref<number | null>(null);
  let timerInterval: number | null = null;

  const startQuiz = (timeLimitSeconds: number) => {
    chosenOptions.value = {};
    secondsLeft.value = timeLimitSeconds;
    isCompleted.value = false;
    score.value = null;

    if (timerInterval) clearInterval(timerInterval);
    
    timerInterval = window.setInterval(() => {
      if (secondsLeft.value > 0) {
        secondsLeft.value -= 1;
      } else {
        submitQuiz([]);
      }
    }, 1000);
  };

  const selectOption = (questionId: string, optionIndex: number) => {
    if (!isCompleted.value) {
      chosenOptions.value[questionId] = optionIndex;
    }
  };

  const submitQuiz = (questions: Question[]) => {
    if (isCompleted.value) return;
    
    isCompleted.value = true;
    if (timerInterval) clearInterval(timerInterval);

    let correctCount = 0;
    questions.forEach(q => {
      if (chosenOptions.value[q.id] === q.correctOptionIndex) {
        correctCount += 1;
      }
    });

    score.value = questions.length > 0 
      ? Math.round((correctCount / questions.length) * 100) 
      : 0;
  };

  return {
    chosenOptions,
    secondsLeft,
    isCompleted,
    score,
    startQuiz,
    selectOption,
    submitQuiz
  };
});
```
