<script setup lang="ts">
import Badge from '@/components/data-display/badge/Badge.vue';
import type { Quiz, QuizAttempt } from '@/types/academy';

defineProps<{
  quiz: Quiz | null | undefined;
  attempt: QuizAttempt;
}>();

const getQuestionText = (quiz: Quiz | null | undefined, qId: number): string => {
  return quiz?.questions?.find((item) => item.id === qId)?.question || 'Assessment Question';
};

const getExplanation = (quiz: Quiz | null | undefined, qId: number): string => {
  const question = quiz?.questions?.find((item) => item.id === qId);
  return question?.explanation || 'Review the course syllabus for this topic.';
};
</script>

<template>
  <div class="space-y-4">
    <h2 class="text-xl font-display font-semibold text-primary">Detailed Review</h2>

    <div
      v-for="ans in attempt.answers"
      :key="ans.id"
      :class="[
        'bg-white border p-6 rounded-2xl shadow-soft space-y-4',
        ans.is_correct ? 'border-success/35' : 'border-secondary/35'
      ]"
    >
      <div class="flex items-start justify-between gap-4">
        <h3 class="font-display font-semibold text-primary text-base leading-snug">
          {{ getQuestionText(quiz, ans.question_id) }}
        </h3>
        <Badge :variant="ans.is_correct ? 'success' : 'error'" size="sm" class="flex-shrink-0">
          {{ ans.is_correct ? 'Correct' : 'Incorrect' }}
        </Badge>
      </div>

      <div class="text-xs font-mono">
        <span class="text-neutral-muted font-semibold">Your selection:</span>
        <span class="text-neutral-black font-semibold ml-1.5">{{ ans.answer.join(', ') || 'Unanswered' }}</span>
      </div>

      <div class="bg-neutral-background p-4 rounded-xl border border-neutral-ivory/50 text-xs text-neutral-muted leading-relaxed">
        <span class="font-semibold text-neutral-black block mb-1">Explanation:</span>
        {{ getExplanation(quiz, ans.question_id) }}
      </div>
    </div>
  </div>
</template>
