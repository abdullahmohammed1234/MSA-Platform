<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useCoursesStore } from '@/stores/academy/courses';
import { useProgressStore } from '@/stores/academy/progress';
import CourseCard from '@/components/academy/CourseCard.vue';

const coursesStore = useCoursesStore();
const progressStore = useProgressStore();

const searchQuery = ref('');
const selectedDifficulty = ref('all');
const selectedCategory = ref('all');
const selectedDuration = ref('all');
const selectedStatus = ref('all');
const sortBy = ref('newest');

onMounted(async () => {
  await coursesStore.fetchCourses();
});

// Category and tags mapper based on title (mock classification)
const getCourseCategory = (title: string): string => {
  const t = title.toLowerCase();
  if (t.includes('aqeedah') || t.includes('theology')) return 'theology';
  if (t.includes('comparative') || t.includes('religion')) return 'dialogue';
  return 'dawah';
};

const filteredCourses = computed(() => {
  let list = [...coursesStore.courses];

  // 1. Search Query (Title/Desc)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(c => 
      c.title.toLowerCase().includes(q) || 
      (c.description && c.description.toLowerCase().includes(q))
    );
  }

  // 2. Difficulty Filter
  if (selectedDifficulty.value !== 'all') {
    list = list.filter(c => c.difficulty === selectedDifficulty.value);
  }

  // 3. Category Filter
  if (selectedCategory.value !== 'all') {
    list = list.filter(c => getCourseCategory(c.title) === selectedCategory.value);
  }

  // 4. Duration Filter
  if (selectedDuration.value !== 'all') {
    list = list.filter(c => {
      const mins = c.estimated_duration || 90;
      if (selectedDuration.value === 'short') return mins < 120;
      if (selectedDuration.value === 'long') return mins >= 120;
      return true;
    });
  }

  // 5. Enrollment Status Filter
  if (selectedStatus.value !== 'all') {
    list = list.filter(c => {
      const isEnrolled = progressStore.courseProgress[c.id] !== undefined;
      return selectedStatus.value === 'enrolled' ? isEnrolled : !isEnrolled;
    });
  }

  // 6. Sorting
  list.sort((a, b) => {
    if (sortBy.value === 'newest') {
      return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
    }
    if (sortBy.value === 'updated') {
      return new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime();
    }
    if (sortBy.value === 'popular') {
      // Mock popularity sorting by ID
      return b.id - a.id;
    }
    return 0;
  });

  return list;
});
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Page title and header description -->
    <div>
      <h1 class="text-3xl font-display font-bold text-primary tracking-tight">Academy Programs</h1>
      <p class="text-neutral-muted text-sm mt-1 font-light">
        Explore structured courses covering Islamic theology, dialogue methodologies, and foundational Dawah tools.
      </p>
    </div>

    <!-- Search and Filters bar -->
    <div class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        
        <!-- Search -->
        <div class="md:col-span-2 relative">
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Search by title, description or keywords..."
            class="input-base text-sm pl-11 py-3"
          />
          <svg class="h-5 w-5 text-neutral-gray absolute left-4 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Sorting -->
        <div>
          <select v-model="sortBy" class="input-base text-sm py-3 cursor-pointer">
            <option value="newest">Newest First</option>
            <option value="popular">Most Popular</option>
            <option value="updated">Recently Updated</option>
          </select>
        </div>

        <!-- Enrollment Status -->
        <div>
          <select v-model="selectedStatus" class="input-base text-sm py-3 cursor-pointer">
            <option value="all">All Enrolled Status</option>
            <option value="enrolled">Enrolled</option>
            <option value="not-enrolled">Not Enrolled</option>
          </select>
        </div>

      </div>

      <!-- Advanced Filter Row -->
      <div class="flex flex-wrap gap-4 pt-3 border-t border-neutral-ivory/50">
        
        <!-- Difficulty -->
        <div class="flex items-center gap-2">
          <span class="text-xs font-semibold text-neutral-muted">Difficulty:</span>
          <select v-model="selectedDifficulty" class="px-3 py-1.5 bg-neutral-background border border-neutral-ivory rounded-xl text-xs outline-none cursor-pointer focus:border-primary">
            <option value="all">All Levels</option>
            <option value="beginner">Beginner</option>
            <option value="intermediate">Intermediate</option>
            <option value="advanced">Advanced</option>
          </select>
        </div>

        <!-- Category -->
        <div class="flex items-center gap-2">
          <span class="text-xs font-semibold text-neutral-muted">Topic:</span>
          <select v-model="selectedCategory" class="px-3 py-1.5 bg-neutral-background border border-neutral-ivory rounded-xl text-xs outline-none cursor-pointer focus:border-primary">
            <option value="all">All Topics</option>
            <option value="theology">Theology (Aqeedah)</option>
            <option value="dawah">Dawah Methods</option>
            <option value="dialogue">Dialogue Essentials</option>
          </select>
        </div>

        <!-- Duration -->
        <div class="flex items-center gap-2">
          <span class="text-xs font-semibold text-neutral-muted">Duration:</span>
          <select v-model="selectedDuration" class="px-3 py-1.5 bg-neutral-background border border-neutral-ivory rounded-xl text-xs outline-none cursor-pointer focus:border-primary">
            <option value="all">All Durations</option>
            <option value="short">Short (&lt; 2 Hours)</option>
            <option value="long">Long (&gt;= 2 Hours)</option>
          </select>
        </div>

      </div>
    </div>

    <!-- Courses Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="course in filteredCourses" :key="course.id" class="h-full">
        <CourseCard
          :course="course"
          :enrolled="progressStore.courseProgress[course.id] !== undefined"
          :progress="progressStore.courseProgress[course.id] || 0"
        />
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="filteredCourses.length === 0" class="bg-white border border-neutral-ivory rounded-3xl p-16 text-center shadow-soft">
      <svg class="mx-auto h-12 w-12 text-neutral-gray/60 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
      </svg>
      <h3 class="text-lg font-display font-semibold text-primary">No Programs Found</h3>
      <p class="text-neutral-muted text-sm mt-1 max-w-md mx-auto font-light">
        Try adjusting your filters or search keywords to find programs matching your interests.
      </p>
    </div>
  </div>
</template>
