<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import client from '@/services/api/client';
import { useCoursesStore } from '@/stores/academy/courses';
import { useToastStore } from '@/components/feedback/toast';

interface CourseItem {
  id: number;
  title: string;
}

interface PathItem {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  description: string;
  courses: CourseItem[];
  created_at: string;
}

const toast = useToastStore();
const coursesStore = useCoursesStore();

const paths = ref<PathItem[]>([]);
const isLoading = ref(false);
const selectedPathId = ref<number | null>(null);

// Path Modals
const showPathModal = ref(false);
const isEditingPath = ref(false);

// Form State
const pathForm = ref({
  id: undefined as number | undefined,
  title: '',
  slug: '',
  description: ''
});

// Course Assignment Form State
const assignCourseId = ref('');
const assigning = ref(false);

const selectedPath = computed(() => {
  return paths.value.find(p => p.id === selectedPathId.value) || null;
});

// Unassigned courses for the current selected path
const unassignedCourses = computed(() => {
  if (!selectedPath.value) return [];
  const assignedIds = selectedPath.value.courses.map(c => c.id);
  return coursesStore.courses.filter(c => !assignedIds.includes(c.id));
});

onMounted(async () => {
  await loadPaths();
  await coursesStore.fetchCourses();
});

const loadPaths = async () => {
  isLoading.value = true;
  try {
    const res = await client.get('/admin/academy/learning-paths');
    paths.value = res.data.learning_paths || [];
  } catch (error) {
    console.error('Failed to load learning paths:', error);
    toast.error('Failed to load learning paths.');
  } finally {
    isLoading.value = false;
  }
};

const generateSlug = () => {
  pathForm.value.slug = pathForm.value.title
    .toLowerCase()
    .trim()
    .replace(/[^\w ]+/g, '')
    .replace(/ +/g, '-');
};

const openCreatePath = () => {
  isEditingPath.value = false;
  pathForm.value = {
    id: undefined,
    title: '',
    slug: '',
    description: ''
  };
  showPathModal.value = true;
};

const openEditPath = (path: PathItem) => {
  isEditingPath.value = true;
  pathForm.value = {
    id: path.id,
    title: path.title,
    slug: path.slug,
    description: path.description || ''
  };
  showPathModal.value = true;
};

const handlePathSubmit = async () => {
  const form = pathForm.value;
  if (!form.title.trim() || !form.slug.trim()) {
    toast.warning('Please specify path title and slug.');
    return;
  }

  const payload = {
    title: form.title.trim(),
    slug: form.slug.trim(),
    description: form.description.trim() || null
  };

  try {
    if (isEditingPath.value && form.id) {
      await client.put(`/admin/academy/learning-paths/${form.id}`, payload);
      toast.success('Learning path updated successfully.');
    } else {
      await client.post('/admin/academy/learning-paths', payload);
      toast.success('Learning path created successfully.');
    }
    showPathModal.value = false;
    await loadPaths();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to save learning path.');
  }
};

const handleDeletePath = async (pathId: number, title: string) => {
  if (!confirm(`Delete learning path "${title}"? This cannot be undone.`)) return;
  try {
    await client.delete(`/admin/academy/learning-paths/${pathId}`);
    toast.success('Learning path deleted successfully.');
    if (selectedPathId.value === pathId) {
      selectedPathId.value = null;
    }
    await loadPaths();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to delete learning path.');
  }
};

// Course Assignments Handlers
const handleAssignCourse = async () => {
  if (!selectedPathId.value || !assignCourseId.value) return;
  assigning.value = true;
  try {
    await client.post(`/admin/academy/learning-paths/${selectedPathId.value}/courses`, {
      course_id: Number(assignCourseId.value)
    });
    toast.success('Course assigned to learning path successfully.');
    assignCourseId.value = '';
    await loadPaths();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to assign course.');
  } finally {
    assigning.value = false;
  }
};

const handleRemoveCourse = async (courseId: number, courseTitle: string) => {
  if (!selectedPathId.value) return;
  if (!confirm(`Remove course "${courseTitle}" from this learning path?`)) return;
  try {
    await client.delete(`/admin/academy/learning-paths/${selectedPathId.value}/courses/${courseId}`);
    toast.success('Course removed from learning path.');
    await loadPaths();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to remove course.');
  }
};

