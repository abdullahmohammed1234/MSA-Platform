<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useMentorStore } from '@/stores/academy/mentor';
import Button from '@/components/ui/button/Button.vue';
import { Motion } from '@motionone/vue';

const mentorStore = useMentorStore();

const courseTitle = ref('');
const courseDescription = ref('');

onMounted(() => {
  if (!mentorStore.isLoaded) {
    mentorStore.fetchState();
  }
});

const handleCreateCourse = (e: Event) => {
  e.preventDefault();
  if (!courseTitle.value.trim()) return;

  const newCourse = {
    id: Date.now(),
    title: courseTitle.value.trim(),
    slug: courseTitle.value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''),
    category: 'Foundations',
    description: courseDescription.value.trim() || 'Mentor-authored course draft awaiting final review.',
    mentorId: 'current-mentor',
    mentorName: 'Current Mentor',
    mentorAvatar: '',
    published: false,
    enrollmentCount: 0,
    completionRate: 0,
    quizAverage: 0,
    xpReward: 100,
    modulesCount: 0,
    lessons: [],
    createdAt: new Date().toISOString(),
  };

  mentorStore.saveCourseDraft(newCourse);
  courseTitle.value = '';
  courseDescription.value = '';
};
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-display font-bold text-primary tracking-tight">Course Supervision</h1>
      <p class="text-neutral-muted text-sm mt-1 font-light">
        Review live course metrics and author draft course shells for administrator publication.
      </p>
    </div>

    <div v-if="mentorStore.isLoading" class="flex justify-center items-center py-20">
      <div class="h-8 w-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Course Cards (Left 2 Columns) -->
      <div class="lg:col-span-2 space-y-6">
        <Motion
          v-for="course in mentorStore.courses"
          :key="course.id"
          :initial="{ opacity: 0, y: 15 }"
          :animate="{ opacity: 1, y: 0 }"
          class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft space-y-6"
        >
          <div class="flex justify-between items-start gap-4">
            <div>
              <span class="text-[10px] font-bold text-secondary uppercase tracking-widest">{{ course.category }}</span>
              <h2 class="text-xl font-display font-bold text-primary mt-1">{{ course.title }}</h2>
              <p class="text-xs text-neutral-muted mt-2 leading-relaxed">{{ course.description }}</p>
            </div>
            <span 
              class="text-xs font-semibold px-2.5 py-1 rounded-full font-mono uppercase tracking-wider"
              :class="course.published ? 'bg-green-500/10 border border-green-500/20 text-green-700' : 'bg-amber-500/10 border border-amber-500/20 text-amber-700'"
            >
              {{ course.published ? 'Live' : 'Draft' }}
            </span>
          </div>

          <!-- Course Fields Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-6 border-t border-neutral-ivory pt-6">
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Enrolled</p>
              <p class="mt-1 text-sm font-semibold text-neutral-black">{{ course.enrollmentCount }}</p>
            </div>
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Completion</p>
              <p class="mt-1 text-sm font-semibold text-neutral-black">{{ course.completionRate }}%</p>
            </div>
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Exam Avg</p>
              <p class="mt-1 text-sm font-semibold text-neutral-black">{{ course.quizAverage }}%</p>
            </div>
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Modules</p>
              <p class="mt-1 text-sm font-semibold text-neutral-black">{{ course.modulesCount }}</p>
            </div>
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Lessons</p>
              <p class="mt-1 text-sm font-semibold text-neutral-black">{{ course.lessons.length }}</p>
            </div>
          </div>
        </Motion>
      </div>

      <!-- Draft Authoring Form (Right Column) -->
      <div>
        <div class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-6 sticky top-24">
          <h2 class="text-lg font-display font-bold text-primary flex items-center gap-2">
            <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Author Course Draft
          </h2>

          <form @submit="handleCreateCourse" class="space-y-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Course Title</label>
              <input
                v-model="courseTitle"
                required
                placeholder="e.g. Comparative Religion Level 1"
                class="w-full bg-neutral-ivory/30 border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold"
              />
            </div>

            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Description</label>
              <textarea
                v-model="courseDescription"
                rows="4"
                placeholder="Course purpose, learning outcomes, and expectations..."
                class="w-full bg-neutral-ivory/30 border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black leading-relaxed"
              ></textarea>
            </div>

            <Button type="submit" size="lg" class="w-full bg-primary hover:bg-primary-dark text-white rounded-xl">
              Create Course Draft
            </Button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>
