<template>
  <div class="p-6 max-w-3xl mx-auto space-y-6 text-neutral-black">
    <!-- Breadcrumbs & Title -->
    <div>
      <div class="flex items-center gap-2 text-xs text-neutral-muted font-bold uppercase tracking-wider mb-2">
        <router-link to="/admin/academy/courses" class="hover:text-neutral-muted transition">Courses</router-link>
        <span>/</span>
        <span class="text-neutral-muted">Create</span>
      </div>
      <h1 class="text-3xl font-extrabold tracking-tight text-primary">
        Create New Course
      </h1>
      <p class="text-sm text-neutral-muted mt-1">Setup the basic information, media assets, and parameters for the new course.</p>
    </div>

    <!-- Form -->
    <form @submit.prevent="handleSubmit" class="space-y-6 rounded-2xl bg-white border border-neutral-ivory p-6 shadow-xl shadow-premium">
      <!-- Title -->
      <div class="space-y-2">
        <label for="title" class="text-sm font-bold text-neutral-muted">Course Title *</label>
        <input 
          v-model="form.title"
          @input="generateSlug"
          id="title"
          type="text"
          placeholder="e.g. Introduction to Dawah Methods"
          required
          class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black placeholder-neutral-gray text-sm focus:outline-none focus:border-primary transition"
        />
      </div>

      <!-- Slug -->
      <div class="space-y-2">
        <label for="slug" class="text-sm font-bold text-neutral-muted">URL Slug *</label>
        <input 
          v-model="form.slug"
          id="slug"
          type="text"
          placeholder="e.g. intro-to-dawah-methods"
          required
          class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black placeholder-neutral-gray text-sm focus:outline-none focus:border-primary transition"
        />
        <p class="text-[11px] text-neutral-muted">Live URL preview: <span class="text-secondary">/academy/courses/{{ form.slug || 'slug' }}</span></p>
      </div>

      <!-- Description -->
      <div class="space-y-2">
        <label for="description" class="text-sm font-bold text-neutral-muted">Course Description</label>
        <textarea 
          v-model="form.description"
          id="description"
          rows="4"
          placeholder="Provide a comprehensive summary of what the course covers..."
          class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black placeholder-neutral-gray text-sm focus:outline-none focus:border-primary transition"
        ></textarea>
      </div>

      <!-- Grid for Difficulty, Duration & Status -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="space-y-2">
          <label for="difficulty" class="text-sm font-bold text-neutral-muted">Difficulty Level</label>
          <select 
            v-model="form.difficulty"
            id="difficulty"
            class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black text-sm focus:outline-none focus:border-primary transition"
          >
            <option value="beginner">Beginner</option>
            <option value="intermediate">Intermediate</option>
            <option value="advanced">Advanced</option>
          </select>
        </div>

        <div class="space-y-2">
          <label for="duration" class="text-sm font-bold text-neutral-muted">Duration (Minutes)</label>
          <input 
            v-model.number="form.estimated_duration"
            id="duration"
            type="number"
            min="0"
            placeholder="e.g. 120"
            class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black placeholder-neutral-gray text-sm focus:outline-none focus:border-primary transition"
          />
        </div>

        <div class="space-y-2">
          <label for="status" class="text-sm font-bold text-neutral-muted">Status</label>
          <select 
            v-model="form.status"
            id="status"
            class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black text-sm focus:outline-none focus:border-primary transition"
          >
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
          </select>
        </div>
      </div>

      <!-- Thumbnail URL -->
      <div class="space-y-2">
        <label for="thumbnail" class="text-sm font-bold text-neutral-muted">Thumbnail URL</label>
        <input 
          v-model="form.thumbnail"
          id="thumbnail"
          type="text"
          placeholder="https://images.unsplash.com/... or relative path"
          class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black placeholder-neutral-gray text-sm focus:outline-none focus:border-primary transition"
        />
        <!-- Mini Preview -->
        <div v-if="form.thumbnail" class="mt-2 aspect-video rounded-xl bg-neutral-background overflow-hidden border border-neutral-ivory max-h-40 max-w-xs">
          <img :src="resolvePublicImagePath(form.thumbnail)" alt="Preview" class="h-full w-full object-cover" />
        </div>
      </div>

      <!-- Submit & Cancel -->
      <div class="pt-4 border-t border-neutral-ivory flex items-center justify-end gap-3">
        <router-link 
          to="/admin/academy/courses"
          class="px-4 py-2 text-sm font-semibold rounded-lg bg-neutral-background border border-neutral-ivory hover:bg-neutral-background text-neutral-muted transition"
        >
          Cancel
        </router-link>
        <button 
          type="submit"
          :disabled="loading"
          class="px-4 py-2 text-sm font-semibold text-white bg-secondary hover:bg-secondary-light disabled:bg-neutral-gray disabled:text-neutral-muted rounded-lg transition duration-200 shadow-lg shadow-soft"
        >
          {{ loading ? 'Saving...' : 'Create Course' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAdminCoursesStore } from '@/stores/admin/academy/courses';
import { resolvePublicImagePath } from '@/constants/publicAssets';

const router = useRouter();
const store = useAdminCoursesStore();

const loading = ref(false);

const form = ref({
  title: '',
  slug: '',
  description: '',
  thumbnail: '',
  difficulty: 'beginner' as 'beginner' | 'intermediate' | 'advanced',
  estimated_duration: null as number | null,
  status: 'draft' as 'draft' | 'published' | 'archived',
});

const generateSlug = () => {
  form.value.slug = form.value.title
    .toLowerCase()
    .replace(/[^\w ]+/g, '')
    .replace(/ +/g, '-');
};

const handleSubmit = async () => {
  loading.value = true;
  try {
    const data = {
      ...form.value,
      estimated_duration: form.value.estimated_duration !== null ? form.value.estimated_duration : undefined
    };
    const created = await store.createCourse(data);
    // After creating, redirect to edit course to allow adding modules/lessons
    if (created && created.id) {
      router.push(`/admin/academy/courses/${created.id}/edit`);
    } else {
      router.push('/admin/academy/courses');
    }
  } catch (err: any) {
    alert(err.message || 'Failed to create course.');
  } finally {
    loading.value = false;
  }
};
</script>
