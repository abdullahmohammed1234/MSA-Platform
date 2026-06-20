<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCoursesStore } from '@/stores/academy/courses';
import Breadcrumbs from '@/components/navigation/breadcrumbs/Breadcrumbs.vue';
import EmptyState from '@/components/data-display/empty-state/EmptyState.vue';
import { BookOpen, PlayCircle } from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const coursesStore = useCoursesStore();

const moduleId = computed(() => Number(route.params.moduleId));

onMounted(async () => {
  if (coursesStore.courses.length === 0) {
    await coursesStore.fetchCourses();
  }
  await Promise.all(
    coursesStore.courses.map((course) => coursesStore.fetchCourseDetails(course.slug))
  );
});

const moduleContext = computed(() => {
  for (const course of coursesStore.courses) {
    const mod = course.modules?.find((m) => m.id === moduleId.value);
    if (mod) {
      return { course, module: mod };
    }
  }
  return null;
});

const breadcrumbItems = computed(() => {
  if (!moduleContext.value) return [{ id: 'modules', label: 'Module' }];
  return [
    { id: 'modules', label: 'Modules' },
    { id: 'module', label: moduleContext.value.module.title },
  ];
});

const openLesson = (courseId: number, lessonId: number) => {
  router.push(`/academy/courses/${courseId}/lessons/${lessonId}`);
};
</script>

<template>
  <div class="space-y-6 pb-16">
    <Breadcrumbs
      :items="breadcrumbItems"
      @home="router.push('/academy')"
      @navigate="(item) => item.id === 'modules' && router.push('/academy/modules')"
    />

    <div v-if="!moduleContext" class="py-12 text-center text-neutral-muted">
      Loading module…
    </div>

    <template v-else>
      <div>
        <p class="text-xs font-mono uppercase text-neutral-muted">{{ moduleContext.course.title }}</p>
        <h1 class="text-3xl font-display font-bold text-primary">{{ moduleContext.module.title }}</h1>
        <p class="text-sm text-neutral-muted mt-1">{{ moduleContext.module.description }}</p>
      </div>

      <div v-if="!moduleContext.module.lessons?.length">
        <EmptyState title="No lessons in this module" description="Lessons will appear here once published." />
      </div>

      <div v-else class="space-y-3">
        <button
          v-for="lesson in moduleContext.module.lessons"
          :key="lesson.id"
          type="button"
          class="w-full flex items-center justify-between rounded-2xl border border-neutral-ivory bg-white p-4 text-left hover:border-primary/30 cursor-pointer"
          @click="openLesson(moduleContext.course.id, lesson.id)"
        >
          <div class="flex items-center gap-3">
            <BookOpen class="h-4 w-4 text-primary" />
            <span class="text-sm font-semibold text-neutral-black">{{ lesson.title }}</span>
          </div>
          <PlayCircle class="h-4 w-4 text-primary" />
        </button>
      </div>
    </template>
  </div>
</template>
