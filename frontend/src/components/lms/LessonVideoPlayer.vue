<script setup lang="ts">
import { ref, computed } from 'vue';
import { Maximize2, Minimize2 } from 'lucide-vue-next';

const props = defineProps<{
  videoUrl: string;
  title: string;
  durationMinutes?: number;
}>();

const theaterMode = ref(false);

const embedUrl = computed(() => {
  const url = props.videoUrl;
  if (url.includes('youtube.com/watch')) {
    const id = new URL(url).searchParams.get('v');
    return id ? `https://www.youtube.com/embed/${id}` : url;
  }
  return url;
});
</script>

<template>
  <div
    :class="[
      'relative rounded-3xl overflow-hidden bg-neutral-black shadow-premium transition-all duration-500',
      theaterMode ? 'aspect-[21/9]' : 'aspect-video'
    ]"
  >
    <iframe
      :src="embedUrl"
      :title="title"
      class="absolute inset-0 h-full w-full border-none"
      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
      allowfullscreen
    />

    <button
      type="button"
      class="absolute bottom-4 right-4 inline-flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white backdrop-blur-sm"
      @click="theaterMode = !theaterMode"
    >
      <component :is="theaterMode ? Minimize2 : Maximize2" class="h-3.5 w-3.5" />
      {{ theaterMode ? 'Standard' : 'Theater' }}
    </button>
  </div>
</template>
