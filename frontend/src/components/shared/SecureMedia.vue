<script setup lang="ts">
import { toRef } from 'vue';
import { Camera, AlertCircle, Loader2 } from 'lucide-vue-next';
import { useAuthenticatedMedia } from '@/composables/useAuthenticatedMedia';

const props = withDefaults(
  defineProps<{
    src: string | null | undefined;
    alt?: string;
    isVideo?: boolean;
    mediaClass?: string;
    poster?: string;
    autoplay?: boolean;
    controls?: boolean;
    muted?: boolean;
    playsinline?: boolean;
    preload?: string;
    loading?: 'lazy' | 'eager';
  }>(),
  {
    alt: '',
    isVideo: false,
    mediaClass: 'w-full h-full object-cover',
    poster: undefined,
    autoplay: false,
    controls: false,
    muted: true,
    playsinline: true,
    preload: 'metadata',
    loading: 'lazy',
  }
);

const { mediaSrc, isLoading, isError, isForbidden } = useAuthenticatedMedia(toRef(props, 'src'));
</script>

<template>
  <div class="relative w-full h-full flex items-center justify-center overflow-hidden">
    <!-- Loading skeleton -->
    <div
      v-if="isLoading"
      class="absolute inset-0 bg-neutral-100 flex items-center justify-center animate-pulse"
      aria-busy="true"
    >
      <Loader2 class="w-6 h-6 text-neutral-400 animate-spin" />
    </div>

    <!-- Error / Forbidden Fallback -->
    <div
      v-else-if="isError || isForbidden || !mediaSrc"
      class="absolute inset-0 bg-neutral-100/90 flex flex-col items-center justify-center p-4 text-center"
    >
      <component
        :is="isForbidden ? AlertCircle : Camera"
        class="w-8 h-8 text-neutral-400 mb-1.5"
      />
      <span class="text-xs font-medium text-neutral-500">
        {{ isForbidden ? 'Restricted media' : 'Media unavailable' }}
      </span>
    </div>

    <!-- Video Element -->
    <video
      v-else-if="isVideo"
      :src="mediaSrc"
      :class="mediaClass"
      :controls="controls"
      :autoplay="autoplay"
      :muted="muted"
      :playsinline="playsinline"
      :preload="preload"
      :poster="poster"
    />

    <!-- Image Element -->
    <img
      v-else
      :src="mediaSrc"
      :alt="alt"
      :class="mediaClass"
      :loading="loading"
      referrerpolicy="no-referrer"
    />
  </div>
</template>
