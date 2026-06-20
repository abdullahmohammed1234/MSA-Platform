<template>
  <div class="p-6 max-w-5xl mx-auto space-y-6 text-neutral-black">
    <!-- Breadcrumbs -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2 text-xs text-neutral-muted font-bold uppercase tracking-wider">
        <router-link to="/admin/academy/courses" class="hover:text-neutral-muted transition">Courses</router-link>
        <span>/</span>
        <span class="text-neutral-muted">Edit</span>
      </div>
      <router-link 
        to="/admin/academy/courses"
        class="text-xs font-bold text-neutral-muted hover:text-white transition"
      >
        ← Back to Courses
      </router-link>
    </div>

    <!-- Title and Tabs -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-neutral-ivory pb-4">
      <div>
        <h1 class="text-3xl font-extrabold text-white">
          {{ store.currentCourse?.title || 'Edit Course' }}
        </h1>
        <p class="text-xs text-neutral-muted mt-1">Manage course settings, outline modules, and edit lessons.</p>
      </div>

      <div class="flex rounded-xl bg-white border border-neutral-ivory p-1">
        <button 
          @click="activeTab = 'settings'"
          :class="`px-4 py-2 text-xs font-bold rounded-lg transition ${activeTab === 'settings' ? 'bg-secondary text-white shadow-md shadow-soft' : 'text-neutral-muted hover:text-neutral-black'}`"
        >
          ⚙️ Course Settings
        </button>
        <button 
          @click="activeTab = 'curriculum'"
          :class="`px-4 py-2 text-xs font-bold rounded-lg transition ${activeTab === 'curriculum' ? 'bg-secondary text-white shadow-md shadow-soft' : 'text-neutral-muted hover:text-neutral-black'}`"
        >
          📖 Curriculum Builder
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="store.loading && !store.currentCourse" class="flex justify-center py-20">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <!-- Content Area -->
    <div v-else class="space-y-6">
      <!-- Tab 1: Settings Form -->
      <div v-show="activeTab === 'settings'" class="rounded-2xl bg-white border border-neutral-ivory p-6 space-y-6 shadow-xl">
        <h2 class="text-lg font-bold text-white mb-4">Edit Course Profile</h2>
        <form @submit.prevent="saveSettings" class="space-y-6">
          <div class="space-y-2">
            <label class="text-sm font-bold text-neutral-muted">Course Title *</label>
            <input 
              v-model="settingsForm.title"
              type="text"
              required
              class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black focus:outline-none focus:border-primary transition text-sm"
            />
          </div>

          <div class="space-y-2">
            <label class="text-sm font-bold text-neutral-muted">URL Slug *</label>
            <input 
              v-model="settingsForm.slug"
              type="text"
              required
              class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black focus:outline-none focus:border-primary transition text-sm"
            />
          </div>

          <div class="space-y-2">
            <label class="text-sm font-bold text-neutral-muted">Description</label>
            <textarea 
              v-model="settingsForm.description"
              rows="4"
              class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black focus:outline-none focus:border-primary transition text-sm"
            ></textarea>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="space-y-2">
              <label class="text-sm font-bold text-neutral-muted">Difficulty Level</label>
              <select 
                v-model="settingsForm.difficulty"
                class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black text-sm focus:outline-none focus:border-primary transition"
              >
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
              </select>
            </div>

            <div class="space-y-2">
              <label class="text-sm font-bold text-neutral-muted">Duration (Minutes)</label>
              <input 
                v-model.number="settingsForm.estimated_duration"
                type="number"
                min="0"
                class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black text-sm focus:outline-none focus:border-primary transition"
              />
            </div>

            <div class="space-y-2">
              <label class="text-sm font-bold text-neutral-muted">Status</label>
              <select 
                v-model="settingsForm.status"
                class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black text-sm focus:outline-none focus:border-primary transition"
              >
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="archived">Archived</option>
              </select>
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-bold text-neutral-muted">Thumbnail URL</label>
            <input 
              v-model="settingsForm.thumbnail"
              type="text"
              class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2.5 text-neutral-black text-sm focus:outline-none focus:border-primary transition"
            />
          </div>

          <div class="flex justify-end pt-4 border-t border-neutral-ivory">
            <button 
              type="submit"
              class="px-4 py-2 text-sm font-bold text-white bg-secondary hover:bg-secondary-light rounded-lg transition"
            >
              Save Changes
            </button>
          </div>
        </form>
      </div>

      <!-- Tab 2: Curriculum Builder -->
      <div v-show="activeTab === 'curriculum'" class="space-y-6">
        <!-- Add Module Button -->
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-white">Modules & Lessons Outline</h2>
          <button 
            @click="openModuleModal()"
            class="px-3 py-1.5 text-xs font-bold text-secondary bg-primary/5 hover:bg-primary/10 border border-primary/15 rounded-lg transition"
          >
            ➕ Add Module
          </button>
        </div>

        <!-- Empty State -->
        <div v-if="!store.currentCourse?.modules?.length" class="p-12 text-center rounded-2xl bg-white border border-neutral-ivory">
          <p class="text-sm text-neutral-muted">This course has no modules. Create a module to start adding lessons.</p>
        </div>

        <!-- Modules Accordion -->
        <div class="space-y-4" v-else>
          <div 
            v-for="(mod, modIndex) in store.currentCourse.modules" 
            :key="mod.id"
            class="rounded-xl bg-white border border-neutral-ivory overflow-hidden"
          >
            <!-- Module Header -->
            <div class="flex items-center justify-between p-4 border-neutral-ivory border-b border-neutral-ivory gap-4">
              <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-neutral-muted">M{{ modIndex + 1 }}</span>
                <h3 class="font-extrabold text-white">{{ mod.title }}</h3>
                <span class="text-[10px] text-neutral-muted bg-neutral-background px-2 py-0.5 rounded-full font-semibold">
                  {{ mod.lessons?.length || 0 }} Lessons
                </span>
              </div>

              <!-- Module Actions -->
              <div class="flex items-center gap-2">
                <button 
                  @click="openLessonModal(mod.id)"
                  class="px-2.5 py-1 text-[11px] font-bold text-secondary hover:text-white hover:bg-primary/10 rounded transition"
                >
                  ➕ Add Lesson
                </button>
                <button 
                  @click="openModuleModal(mod)"
                  class="p-1.5 text-xs text-neutral-muted hover:text-white transition"
                  title="Edit Module"
                >
                  ✏️
                </button>
                <button 
                  @click="deleteModule(mod)"
                  class="p-1.5 text-xs text-red-400 hover:text-red-300 transition"
                  title="Delete Module"
                >
                  🗑️
                </button>
                <div class="flex flex-col text-[8px] gap-0.5 ml-2">
                  <button @click="moveModule(modIndex, -1)" class="hover:text-secondary">▲</button>
                  <button @click="moveModule(modIndex, 1)" class="hover:text-secondary">▼</button>
                </div>
              </div>
            </div>

            <!-- Lessons List -->
            <div class="divide-y divide-neutral-ivory bg-white/50 p-2">
              <div v-if="!mod.lessons || mod.lessons.length === 0" class="py-4 text-center text-xs text-neutral-muted">
                No lessons in this module.
              </div>
              <div 
                v-else
                v-for="(les, lesIndex) in mod.lessons" 
                :key="les.id"
                class="flex items-center justify-between py-2 px-3 hover:bg-neutral-background/60 rounded-lg group transition"
              >
                <div class="flex items-center gap-3">
                  <span class="text-xs text-neutral-muted font-semibold">{{ modIndex + 1 }}.{{ lesIndex + 1 }}</span>
                  <div>
                    <h4 class="text-sm font-semibold text-neutral-black group-hover:text-secondary-light transition duration-200">
                      {{ les.title }}
                    </h4>
                    <div class="flex items-center gap-2 text-[10px] text-neutral-muted font-semibold mt-0.5">
                      <span v-if="les.is_required">Required</span>
                      <span v-else>Optional</span>
                      <span>•</span>
                      <span>{{ les.estimated_duration }} min</span>
                    </div>
                  </div>
                </div>

                <!-- Lesson Actions -->
                <div class="flex items-center gap-2">
                  <button 
                    @click="openLessonModal(mod.id, les)"
                    class="p-1 text-neutral-muted hover:text-white transition"
                    title="Edit Lesson"
                  >
                    ✏️
                  </button>
                  <button 
                    @click="deleteLesson(les)"
                    class="p-1 text-red-400 hover:text-red-350 transition"
                    title="Delete Lesson"
                  >
                    🗑️
                  </button>
                  <div class="flex flex-col text-[8px] gap-0.5 ml-2">
                    <button @click="moveLesson(mod, lesIndex, -1)" class="hover:text-secondary">▲</button>
                    <button @click="moveLesson(mod, lesIndex, 1)" class="hover:text-secondary">▼</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Module Form Modal -->
    <div v-if="showModuleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-background/70 backdrop-blur-sm">
      <div class="w-full max-w-md bg-white border border-neutral-ivory rounded-2xl p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-white mb-4">{{ editingModuleId ? 'Edit Module' : 'Create Module' }}</h3>
        <form @submit.prevent="saveModule" class="space-y-4">
          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Module Title *</label>
            <input v-model="moduleForm.title" type="text" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary" />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Description</label>
            <textarea v-model="moduleForm.description" rows="3" class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showModuleModal = false" class="px-4 py-2 text-xs font-bold rounded-lg bg-neutral-background border border-neutral-ivory hover:bg-neutral-background text-neutral-muted transition">Cancel</button>
            <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg text-white bg-secondary hover:bg-secondary-light transition">Save</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Lesson Form Modal -->
    <div v-if="showLessonModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-background/70 backdrop-blur-sm">
      <div class="w-full max-w-lg bg-white border border-neutral-ivory rounded-2xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-white mb-4">{{ editingLessonId ? 'Edit Lesson' : 'Create Lesson' }}</h3>
        <form @submit.prevent="saveLesson" class="space-y-4">
          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Lesson Title *</label>
            <input v-model="lessonForm.title" type="text" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary" />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Lesson Content (Markdown / Text)</label>
            <textarea v-model="lessonForm.content" rows="6" placeholder="Provide lesson materials here..." class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary font-mono"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Estimated Duration (Min) *</label>
              <input v-model.number="lessonForm.estimated_duration" type="number" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary" />
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Required Lesson</label>
              <select v-model="lessonForm.is_required" class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary">
                <option :value="true">Yes, mandatory</option>
                <option :value="false">No, optional</option>
              </select>
            </div>
          </div>
          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Video Embed URL</label>
            <input v-model="lessonForm.video_url" type="text" placeholder="https://www.youtube.com/embed/..." class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary" />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showLessonModal = false" class="px-4 py-2 text-xs font-bold rounded-lg bg-neutral-background border border-neutral-ivory hover:bg-neutral-background text-neutral-muted transition">Cancel</button>
            <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg text-white bg-secondary hover:bg-secondary-light transition">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAdminCoursesStore } from '@/stores/admin/academy/courses';
