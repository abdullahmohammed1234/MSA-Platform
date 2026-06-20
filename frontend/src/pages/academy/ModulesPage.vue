<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { Layers, CheckCircle, Lock, PlayCircle, Clock, BookOpen, ArrowRight } from 'lucide-vue-next';
import { useCoursesStore } from '@/stores/academy/courses';
import { useProgressStore } from '@/stores/academy/progress';
import { coursesService } from '@/services/academy/coursesService';
import type { Course, Module } from '@/types/academy';

interface ModuleRow {
  id: number;
  title: string;
  description?: string | null;
  courseId: number;
  courseTitle: string;
  courseSlug: string;
  lessonCount: number;
  completedCount: number;
  estimatedDuration: number;
  isLocked: boolean;
  firstLessonId?: number;
}

const router = useRouter();
const coursesStore = useCoursesStore();
const progressStore = useProgressStore();

const loading = ref(true);
const error = ref<string | null>(null);
const enrolledCourses = ref<Course[]>([]);

onMounted(async () => {
  loading.value = true;
  error.value = null;

  try {
    await coursesStore.fetchCourses();
    const details = await Promise.all(
      coursesStore.courses.map((course) => coursesService.getCourseDetails(course.slug))
    );

    enrolledCourses.value = details
      .filter((entry) => entry.enrollment)
      .map((entry) => {
        progressStore.syncProgress(entry.course.id, entry.progress);
        return entry.course;
      });
  } catch (err: any) {
    error.value = err.message || 'Failed to load modules.';
  } finally {
    loading.value = false;
  }
});

const modules = computed((): ModuleRow[] => {
  const rows: ModuleRow[] = [];

  enrolledCourses.value.forEach((course) => {
    (course.modules || []).forEach((mod: Module) => {
      const lessons = mod.lessons || [];
      const completedCount = lessons.filter((lesson) =>
        progressStore.isLessonCompleted(course.id, lesson.id)
      ).length;
      const firstLesson = lessons[0];

      rows.push({
        id: mod.id,
        title: mod.title,
        description: mod.description,
        courseId: course.id,
        courseTitle: course.title,
        courseSlug: course.slug,
        lessonCount: lessons.length,
        completedCount,
        estimatedDuration: mod.estimated_duration || 0,
        isLocked: lessons.length === 0,
        firstLessonId: firstLesson?.id,
      });
    });
  });

  return rows;
});

const openModule = (mod: ModuleRow) => {
  if (mod.firstLessonId) {
    router.push(`/academy/courses/${mod.courseId}/lessons/${mod.firstLessonId}`);
    return;
  }
  router.push(`/academy/courses/${mod.courseSlug}`);
};
</script>

<template>
  <div class="space-y-8 pb-16">
    <div>
      <h1 class="text-3xl font-display font-bold text-primary tracking-tight">Learning Modules</h1>
      <p class="text-neutral-muted text-sm mt-1 font-light">
        Track module progress across your enrolled Dawah Academy programs.
      </p>
    </div>

    <div v-if="loading" class="py-16 text-center text-neutral-muted">Loading modules...</div>
    <div v-else-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
      {{ error }}
    </div>
    <div
      v-else-if="modules.length === 0"
      class="rounded-3xl border border-neutral-ivory bg-white p-10 text-center shadow-soft"
    >
      <Layers class="mx-auto h-10 w-10 text-neutral-muted" />
      <h2 class="mt-4 text-lg font-semibold text-primary">No modules yet</h2>
      <p class="mt-2 text-sm text-neutral-muted">Enroll in a course to unlock its module syllabus.</p>
      <button
        type="button"
        class="mt-6 inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-white"
        @click="router.push('/academy/courses')"
      >
        Browse Courses
        <ArrowRight class="h-4 w-4" />
      </button>
    </div>

    <div v-else class="grid grid-cols-1 gap-4">
      <article
        v-for="mod in modules"
        :key="`${mod.courseId}-${mod.id}`"
        class="rounded-3xl border border-neutral-ivory bg-white p-6 shadow-soft"
      >
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div class="space-y-2">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-muted">{{ mod.courseTitle }}</p>
            <h2 class="text-xl font-display font-semibold text-primary">{{ mod.title }}</h2>
            <p v-if="mod.description" class="text-sm text-neutral-muted">{{ mod.description }}</p>
            <div class="flex flex-wrap items-center gap-4 text-xs text-neutral-muted">
              <span class="inline-flex items-center gap-1.5">
                <BookOpen class="h-3.5 w-3.5" />
                {{ mod.completedCount }}/{{ mod.lessonCount }} lessons
              </span>
              <span v-if="mod.estimatedDuration" class="inline-flex items-center gap-1.5">
                <Clock class="h-3.5 w-3.5" />
                {{ mod.estimatedDuration }} min
              </span>
            </div>
          </div>

          <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-xs font-bold uppercase tracking-wider transition-colors"
            :class="mod.isLocked
              ? 'cursor-not-allowed bg-neutral-ivory text-neutral-muted'
              : 'bg-primary text-white hover:bg-primary-dark'"
            :disabled="mod.isLocked"
            @click="openModule(mod)"
          >
            <component :is="mod.isLocked ? Lock : mod.completedCount === mod.lessonCount && mod.lessonCount > 0 ? CheckCircle : PlayCircle" class="h-4 w-4" />
            {{ mod.isLocked ? 'Locked' : mod.completedCount === mod.lessonCount && mod.lessonCount > 0 ? 'Review' : 'Continue' }}
          </button>
        </div>
      </article>
    </div>
  </div>
</template>
