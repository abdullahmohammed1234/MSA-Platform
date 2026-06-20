<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAchievementsStore } from '@/stores/academy/achievements';
import { useToastStore } from '@/components/feedback/toast';
import type { Achievement } from '@/types/certification';

const toast = useToastStore();
const achievementsStore = useAchievementsStore();

const showModal = ref(false);
const isEditing = ref(false);

const form = ref<{
  id?: number;
  title: string;
  slug: string;
  description: string;
  type: 'completion' | 'performance' | 'participation' | 'special_recognition';
  points: number;
  criteria_type: string;
  criteria_value: {
    threshold?: number;
  };
}>({
  title: '',
  slug: '',
  description: '',
  type: 'completion',
  points: 100,
  criteria_type: 'lessons_count',
  criteria_value: {
    threshold: 1
  }
});

onMounted(async () => {
  await achievementsStore.fetchAchievementsAdmin();
});

const generateSlug = () => {
  form.value.slug = form.value.title
    .toLowerCase()
    .trim()
    .replace(/[^\w ]+/g, '')
    .replace(/ +/g, '-');
};

const openCreate = () => {
  isEditing.value = false;
  form.value = {
    title: '',
    slug: '',
    description: '',
    type: 'completion',
    points: 100,
    criteria_type: 'lessons_count',
    criteria_value: {
      threshold: 1
    }
  };
  showModal.value = true;
};

const openEdit = (ach: Achievement) => {
  isEditing.value = true;
  form.value = {
    id: ach.id,
    title: ach.title,
    slug: ach.slug,
    description: ach.description || '',
    type: ach.type,
    points: ach.points,
    criteria_type: ach.criteria_type,
    criteria_value: ach.criteria_value || { threshold: 1 }
  };
  showModal.value = true;
};

const handleSubmit = async () => {
  const f = form.value;
  if (!f.title.trim() || !f.slug.trim()) {
    toast.warning('Please specify achievement title and slug.');
    return;
  }

  const payload: Partial<Achievement> = {
    title: f.title.trim(),
    slug: f.slug.trim(),
    description: f.description.trim() || null,
    type: f.type,
    points: Number(f.points),
    criteria_type: f.criteria_type,
    criteria_value: f.criteria_value
  };

  try {
    if (isEditing.value && f.id) {
      await achievementsStore.updateAchievement(f.id, payload);
      toast.success('Achievement definition updated successfully.');
    } else {
      await achievementsStore.createAchievement(payload);
      toast.success('Achievement definition created successfully.');
    }
    showModal.value = false;
    await achievementsStore.fetchAchievementsAdmin();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to save achievement.');
  }
};

