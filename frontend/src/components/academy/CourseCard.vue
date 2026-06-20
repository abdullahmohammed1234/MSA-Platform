<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { Motion } from '@motionone/vue';
import Badge from '@/components/data-display/badge/Badge.vue';
import LmsProgressBar from '@/components/data-display/progress/LmsProgressBar.vue';
import { buttonHover } from '@/design-system/animations/hover';
import type { CourseSummary } from '@/types/academy';
import { resolvePublicImagePath } from '@/constants/publicAssets';

const props = withDefaults(defineProps<{
  course: CourseSummary;
  progress?: number;
  enrolled?: boolean;
}>(), {
  progress: 0,
  enrolled: false
});

const router = useRouter();

const thumbnailUrl = computed(() =>
  props.course.thumbnail ? resolvePublicImagePath(props.course.thumbnail) : '',
);

const difficultyVariant = computed(() => {
  switch (props.course.difficulty) {
    case 'beginner': return 'success';
    case 'intermediate': return 'warning';
    case 'advanced': return 'error';
    default: return 'primary';
  }
});

const difficultyLabel = computed(() => {
  return props.course.difficulty.charAt(0).toUpperCase() + props.course.difficulty.slice(1);
});

const isCompleted = computed(() => props.progress >= 100);

const navigateToDetails = () => {
  router.push(`/academy/courses/${props.course.slug}`);
};
</script>

<template>
  <Motion
    :hover="{ scale: 1.02, y: -6 }"
    :transition="{ duration: 0.3, ease: 'easeOut' }"
    class="premium-card flex flex-col h-full bg-white border border-neutral-ivory rounded-3xl shadow-soft hover:shadow-premium group overflow-hidden cursor-pointer"
    @click="navigateToDetails"
  >
    <!-- Course Thumbnail / Premium Islamic Pattern Cover -->
    <div class="relative h-44 w-full bg-primary overflow-hidden flex-shrink-0">
      <div v-if="!course.thumbnail" class="absolute inset-0 pattern-islamic opacity-20"></div>
      <div v-if="!course.thumbnail" class="absolute inset-0 bg-gradient-to-t from-primary-dark/80 via-primary/30 to-transparent flex items-center justify-center p-6 text-center">
        <span class="font-display text-xl font-bold text-white tracking-wide drop-shadow-md leading-snug">
          {{ course.title }}
        </span>
      </div>
      <img
        v-else
        :src="thumbnailUrl"
        :alt="course.title"
        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
      />
      
      <!-- Tags Overlay -->
      <div class="absolute top-4 left-4 flex gap-2">
        <Badge :variant="difficultyVariant" size="sm">
          {{ difficultyLabel }}
        </Badge>
      </div>
      
      <!-- Completed Banner -->
      <div
        v-if="enrolled && isCompleted"
        class="absolute top-4 right-4 bg-success text-white px-2 py-0.5 text-[9px] font-bold rounded-full border border-success/30 uppercase tracking-widest flex items-center gap-1 shadow-md"
      >
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
        </svg>
        Completed
      </div>
    </div>

    <!-- Course Info Card Body -->
    <div class="p-6 flex-grow flex flex-col justify-between">
      <div>
        <h3 class="font-display font-semibold text-lg text-primary group-hover:text-primary-light transition-colors leading-snug mb-2 truncate">
          {{ course.title }}
        </h3>
        
        <p class="text-neutral-muted text-xs leading-relaxed line-clamp-3 mb-4 font-light">
          {{ course.description || 'No description provided.' }}
        </p>
      </div>

      <div class="space-y-4">
        <!-- Progress Bar if Enrolled -->
        <div v-if="enrolled" class="w-full">
          <div class="flex justify-between items-center text-[10px] mb-1 font-semibold text-neutral-muted">
            <span>Course Progress</span>
            <span class="text-primary font-bold">{{ progress }}%</span>
          </div>
          <LmsProgressBar :value="progress" color="bg-secondary" />
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-neutral-ivory/40">
          <div class="flex items-center gap-1.5 text-xs text-neutral-muted">
            <svg class="h-4 w-4 text-neutral-gray" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-mono text-[10px]">{{ course.estimated_duration || 90 }} mins</span>
          </div>

          <!-- Quick Action -->
          <Motion
            :hover="buttonHover.hover"
            :press="buttonHover.tap"
            :transition="buttonHover.transition"
            as="button"
            class="text-xs font-semibold text-primary group-hover:underline flex items-center gap-1 cursor-pointer"
            @click.stop="navigateToDetails"
          >
            <span v-if="enrolled && !isCompleted">Resume</span>
            <span v-else-if="enrolled && isCompleted">Review</span>
            <span v-else>Enroll Now</span>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
            </svg>
          </Motion>
        </div>
      </div>
    </div>
  </Motion>
</template>
