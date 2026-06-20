<script setup lang="ts">
import { ref } from 'vue'

const props = withDefaults(defineProps<{
  src: string
  alt: string
  aspectRatio?: string
  className?: string
  placeholder?: string
}>(), {
  aspectRatio: 'aspect-video',
  className: '',
  placeholder: ''
})

const isLoaded = ref(false)

const onImageLoad = () => {
  isLoaded.value = true
}
</script>

<template>
  <div 
    class="relative overflow-hidden w-full bg-slate-900/50 backdrop-blur-sm rounded-xl border border-white/5" 
    :class="[props.aspectRatio, props.className]"
  >
    <!-- Blurred Low-Quality Placeholder -->
    <div 
      v-if="!isLoaded" 
      class="absolute inset-0 bg-cover bg-center filter blur-lg scale-110 transition-opacity duration-500 ease-out"
      :style="props.placeholder ? { backgroundImage: `url(${props.placeholder})` } : {}"
      :class="props.placeholder ? 'opacity-100' : 'opacity-20 animate-pulse bg-gradient-to-br from-indigo-500/10 via-purple-500/10 to-pink-500/10'"
    >
      <div class="absolute inset-0 flex items-center justify-center">
        <svg class="animate-spin h-5 w-5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
    </div>

    <!-- Main Image -->
    <img 
      :src="props.src" 
      :alt="props.alt" 
      loading="lazy" 
      class="w-full h-full object-cover transition-all duration-700 ease-out"
      :class="isLoaded ? 'opacity-100 scale-100 blur-0' : 'opacity-0 scale-105 blur-sm'"
      @load="onImageLoad"
    />
  </div>
</template>