import { useAdminLessonsStore } from '@/stores/admin/academy/lessons';

const route = useRoute();
const store = useAdminCoursesStore();
const lessonsStore = useAdminLessonsStore();

const activeTab = ref('settings');

const settingsForm = ref({
  title: '',
  slug: '',
  description: '',
  thumbnail: '',
  difficulty: 'beginner' as 'beginner' | 'intermediate' | 'advanced',
  estimated_duration: null as number | null,
  status: 'draft' as 'draft' | 'published' | 'archived',
});

// Modals status
const showModuleModal = ref(false);
const editingModuleId = ref<number | null>(null);
const moduleForm = ref({ title: '', description: '' });

const showLessonModal = ref(false);
const activeModuleIdForLesson = ref<number | null>(null);
const editingLessonId = ref<number | null>(null);
const lessonForm = ref({
  title: '',
  content: '',
  estimated_duration: 15,
  is_required: true,
  video_url: '',
});

const loadCourse = async () => {
  const id = route.params.id as string;
  await store.fetchCourseDetails(id);
  if (store.currentCourse) {
    settingsForm.value.title = store.currentCourse.title;
    settingsForm.value.slug = store.currentCourse.slug;
    settingsForm.value.description = store.currentCourse.description || '';
    settingsForm.value.thumbnail = store.currentCourse.thumbnail || '';
    settingsForm.value.difficulty = store.currentCourse.difficulty;
    settingsForm.value.estimated_duration = store.currentCourse.estimated_duration || null;
    settingsForm.value.status = store.currentCourse.status;
  }
};