const handleReorderCourse = async (direction: 'up' | 'down', index: number) => {
  if (!selectedPath.value || !selectedPathId.value) return;
  const list = [...selectedPath.value.courses];
  
  if (direction === 'up' && index > 0) {
    const temp = list[index];
    list[index] = list[index - 1];
    list[index - 1] = temp;
  } else if (direction === 'down' && index < list.length - 1) {
    const temp = list[index];
    list[index] = list[index + 1];
    list[index + 1] = temp;
  } else {
    return;
  }

  try {
    await client.post(`/admin/academy/learning-paths/${selectedPathId.value}/courses/reorder`, {
      course_ids: list.map(c => c.id)
    });
    toast.success('Course ordering saved successfully.');
    await loadPaths();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to save course ordering.');
  }
};
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Academy Learning Paths</h1>
        <p class="text-sm text-neutral-muted mt-1">Configure sequences of academy courses for structured learning certifications.</p>
      </div>

      <button
        @click="openCreatePath"
        class="bg-primary hover:bg-primary/95 text-white font-bold text-xs px-4 py-2.5 rounded-xl cursor-pointer shadow-soft"
      >
        New Learning Path
      </button>
    </div>

    <!-- Main Grid layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      
      <!-- Left 5 Columns: Paths List -->
      <div class="lg:col-span-5 space-y-4">
        <h2 class="text-base font-bold text-neutral-black">Learning Path Templates</h2>

        <div class="space-y-3">
          <div
            v-for="path in paths"
            :key="path.id"
            class="bg-white border rounded-2xl p-5 shadow-soft hover:shadow-premium transition-all duration-300 cursor-pointer relative"
            :class="[
              selectedPathId === path.id ? 'border-primary bg-neutral-background/10' : 'border-neutral-ivory hover:border-neutral-ivory/80'
            ]"
            @click="selectedPathId = path.id"
          >
            <div class="space-y-2">
              <div class="flex justify-between items-start gap-2">
                <h3 class="font-display font-bold text-sm text-primary leading-snug">
                  {{ path.title }}
                </h3>
                <span class="bg-primary/10 text-primary text-[8px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full whitespace-nowrap">
                  {{ path.courses.length }} Courses
                </span>
              </div>
              <p class="text-[11px] text-neutral-muted leading-relaxed font-light">
                {{ path.description }}
              </p>
            </div>

            <!-- Path actions -->
            <div class="mt-4 pt-3 border-t border-neutral-ivory/50 flex justify-end gap-3 text-[10px] font-bold">
              <button
                @click.stop="openEditPath(path)"
                class="text-primary hover:underline cursor-pointer"
              >
                Edit Details
              </button>
              <button
                @click.stop="handleDeletePath(path.id, path.title)"
                class="text-red-500 hover:underline cursor-pointer"
              >
                Delete
              </button>
            </div>
          </div>

          <div v-if="paths.length === 0" class="text-center py-12 text-xs text-neutral-muted italic bg-white border border-neutral-ivory rounded-2xl shadow-soft">
            No learning paths defined.
          </div>
        </div>
      </div>

      <!-- Right 7 Columns: Assigned Courses & Sequencing -->
      <div class="lg:col-span-7 space-y-6" v-if="selectedPath">
        <div class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-6">
          <div>
            <span class="text-[9px] font-mono font-bold text-neutral-muted uppercase tracking-wider">Learning Path Detail</span>
            <h2 class="text-lg font-display font-extrabold text-primary leading-snug mt-0.5">
              {{ selectedPath.title }}
            </h2>
            <p class="text-xs text-neutral-muted font-light mt-1">{{ selectedPath.description }}</p>
          </div>

          <!-- Course Assignment Section -->
          <div class="border-t border-neutral-ivory/60 pt-6 space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Assign Course to Path</h3>
            <div class="flex items-center gap-2">
              <select
                v-model="assignCourseId"
                class="w-full p-2.5 text-xs rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 cursor-pointer"
                :disabled="assigning || unassignedCourses.length === 0"
              >
                <option value="">-- Choose Course to Add --</option>
                <option v-for="course in unassignedCourses" :key="course.id" :value="course.id">
                  {{ course.title }}
                </option>
              </select>
              <button
                @click="handleAssignCourse"
                class="bg-primary hover:bg-primary/95 text-white font-bold text-xs px-4 py-2.5 rounded-xl flex-shrink-0 cursor-pointer disabled:opacity-50"
                :disabled="assigning || !assignCourseId || unassignedCourses.length === 0"
              >
                {{ assigning ? 'Adding...' : 'Add Course' }}
              </button>
            </div>
            <p class="text-[9px] text-neutral-muted font-light italic" v-if="unassignedCourses.length === 0">
              All available academy courses are already assigned to this pathway.
            </p>
          </div>

          <!-- Assigned Courses Sequence list -->
          <div class="border-t border-neutral-ivory/60 pt-6 space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Course Sequence Flow</h3>
            
            <div class="divide-y divide-neutral-ivory/50">
              <div
                v-for="(course, idx) in selectedPath.courses"
                :key="course.id"
                class="py-3.5 first:pt-0 last:pb-0 flex items-center justify-between gap-4"
              >
                <div class="flex items-center gap-3">
                  <!-- Sequence number badge -->
                  <span class="h-6 w-6 rounded-full bg-neutral-ivory/80 text-primary font-bold font-mono text-[10px] flex items-center justify-center">
                    {{ idx + 1 }}
                  </span>
                  <span class="text-xs font-bold text-neutral-black">{{ course.title }}</span>
                </div>

                <div class="flex items-center gap-3 font-bold text-xs">
                  <!-- Ordering arrows -->
                  <div class="flex items-center gap-1.5 text-neutral-muted">
                    <button
                      @click="handleReorderCourse('up', idx)"
                      :disabled="idx === 0"
                      class="hover:text-primary disabled:opacity-30 cursor-pointer bg-transparent border-none p-1"
                      title="Move Up"
                    >
                      ▲
                    </button>
                    <button
                      @click="handleReorderCourse('down', idx)"
                      :disabled="idx === selectedPath.courses.length - 1"
                      class="hover:text-primary disabled:opacity-30 cursor-pointer bg-transparent border-none p-1"
                      title="Move Down"
                    >
                      ▼
                    </button>
                  </div>

                  <!-- Remove action -->
                  <button
                    @click="handleRemoveCourse(course.id, course.title)"
                    class="text-red-500 hover:text-red-600 cursor-pointer bg-transparent border-none hover:underline"
                  >
                    Remove
                  </button>
                </div>
              </div>

              <div v-if="selectedPath.courses.length === 0" class="text-center py-8 text-xs text-neutral-muted italic font-light">
                No courses assigned to this learning path yet. Assign courses above to formulate the study syllabus.
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="lg:col-span-7 flex flex-col items-center justify-center py-20 bg-white border border-neutral-ivory border-dashed rounded-2xl shadow-soft">
        <svg class="h-12 w-12 text-neutral-muted mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
        <h3 class="font-bold text-neutral-black mb-1">No Learning Path Selected</h3>
        <p class="text-xs text-neutral-muted max-w-sm text-center font-light">Choose a learning path template on the left panel to configure its course sequences and syllabus flow.</p>
      </div>

    </div>

    <!-- Create/Edit Path Modal -->
    <div
      v-if="showPathModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-black/40 backdrop-blur-sm p-4"
    >
      <div class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-premium space-y-6">
        <div>
          <h3 class="text-lg font-display font-semibold text-primary">
            {{ isEditingPath ? 'Edit Learning Path' : 'Create New Learning Path' }}
          </h3>
          <p class="text-xs text-neutral-muted mt-0.5 font-light">
            Set up the pathway title, custom slug, and study curriculum description.
          </p>
        </div>

        <div class="space-y-4">
          <!-- Title -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Path Title</label>
            <input
              type="text"
              v-model="pathForm.title"
              @input="generateSlug"
              placeholder="e.g. Volunteer Specialist Path"
              class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40"
            />
          </div>

          <!-- Slug -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Slug (auto-generated)</label>
            <input
              type="text"
              v-model="pathForm.slug"
              placeholder="e.g. volunteer-specialist-path"
              class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 font-mono"
            />
          </div>

          <!-- Description -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Description</label>
            <textarea
              v-model="pathForm.description"
              placeholder="Provide a comprehensive syllabus overview for this specialized path..."
              rows="3"
              class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 resize-none"
            ></textarea>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            @click="showPathModal = false"
            class="px-4 py-2 border border-neutral-ivory text-neutral-muted text-xs rounded-xl cursor-pointer hover:bg-neutral-background transition-colors"
          >
            Cancel
          </button>
          <button
            @click="handlePathSubmit"
            class="px-5 py-2 bg-primary text-white font-bold text-xs rounded-xl cursor-pointer hover:bg-primary/95 shadow-soft transition-colors"
          >
            {{ isEditingPath ? 'Save Changes' : 'Create Path' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