const handleDelete = async (achId: number, title: string) => {
  if (!confirm(`Delete achievement "${title}"? This cannot be undone.`)) return;
  try {
    await achievementsStore.deleteAchievement(achId);
    toast.success('Achievement deleted successfully.');
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to delete achievement.');
  }
};
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Academy Achievements</h1>
        <p class="text-sm text-neutral-muted mt-1">Configure gamified rewards and configure the points engine triggers.</p>
      </div>

      <button
        @click="openCreate"
        class="bg-primary hover:bg-primary/95 text-white font-bold text-xs px-4 py-2.5 rounded-xl cursor-pointer shadow-soft"
      >
        Add Achievement
      </button>
    </div>

    <!-- Achievements Table -->
    <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left">
          <thead>
            <tr class="bg-neutral-background border-b border-neutral-ivory text-[10px] font-bold uppercase tracking-wider text-neutral-muted">
              <th class="px-6 py-3">Title / Slug</th>
              <th class="px-6 py-3">Description</th>
              <th class="px-6 py-3">Type</th>
              <th class="px-6 py-3">Points</th>
              <th class="px-6 py-3">Trigger Condition</th>
              <th class="px-6 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory/50 text-xs">
            <tr v-for="ach in achievementsStore.adminAchievements" :key="ach.id" class="hover:bg-neutral-background/30 transition-colors">
              <td class="px-6 py-4">
                <div class="font-bold text-neutral-black">{{ ach.title }}</div>
                <div class="text-[9px] font-mono text-neutral-muted mt-0.5">{{ ach.slug }}</div>
              </td>
              <td class="px-6 py-4 text-neutral-muted leading-relaxed max-w-xs truncate">
                {{ ach.description }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="bg-primary/10 text-primary text-[10px] font-semibold px-2.5 py-0.5 rounded-full capitalize">
                  {{ ach.type }}
                </span>
              </td>
              <td class="px-6 py-4 font-mono font-bold text-accent-gold whitespace-nowrap">
                +{{ ach.points }} SP
              </td>
              <td class="px-6 py-4 text-neutral-muted">
                <div class="font-mono text-[10px] font-bold text-neutral-black capitalize">
                  {{ ach.criteria_type.replace('_', ' ') }}
                </div>
                <div class="text-[9px] mt-0.5 font-light" v-if="ach.criteria_value">
                  Threshold: {{ ach.criteria_value.threshold }}
                </div>
              </td>
              <td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                <button
                  @click="openEdit(ach)"
                  class="text-primary hover:underline font-bold cursor-pointer"
                >
                  Edit
                </button>
                <button
                  @click="handleDelete(ach.id, ach.title)"
                  class="text-red-500 hover:underline font-bold cursor-pointer"
                >
                  Delete
                </button>
              </td>
            </tr>

            <tr v-if="achievementsStore.adminAchievements.length === 0">
              <td colspan="6" class="px-6 py-12 text-center text-neutral-muted italic">
                No achievements defined.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Achievement Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-black/40 backdrop-blur-sm p-4"
    >
      <div class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-premium space-y-6">
        <div>
          <h3 class="text-lg font-display font-semibold text-primary">
            {{ isEditing ? 'Edit Achievement' : 'Define New Achievement' }}
          </h3>
          <p class="text-xs text-neutral-muted mt-0.5 font-light">
            Set up the title, criteria targets, and point score reward values.
          </p>
        </div>

        <div class="space-y-4">
          <!-- Title -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Title</label>
            <input
              type="text"
              v-model="form.title"
              @input="generateSlug"
              placeholder="e.g. First Step Completed"
              class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40"
            />
          </div>

          <!-- Slug -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Slug (auto-generated)</label>
            <input
              type="text"
              v-model="form.slug"
              placeholder="e.g. first-step-completed"
              class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 font-mono"
            />
          </div>

          <!-- Description -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Description</label>
            <textarea
              v-model="form.description"
              placeholder="Provide a motivating description of this accomplishment..."
              rows="3"
              class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 resize-none"
            ></textarea>
          </div>

          <!-- Category Type & Points -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Category Type</label>
              <select
                v-model="form.type"
                class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 cursor-pointer"
              >
                <option value="completion">Completion</option>
                <option value="performance">Performance</option>
                <option value="participation">Participation</option>
                <option value="special_recognition">Special Recognition</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Scholar Points</label>
              <input
                type="number"
                v-model="form.points"
                min="0"
                class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 font-mono"
              />
            </div>
          </div>

          <!-- Criteria Triggers -->
          <div class="grid grid-cols-2 gap-4 border-t border-neutral-ivory/60 pt-4">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Criteria Type</label>
              <select
                v-model="form.criteria_type"
                class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 cursor-pointer"
              >
                <option value="lessons_count">Completed Lessons</option>
                <option value="courses_count">Completed Courses</option>
                <option value="quizzes_count">Quizzes Passed</option>
                <option value="score_reached">Score Threshold</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Target Count</label>
              <input
                type="number"
                v-model="form.criteria_value.threshold"
                min="1"
                class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 font-mono"
              />
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            @click="showModal = false"
            class="px-4 py-2 border border-neutral-ivory text-neutral-muted text-xs rounded-xl cursor-pointer hover:bg-neutral-background transition-colors"
          >
            Cancel
          </button>
          <button
            @click="handleSubmit"
            class="px-5 py-2 bg-primary text-white font-bold text-xs rounded-xl cursor-pointer hover:bg-primary/95 shadow-soft transition-colors"
          >
            {{ isEditing ? 'Save Changes' : 'Create Achievement' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