const saveSettings = async () => {
  if (!store.currentCourse) return;
  try {
    await store.updateCourse(store.currentCourse.id, settingsForm.value);
    alert('Course settings updated successfully.');
  } catch (err: any) {
    alert(err.message || 'Failed to update course settings.');
  }
};

// Module Actions
const openModuleModal = (module: any = null) => {
  if (module) {
    editingModuleId.value = module.id;
    moduleForm.value.title = module.title;
    moduleForm.value.description = module.description || '';
  } else {
    editingModuleId.value = null;
    moduleForm.value.title = '';
    moduleForm.value.description = '';
  }
  showModuleModal.value = true;
};

const saveModule = async () => {
  if (!store.currentCourse) return;
  try {
    if (editingModuleId.value) {
      await lessonsStore.updateModule(editingModuleId.value, moduleForm.value);
    } else {
      await lessonsStore.createModule({
        course_id: store.currentCourse.id,
        ...moduleForm.value,
      });
    }
    showModuleModal.value = false;
    await loadCourse(); // reload
  } catch (err: any) {
    alert(err.message);
  }
};

const deleteModule = async (module: any) => {
  if (confirm(`Delete module "${module.title}"? All lessons inside will be deleted.`)) {
    try {
      await lessonsStore.deleteModule(module.id);
      await loadCourse();
    } catch (err: any) {
      alert(err.message);
    }
  }
};

