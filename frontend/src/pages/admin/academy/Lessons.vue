<template>
  <div class="p-6 max-w-5xl mx-auto space-y-6 text-neutral-black">
    <div>
      <h1 class="text-3xl font-extrabold tracking-tight text-white">Lesson Summary</h1>
      <p class="text-xs text-neutral-muted mt-1">To edit lessons, use the curriculum builder inside the course editor settings.</p>
    </div>

    <!-- Courses List Grid -->
    <div class="grid grid-cols-1 gap-6">
      <div 
        v-for="course in coursesStore.courses" 
        :key="course.id"
        class="p-6 rounded-xl bg-white border border-neutral-ivory space-y-4"
      >
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-3">
          <h2 class="text-lg font-bold text-white">{{ course.title }}</h2>
          <router-link 
            :to="`/admin/academy/courses/${course.id}/edit`"
            class="text-xs font-semibold text-secondary hover:text-secondary-light transition"
          >
            Manage Curriculum →
          </router-link>
        </div>

        <div v-if="loading" class="text-neutral-muted text-xs py-4">Loading lessons...</div>
        <div v-else class="space-y-4">
          <div v-if="!courseModules[course.id]?.length" class="text-xs text-neutral-muted italic">No modules.</div>
          <div 
            v-else
            v-for="mod in courseModules[course.id]" 
            :key="mod.id"
            class="space-y-2"
          >
            <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider">{{ mod.title }}</div>
            <div v-if="!mod.lessons?.length" class="text-xs text-neutral-muted pl-4">No lessons in this module.</div>
            <div 
              v-else
              v-for="les in mod.lessons" 
              :key="les.id"
              class="flex items-center justify-between p-3 rounded-lg bg-neutral-background/40 text-sm pl-4 ml-2"
            >
              <span>{{ les.title }}</span>
              <span class="text-xs text-neutral-muted">{{ les.estimated_duration }} mins</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAdminCoursesStore } from '@/stores/admin/academy/courses';
import client from '@/services/api';

const coursesStore = useAdminCoursesStore();
const loading = ref(false);
const courseModules = ref<Record<number, any[]>>({});

onMounted(async () => {
  loading.value = true;
  await coursesStore.fetchCourses();
  for (const course of coursesStore.courses) {
    try {
      const response = await client.get(`/academy/courses/${course.id}`);
      if (response.data.success) {
        courseModules.value[course.id] = response.data.course.modules || [];
      }
    } catch (e) {
      courseModules.value[course.id] = [];
    }
  }
  loading.value = false;
});
</script>
