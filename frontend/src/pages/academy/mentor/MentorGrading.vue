<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useMentorStore } from '@/stores/academy/mentor';
import Button from '@/components/ui/button/Button.vue';
import { Motion } from '@motionone/vue';

const mentorStore = useMentorStore();

// Track custom grading scores locally per submission
const gradingScores = ref<Record<string | number, number>>({});

onMounted(() => {
  if (!mentorStore.isLoaded) {
    mentorStore.fetchState();
  }
});

const getScore = (subId: string | number, currentScore?: number) => {
  if (gradingScores.value[subId] !== undefined) {
    return gradingScores.value[subId];
  }
  return currentScore ?? 85;
};

const handleSaveCorrection = async (subId: string | number, defaultScore?: number) => {
  const score = gradingScores.value[subId] ?? defaultScore ?? 85;
  await mentorStore.gradeSubmission(subId, score);
};
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-display font-bold text-primary tracking-tight">Corrections Queue</h1>
      <p class="text-neutral-muted text-sm mt-1 font-light">
        Evaluate and score reflections, outreach logs, and qualitative assignments from your learners.
      </p>
    </div>

    <div v-if="mentorStore.isLoading" class="flex justify-center items-center py-20">
      <div class="h-8 w-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else class="space-y-6">
      <Motion
        v-for="submission in mentorStore.submissions"
        :key="submission.id"
        :initial="{ opacity: 0, y: 15 }"
        :animate="{ opacity: 1, y: 0 }"
        class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft"
      >
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
          <!-- Submission Details -->
          <div class="flex-grow space-y-3">
            <div class="flex items-center gap-2">
              <span 
                class="text-[9px] font-bold px-2 py-0.5 rounded-full font-mono uppercase tracking-wider"
                :class="submission.type === 'reflection' ? 'bg-indigo-500/10 border border-indigo-500/20 text-indigo-700' : 'bg-cyan-500/10 border border-cyan-500/20 text-cyan-700'"
              >
                {{ submission.type.replace('_', ' ') }}
              </span>
              <span class="text-xs text-neutral-muted">&bull; Submitted {{ submission.submittedAt }}</span>
            </div>
            
            <h2 class="text-xl font-display font-bold text-primary">{{ submission.itemTitle }}</h2>
            <p class="text-xs font-semibold text-neutral-muted">{{ submission.volunteerName }} &bull; {{ submission.courseTitle }}</p>
            
            <div class="rounded-2xl border border-neutral-ivory bg-neutral-background/50 p-4 mt-2">
              <p class="text-xs text-neutral-black leading-relaxed font-light whitespace-pre-wrap">
                {{ submission.details }}
              </p>
            </div>
          </div>

          <!-- Grading Action Block -->
          <div class="w-full lg:w-64 flex-shrink-0 border border-neutral-ivory rounded-2xl p-4 space-y-4">
            <div class="flex justify-between items-center text-xs">
              <span class="font-bold text-neutral-muted uppercase tracking-wider">Status</span>
              <span 
                class="font-mono font-bold uppercase"
                :class="submission.status === 'graded' ? 'text-green-700' : 'text-amber-700'"
              >
                {{ submission.status === 'graded' ? `Score: ${submission.score}%` : 'Pending' }}
              </span>
            </div>

            <!-- Grade Slider -->
            <div class="space-y-2">
              <div class="flex justify-between text-xs font-semibold">
                <span class="text-neutral-muted">Correction Grade:</span>
                <span class="text-primary font-bold">{{ getScore(submission.id, submission.score) }}%</span>
              </div>
              <input
                type="range"
                min="50"
                max="100"
                :value="getScore(submission.id, submission.score)"
                @input="(e) => gradingScores[submission.id] = Number((e.target as HTMLInputElement).value)"
                class="w-full accent-primary cursor-pointer"
              />
            </div>

            <Button
              size="sm"
              class="w-full bg-primary hover:bg-primary-dark text-white rounded-xl text-xs py-2.5"
              @click="handleSaveCorrection(submission.id, submission.score)"
            >
              Save Correction
            </Button>
          </div>
        </div>
      </Motion>

      <div 
        v-if="!mentorStore.submissions.length"
        class="bg-white border border-neutral-ivory p-8 rounded-3xl text-center text-neutral-muted text-sm shadow-soft"
      >
        No submissions are waiting for mentor review.
      </div>
    </div>
  </div>
</template>