const moveModule = async (index: number, direction: number) => {
  if (!store.currentCourse || !store.currentCourse.modules) return;
  const targetIndex = index + direction;
  if (targetIndex < 0 || targetIndex >= store.currentCourse.modules.length) return;

  const modules = [...store.currentCourse.modules];
  const temp = modules[index];
  modules[index] = modules[targetIndex];
  modules[targetIndex] = temp;

  try {
    await store.reorderModules(store.currentCourse.id, modules.map((m) => m.id));
    await loadCourse();
  } catch (err: any) {
    alert(err.message);
  }
};

// Lesson Actions
const openLessonModal = (moduleId: number, lesson: any = null) => {
  activeModuleIdForLesson.value = moduleId;
  if (lesson) {
    editingLessonId.value = lesson.id;
    lessonForm.value.title = lesson.title;
    lessonForm.value.content = lesson.content || '';
    lessonForm.value.estimated_duration = lesson.estimated_duration;
    lessonForm.value.is_required = lesson.is_required;
    lessonForm.value.video_url = lesson.video_url || '';
  } else {
    editingLessonId.value = null;
    lessonForm.value.title = '';
    lessonForm.value.content = '';
    lessonForm.value.estimated_duration = 15;
    lessonForm.value.is_required = true;
    lessonForm.value.video_url = '';
  }
  showLessonModal.value = true;
};

const saveLesson = async () => {
  try {
    if (editingLessonId.value) {
      await lessonsStore.updateLesson(editingLessonId.value, lessonForm.value);
    } else {
      await lessonsStore.createLesson({
        module_id: activeModuleIdForLesson.value!,
        ...lessonForm.value,
      });
    }
    showLessonModal.value = false;
    await loadCourse();
  } catch (err: any) {
    alert(err.message);
  }
};

const deleteLesson = async (lesson: any) => {
  if (confirm(`Are you sure you want to delete lesson "${lesson.title}"?`)) {
    try {
      await lessonsStore.deleteLesson(lesson.id);
      await loadCourse();
    } catch (err: any) {
      alert(err.message);
    }
  }
};

const moveLesson = async (module: any, index: number, direction: number) => {
  const targetIndex = index + direction;
  if (targetIndex < 0 || targetIndex >= module.lessons.length) return;

  const lessons = [...module.lessons];
  const temp = lessons[index];
  lessons[index] = lessons[targetIndex];
  lessons[targetIndex] = temp;

  try {
    await lessonsStore.reorderLessons(module.id, lessons.map((l) => l.id));
    await loadCourse();
  } catch (err: any) {
    alert(err.message);
  }
};

onMounted(() => {
  loadCourse();
});
</script>
