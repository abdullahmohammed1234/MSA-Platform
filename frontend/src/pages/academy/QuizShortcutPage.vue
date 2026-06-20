<script setup lang="ts">
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCoursesStore } from '@/stores/academy/courses';
import { coursesService } from '@/services/academy/coursesService';

const route = useRoute();
const router = useRouter();
const coursesStore = useCoursesStore();

onMounted(async () => {
  const quizId = Number(route.params.quizId);
  if (coursesStore.courses.length === 0) {
    await coursesStore.fetchCourses();
  }

  for (const course of coursesStore.courses) {
    const details = await coursesService.getCourseDetails(course.slug);
    const quiz = details.course.quizzes?.find((q) => q.id === quizId);
    if (quiz) {
      router.replace(`/academy/courses/${details.course.id}/quizzes/${quizId}`);
      return;
    }
  }

  router.replace('/academy/quizzes');
});
</script>

<template>
  <div class="py-20 text-center text-neutral-muted text-sm">Opening quiz…</div>
</template>
