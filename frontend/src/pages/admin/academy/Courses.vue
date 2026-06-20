<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6 text-neutral-black">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-primary">
          Course Management
        </h1>
        <p class="text-sm text-neutral-muted mt-1">
          Create, edit, publish, and delete courses for the Dawah Academy.
        </p>
      </div>
      <router-link 
        to="/admin/academy/courses/create"
        class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-secondary hover:bg-secondary-light rounded-lg transition duration-200 shadow-lg shadow-soft"
      >
        ➕ Create Course
      </router-link>
    </div>

    <!-- Filters & Search -->
    <div class="p-4 rounded-xl bg-white border border-neutral-ivory flex flex-col md:flex-row gap-4 items-center justify-between">
      <div class="w-full md:w-80 relative">
        <input 
          v-model="filters.search"
          @input="onSearch"
          type="text"
          placeholder="Search courses by title..."
          class="w-full bg-white border border-neutral-ivory rounded-lg py-2 px-3 pl-9 text-neutral-black placeholder-neutral-gray text-sm focus:outline-none focus:border-primary transition"
        />
        <span class="absolute left-3 top-2.5 text-neutral-muted text-sm">🔍</span>
      </div>

      <div class="flex gap-4 w-full md:w-auto">
        <select 
          v-model="filters.status"
          @change="onFilterChange"
          class="bg-neutral-background border border-neutral-ivory text-neutral-black text-sm rounded-lg p-2 focus:outline-none focus:border-primary transition"
        >
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>

        <select 
          v-model="filters.difficulty"
          @change="onFilterChange"
          class="bg-neutral-background border border-neutral-ivory text-neutral-black text-sm rounded-lg p-2 focus:outline-none focus:border-primary transition"
        >
          <option value="">All Difficulties</option>
          <option value="beginner">Beginner</option>
          <option value="intermediate">Intermediate</option>
          <option value="advanced">Advanced</option>
        </select>
      </div>
    </div>

    <!-- Course Grid -->
    <div v-if="store.loading" class="flex flex-col items-center justify-center py-20 gap-3">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
      <p class="text-sm text-neutral-muted font-medium animate-pulse">Loading courses...</p>
    </div>

    <div v-else-if="store.courses.length === 0" class="p-12 text-center rounded-2xl bg-white border border-neutral-ivory">
      <div class="text-4xl mb-3">📚</div>
      <h3 class="text-lg font-bold text-white">No courses found</h3>
      <p class="text-sm text-neutral-muted mt-1">Try adjusting your filters or search query, or create a new course.</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div 
        v-for="course in store.courses" 
        :key="course.id"
        class="group flex flex-col justify-between overflow-hidden rounded-2xl bg-white border border-neutral-ivory hover:border-neutral-ivory hover:shadow-xl hover:shadow-soft transition duration-300"
      >
        <!-- Thumbnail -->
        <div class="relative aspect-video bg-neutral-background overflow-hidden">
          <img 
            v-if="course.thumbnail"
            :src="resolvePublicImagePath(course.thumbnail)"
            :alt="course.title"
            class="h-full w-full object-cover group-hover:scale-105 transition duration-500"
          />
          <div v-else class="h-full w-full flex items-center justify-center text-neutral-muted text-4xl border-neutral-ivory">
            📖
          </div>
          <!-- Status Tag -->
          <span :class="`absolute top-3 right-3 text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full ${statusClass(course.status)}`">
            {{ course.status }}
          </span>
        </div>

        <!-- Content -->
        <div class="p-5 flex-1 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between gap-2">
              <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">
                ⚡ {{ course.difficulty }}
              </span>
              <span class="text-xs text-neutral-muted font-semibold" v-if="course.estimated_duration">
                🕒 {{ course.estimated_duration }} min
              </span>
            </div>
            <h2 class="text-lg font-bold text-white mt-2 group-hover:text-secondary-light transition duration-200 line-clamp-1">
              {{ course.title }}
            </h2>
            <p class="text-xs text-neutral-muted mt-1 line-clamp-2 leading-relaxed">
              {{ course.description || 'No description provided.' }}
            </p>
          </div>

          <!-- Actions -->
          <div class="mt-6 pt-4 border-t border-neutral-ivory/80 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <router-link 
                :to="`/admin/academy/courses/${course.id}/edit`"
                class="px-3 py-1.5 text-xs font-bold rounded-lg bg-neutral-background hover:bg-neutral-background text-neutral-black transition"
              >
                ✏️ Edit
              </router-link>
              <button 
                @click="onDelete(course)"
                class="px-3 py-1.5 text-xs font-bold rounded-lg bg-red-950/20 hover:bg-red-950/40 text-red-400 border border-red-900/30 hover:border-red-900/50 transition"
              >
                🗑️ Delete
              </button>
            </div>

            <!-- Quick Publish Switch -->
            <button 
              v-if="course.status === 'draft'"
              @click="togglePublish(course)"
              class="text-xs font-bold text-secondary hover:text-secondary-light transition"
            >
              Publish →
            </button>
            <button 
              v-if="course.status === 'published'"
              @click="toggleArchive(course)"
              class="text-xs font-bold text-neutral-muted hover:text-neutral-muted transition"
            >
              Archive
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAdminCoursesStore } from '@/stores/admin/academy/courses';
import { resolvePublicImagePath } from '@/constants/publicAssets';

const store = useAdminCoursesStore();

const filters = ref({
  search: '',
  status: '',
  difficulty: '',
});

const statusClass = (status: string) => {
  switch (status) {
    case 'published': return 'bg-secondary/10 text-secondary border border-secondary/20';
    case 'draft': return 'bg-primary/10 text-primary border border-primary/15';
    case 'archived': return 'bg-neutral-background text-neutral-muted border border-neutral-ivory';
    default: return 'bg-neutral-background text-neutral-muted';
  }
};

const onSearch = () => {
  store.fetchCourses(filters.value);
};

const onFilterChange = () => {
  store.fetchCourses(filters.value);
};

const togglePublish = async (course: any) => {
  try {
    await store.updateCourse(course.id, { status: 'published' });
  } catch (err: any) {
    alert(err.message || 'Failed to publish course.');
  }
};

const toggleArchive = async (course: any) => {
  try {
    await store.updateCourse(course.id, { status: 'archived' });
  } catch (err: any) {
    alert(err.message || 'Failed to archive course.');
  }
};

const onDelete = async (course: any) => {
  if (confirm(`Are you sure you want to delete "${course.title}"?`)) {
    try {
      await store.deleteCourse(course.id);
    } catch (err) {
      alert('Failed to delete course.');
    }
  }
};

onMounted(() => {
  store.fetchCourses(filters.value);
});
</script>
