<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

interface Props {
  offset?: number;
}

const props = withDefaults(defineProps<Props>(), {
  offset: 50
});

const sectionRef = ref<HTMLElement | null>(null);
const translateY = ref(-props.offset);

const handleScroll = () => {
  if (!sectionRef.value) return;
  const rect = sectionRef.value.getBoundingClientRect();
  const windowHeight = window.innerHeight;
  
  // Calculate relative position (0 = enters bottom, 1 = leaves top)
  const totalRange = windowHeight + rect.height;
  const currentPos = windowHeight - rect.top;
  const progress = Math.max(0, Math.min(1, currentPos / totalRange));
  
  // Map progress (0 -> 1) to (-offset -> offset)
  translateY.value = -props.offset + (progress * 2 * props.offset);
};

onMounted(() => {
  window.addEventListener('scroll', handleScroll, { passive: true });
  window.addEventListener('resize', handleScroll, { passive: true });
  handleScroll();
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
  window.removeEventListener('resize', handleScroll);
});
</script>

<template>
  <div ref="sectionRef" class="relative overflow-hidden w-full h-full">
    <div 
      :style="{ 
        transform: `translateY(${translateY}px)`,
        willChange: 'transform'
      }" 
      class="w-full h-full transition-transform duration-75 ease-out"
    >
      <slot></slot>
    </div>
  </div>
</template>
