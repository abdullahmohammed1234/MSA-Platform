<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useAdminCoursesStore } from '@/stores/admin/academy/courses';
import { useAdminStudentsStore } from '@/stores/admin/academy/students';
import { useAdminMentorsStore } from '@/stores/admin/academy/mentors';
import { BarChart3, Users, BookOpen, GraduationCap, RefreshCw } from 'lucide-vue-next';

const coursesStore = useAdminCoursesStore();
const studentsStore = useAdminStudentsStore();
const mentorsStore = useAdminMentorsStore();

const loadAnalytics = async () => {
  await Promise.all([
    coursesStore.fetchCourses(),
    studentsStore.fetchStudents(),
    mentorsStore.fetchMentors(),
  ]);
};

const publishedCourses = computed(() => coursesStore.courses.filter((course) => course.status === 'published'));
const courseCompletionSignal = computed(() => {
  if (studentsStore.students.length === 0) return 0;
  return Math.min(100, Math.round((mentorsStore.mentors.length / Math.max(studentsStore.students.length, 1)) * 100));
});

onMounted(loadAnalytics);
</script>

<template>
  <div class="space-y-8 max-w-7xl mx-auto p-1 text-neutral-black">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white border border-neutral-ivory rounded-2xl p-6 shadow-xl">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-primary">
          Volunteer Analytics
        </h1>
        <p class="text-sm text-neutral-muted mt-1">Track academy engagement, mentor coverage, and published curriculum health at a glance.</p>
      </div>
      <button @click="loadAnalytics" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-neutral-background border border-neutral-ivory text-sm font-semibold hover:bg-neutral-background transition">
        <RefreshCw class="h-4 w-4" /> Refresh analytics
      </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Students</div>
        <div class="mt-2 text-3xl font-black text-white flex items-center gap-2"><Users class="h-5 w-5 text-secondary" />{{ studentsStore.students.length }}</div>
      </div>
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Mentors</div>
        <div class="mt-2 text-3xl font-black text-white flex items-center gap-2"><GraduationCap class="h-5 w-5 text-primary" />{{ mentorsStore.mentors.length }}</div>
      </div>
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Published courses</div>
        <div class="mt-2 text-3xl font-black text-white flex items-center gap-2"><BookOpen class="h-5 w-5 text-amber-400" />{{ publishedCourses.length }}</div>
      </div>
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Coverage signal</div>
        <div class="mt-2 text-3xl font-black text-white flex items-center gap-2"><BarChart3 class="h-5 w-5 text-secondary" />{{ courseCompletionSignal }}%</div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 rounded-2xl bg-white border border-neutral-ivory p-6">
        <h2 class="text-lg font-bold text-white">Curriculum distribution</h2>
        <p class="text-sm text-neutral-muted mt-1">Published curriculum readiness is based on live store counts and can be refined as backend analytics mature.</p>

        <div class="mt-6 space-y-4">
          <div>
            <div class="flex items-center justify-between text-sm mb-2">
              <span class="text-neutral-muted">Student roster</span>
              <span class="text-neutral-muted">{{ studentsStore.students.length }}</span>
            </div>
            <div class="h-2 rounded-full bg-neutral-background overflow-hidden"><div class="h-full bg-secondary" :style="{ width: `${Math.min(100, studentsStore.students.length * 4)}%` }"></div></div>
          </div>
          <div>
            <div class="flex items-center justify-between text-sm mb-2">
              <span class="text-neutral-muted">Mentor coverage</span>
              <span class="text-neutral-muted">{{ mentorsStore.mentors.length }}</span>
            </div>
            <div class="h-2 rounded-full bg-neutral-background overflow-hidden"><div class="h-full bg-blue-400" :style="{ width: `${Math.min(100, mentorsStore.mentors.length * 8)}%` }"></div></div>
          </div>
          <div>
            <div class="flex items-center justify-between text-sm mb-2">
              <span class="text-neutral-muted">Course publication</span>
              <span class="text-neutral-muted">{{ publishedCourses.length }} of {{ coursesStore.courses.length }}</span>
            </div>
            <div class="h-2 rounded-full bg-neutral-background overflow-hidden"><div class="h-full bg-amber-400" :style="{ width: `${coursesStore.courses.length ? Math.round((publishedCourses.length / coursesStore.courses.length) * 100) : 0}%` }"></div></div>
          </div>
        </div>
      </div>

      <div class="rounded-2xl bg-white border border-neutral-ivory p-6">
        <h2 class="text-lg font-bold text-white">Operational shortcuts</h2>
        <div class="mt-4 space-y-2 text-sm">
          <router-link to="/admin/academy/students" class="block rounded-xl bg-neutral-background/70 hover:bg-neutral-background px-4 py-3 text-neutral-black transition">Open student roster</router-link>
          <router-link to="/admin/academy/mentors" class="block rounded-xl bg-neutral-background/70 hover:bg-neutral-background px-4 py-3 text-neutral-black transition">Open mentor desk</router-link>
          <router-link to="/admin/academy/courses" class="block rounded-xl bg-neutral-background/70 hover:bg-neutral-background px-4 py-3 text-neutral-black transition">Open course desk</router-link>
          <router-link to="/admin/academy/progress" class="block rounded-xl bg-neutral-background/70 hover:bg-neutral-background px-4 py-3 text-neutral-black transition">Open learner progress</router-link>
        </div>
      </div>
    </div>
  </div>
</template>