<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useBadgesStore } from '@/stores/academy/badges';
import { useToastStore } from '@/components/feedback/toast';
import type { Badge } from '@/types/certification';

const toast = useToastStore();
const badgesStore = useBadgesStore();

const showModal = ref(false);
const isEditing = ref(false);

const form = ref<{
  id?: number;
  name: string;
  slug: string;
  description: string;
  image_path: string;
  criteria_type: string;
  criteria_value: {
    threshold?: number;
  };
}>({
  name: '',
  slug: '',
  description: '',
  image_path: '/assets/badges/badge-custom.svg',
  criteria_type: 'first_course_completed',
  criteria_value: {}
});

onMounted(async () => {
  await badgesStore.fetchBadgesAdmin();
});

const generateSlug = () => {
  form.value.slug = form.value.name
    .toLowerCase()
    .trim()
    .replace(/[^\w ]+/g, '')
    .replace(/ +/g, '-');
};

const openCreate = () => {
  isEditing.value = false;
  form.value = {
    name: '',
    slug: '',
    description: '',
    image_path: '/assets/badges/badge-custom.svg',
    criteria_type: 'first_course_completed',
    criteria_value: {}
  };
  showModal.value = true;
};

const openEdit = (badge: Badge) => {
  isEditing.value = true;
  form.value = {
    id: badge.id,
    name: badge.name,
    slug: badge.slug,
    description: badge.description || '',
    image_path: badge.image_path || '/assets/badges/badge-custom.svg',
    criteria_type: badge.criteria_type,
    criteria_value: badge.criteria_value || {}
  };
  showModal.value = true;
};

const handleSubmit = async () => {
  const f = form.value;
  if (!f.name.trim() || !f.slug.trim()) {
    toast.warning('Please specify badge name and slug.');
    return;
  }

  const payload: Partial<Badge> = {
    name: f.name.trim(),
    slug: f.slug.trim(),
    description: f.description.trim() || null,
    image_path: f.image_path.trim(),
    criteria_type: f.criteria_type,
    criteria_value: f.criteria_value
  };

  try {
    if (isEditing.value && f.id) {
      await badgesStore.updateBadge(f.id, payload);
      toast.success('Badge definition updated successfully.');
    } else {
      await badgesStore.createBadge(payload);
      toast.success('Badge definition created successfully.');
    }
    showModal.value = false;
    await badgesStore.fetchBadgesAdmin();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to save badge.');
  }
};

const handleDelete = async (badgeId: number, name: string) => {
  if (!confirm(`Delete badge "${name}"? This cannot be undone.`)) return;
  try {
    await badgesStore.deleteBadge(badgeId);
    toast.success('Badge deleted successfully.');
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to delete badge.');
  }
};
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Academy Badges</h1>
        <p class="text-sm text-neutral-muted mt-1">Configure honor badges and trigger rules for outstanding achievements.</p>
      </div>

      <button
        @click="openCreate"
        class="bg-primary hover:bg-primary/95 text-white font-bold text-xs px-4 py-2.5 rounded-xl cursor-pointer shadow-soft"
      >
        Add Honor Badge
      </button>
    </div>

    <!-- Badges List Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="badge in badgesStore.adminBadges"
        :key="badge.id"
        class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft hover:shadow-premium transition-all duration-300 flex flex-col justify-between"
      >
        <div class="space-y-4">
          <div class="flex items-center gap-4">
            <!-- Simulated Badge Visual representation -->
            <div class="h-14 w-14 rounded-full bg-gradient-to-tr from-secondary to-primary p-0.5 flex items-center justify-center shadow">
              <div class="h-full w-full rounded-full bg-white flex items-center justify-center text-primary font-bold text-xs font-display">
                Badge
              </div>
            </div>
            <div>
              <h3 class="font-display font-semibold text-base text-primary leading-snug">{{ badge.name }}</h3>
              <div class="text-[9px] font-mono text-neutral-muted mt-0.5">{{ badge.slug }}</div>
            </div>
          </div>

          <p class="text-xs text-neutral-muted leading-relaxed font-light">
            {{ badge.description }}
          </p>

          <div class="bg-neutral-background p-3 rounded-xl border border-neutral-ivory/60 text-[10px] font-mono space-y-1">
            <div class="flex justify-between">
              <span class="text-neutral-muted">Criteria:</span>
              <span class="text-neutral-black font-bold capitalize">{{ badge.criteria_type.replace('_', ' ') }}</span>
            </div>
            <div class="flex justify-between" v-if="badge.criteria_value && badge.criteria_value.threshold">
              <span class="text-neutral-muted">Threshold:</span>
              <span class="text-neutral-black font-bold">{{ badge.criteria_value.threshold }}</span>
            </div>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-neutral-ivory/60 flex justify-end gap-3 text-xs font-bold">
          <button
            @click="openEdit(badge)"
            class="text-primary hover:underline cursor-pointer"
          >
            Edit
          </button>
          <button
            @click="handleDelete(badge.id, badge.name)"
            class="text-red-500 hover:underline cursor-pointer"
          >
            Delete
          </button>
        </div>
      </div>

      <div v-if="badgesStore.adminBadges.length === 0" class="col-span-full py-12 text-center text-neutral-muted italic bg-white border border-neutral-ivory rounded-3xl shadow-soft">
        No badges defined. Create one to reward study milestones.
      </div>
    </div>

    <!-- Create/Edit Badge Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-black/40 backdrop-blur-sm p-4"
    >
      <div class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-premium space-y-6">
        <div>
          <h3 class="text-lg font-display font-semibold text-primary">
            {{ isEditing ? 'Edit Badge Definition' : 'Create New Honor Badge' }}
          </h3>
          <p class="text-xs text-neutral-muted mt-0.5 font-light">
            Define names, custom slugs, asset links, and active triggers.
          </p>
        </div>

        <div class="space-y-4">
          <!-- Name -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Badge Name</label>
            <input
              type="text"
              v-model="form.name"
              @input="generateSlug"
              placeholder="e.g. Perfect Quiz Score"
              class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40"
            />
          </div>

          <!-- Slug -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Slug (auto-generated)</label>
            <input
              type="text"
              v-model="form.slug"
              placeholder="e.g. perfect-quiz-score"
              class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 font-mono"
            />
          </div>

          <!-- Description -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Description</label>
            <textarea
              v-model="form.description"
              placeholder="Reward text describing how to earn this badge..."
              rows="3"
              class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 resize-none"
            ></textarea>
          </div>

          <!-- Image Asset Path -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Image Asset Path</label>
            <input
              type="text"
              v-model="form.image_path"
              placeholder="/assets/badges/..."
              class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 font-mono text-xs"
            />
          </div>

          <!-- Criteria Trigger configuration -->
          <div class="grid grid-cols-2 gap-4 border-t border-neutral-ivory/60 pt-4">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Criteria Type</label>
              <select
                v-model="form.criteria_type"
                class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 cursor-pointer text-xs"
              >
                <option value="first_course_completed">First Course Completion</option>
                <option value="quiz_master">Quiz Master (Quizzes Completed)</option>
                <option value="perfect_score">Perfect Quiz Score (100%)</option>
                <option value="consistent_learner">Lessons Completed</option>
                <option value="volunteer_graduate">Volunteer Learning Path Complete</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1">Target Threshold</label>
              <input
                type="number"
                v-model="form.criteria_value.threshold"
                placeholder="e.g. 5"
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
            {{ isEditing ? 'Save Changes' : 'Create Badge' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
