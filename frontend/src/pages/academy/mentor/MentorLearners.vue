<script setup lang="ts">
import { onMounted } from 'vue';
import { useMentorStore } from '@/stores/academy/mentor';
import { Motion } from '@motionone/vue';

const mentorStore = useMentorStore();

onMounted(() => {
  if (!mentorStore.isLoaded) {
    mentorStore.fetchState();
  }
});
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-display font-bold text-primary tracking-tight">Learner Coaching</h1>
      <p class="text-neutral-muted text-sm mt-1 font-light">
        Monitor your assigned volunteers, review academic performance streaks, and identify potential support needs.
      </p>
    </div>

    <div v-if="mentorStore.isLoading" class="flex justify-center items-center py-20">
      <div class="h-8 w-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else class="grid gap-6 md:grid-cols-2">
      <Motion
        v-for="student in mentorStore.volunteers"
        :key="student.id"
        :initial="{ opacity: 0, scale: 0.98 }"
        :animate="{ opacity: 1, scale: 1 }"
        class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft space-y-6"
      >
        <!-- Student Header -->
        <div class="flex items-center gap-4">
          <img 
            :src="student.avatar" 
            :alt="student.name" 
            class="h-14 w-14 rounded-2xl border border-neutral-ivory bg-neutral-background object-cover" 
          />
          <div>
            <h2 class="text-lg font-display font-bold text-primary">{{ student.name }}</h2>
            <p class="text-xs text-neutral-muted">{{ student.email }}</p>
          </div>
        </div>

        <!-- Student Metrics -->
        <div class="grid grid-cols-2 gap-6 border-t border-neutral-ivory pt-6">
          <div>
            <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Enrolled Courses</p>
            <p class="mt-1 text-sm font-semibold text-neutral-black">{{ student.enrolledCourses }}</p>
          </div>
          <div>
            <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Completed Courses</p>
            <p class="mt-1 text-sm font-semibold text-neutral-black">{{ student.completedCourses }}</p>
          </div>
          <div>
            <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Quiz Average</p>
            <p class="mt-1 text-sm font-semibold text-neutral-black">{{ student.quizAverage }}%</p>
          </div>
          <div>
            <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Risk Status</p>
            <span 
              class="mt-1.5 inline-block text-[10px] font-bold uppercase tracking-wider font-mono px-2 py-0.5 rounded-full"
              :class="student.riskStatus === 'active' ? 'bg-green-500/10 border border-green-500/20 text-green-700' : 'bg-green-500/10 border border-green-500/20 text-green-700'"
            >
              {{ student.riskStatus ?? 'active' }}
            </span>
          </div>
        </div>
      </Motion>

      <div 
        v-if="!mentorStore.volunteers.length"
        class="col-span-2 bg-white border border-neutral-ivory p-8 rounded-3xl text-center text-neutral-muted text-sm shadow-soft"
      >
        No volunteers assigned to you at this time.
      </div>
    </div>
  </div>
</template>
