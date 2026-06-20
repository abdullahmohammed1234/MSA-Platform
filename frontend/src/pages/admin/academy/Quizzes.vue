<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6 text-neutral-black">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-primary">
          Quiz Management
        </h1>
        <p class="text-sm text-neutral-muted mt-1">Configure evaluation tests, passing scores, limits, and build questions.</p>
      </div>
      <div class="flex items-center gap-3">
        <router-link 
          to="/admin/academy/question-bank"
          class="px-4 py-2 text-xs font-semibold rounded-lg bg-neutral-background border border-neutral-ivory hover:bg-neutral-background text-neutral-black transition"
        >
          📂 Question Bank
        </router-link>
        <button 
          @click="openQuizModal()"
          class="px-4 py-2 text-sm font-semibold text-white bg-secondary hover:bg-secondary-light rounded-lg transition"
        >
          ➕ Create Quiz
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-20">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="quizzes.length === 0" class="p-12 text-center rounded-2xl bg-white border border-neutral-ivory">
      <p class="text-sm text-neutral-muted">No quizzes configured yet. Create a quiz to start assessing students.</p>
    </div>

    <!-- Quiz Table -->
    <div v-else class="overflow-x-auto rounded-2xl bg-white border border-neutral-ivory shadow-xl">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-neutral-ivory/80 text-xs font-bold text-neutral-muted uppercase tracking-wider bg-neutral-background/20">
            <th class="p-4">Quiz Title</th>
            <th class="p-4">Associated Course</th>
            <th class="p-4">Passing Score</th>
            <th class="p-4">Attempts</th>
            <th class="p-4">Time Limit</th>
            <th class="p-4">Status</th>
            <th class="p-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-ivory text-sm">
          <tr v-for="quiz in quizzes" :key="quiz.id" class="hover:bg-neutral-background/20 transition">
            <td class="p-4 font-bold text-white">{{ quiz.title }}</td>
            <td class="p-4 text-neutral-muted">{{ getCourseTitle(quiz.course_id) }}</td>
            <td class="p-4 text-secondary font-semibold">{{ quiz.passing_score }}%</td>
            <td class="p-4 text-neutral-muted">{{ quiz.attempt_limit ? `${quiz.attempt_limit} max` : 'Unlimited' }}</td>
            <td class="p-4 text-neutral-muted">{{ quiz.time_limit ? `${quiz.time_limit} min` : 'Unlimited' }}</td>
            <td class="p-4">
              <span :class="`text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ${quiz.status === 'published' ? 'bg-secondary/10 text-secondary border border-secondary/25' : 'bg-primary/10 text-primary border border-primary/25'}`">
                {{ quiz.status }}
              </span>
            </td>
            <td class="p-4 text-right space-x-2">
              <router-link 
                :to="`/admin/academy/quiz-builder?quiz_id=${quiz.id}`"
                class="px-2.5 py-1 text-xs font-semibold rounded bg-neutral-background hover:bg-neutral-background text-neutral-black border border-neutral-ivory/60"
              >
                🛠️ Builder
              </router-link>
              <button 
                @click="openQuizModal(quiz)"
                class="px-2 py-1 text-xs font-bold text-neutral-muted hover:text-white"
              >
                ✏️
              </button>
              <button 
                @click="deleteQuiz(quiz)"
                class="px-2 py-1 text-xs font-bold text-red-400 hover:text-red-300"
              >
                🗑️
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Quiz Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-background/70 backdrop-blur-sm">
      <div class="w-full max-w-md bg-white border border-neutral-ivory rounded-2xl p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-white mb-4">{{ editingQuizId ? 'Edit Quiz' : 'Create Quiz' }}</h3>
        <form @submit.prevent="saveQuiz" class="space-y-4">
          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Quiz Title *</label>
            <input v-model="form.title" type="text" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary" />
          </div>

          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Course Assignment *</label>
            <select v-model="form.course_id" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary">
              <option value="" disabled>Select course...</option>
              <option v-for="c in coursesStore.courses" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Passing Score (%) *</label>
              <input v-model.number="form.passing_score" type="number" min="0" max="100" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary" />
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Time Limit (Minutes)</label>
              <input v-model.number="form.time_limit" type="number" placeholder="Unlimited" class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Attempt Limit</label>
              <input v-model.number="form.attempt_limit" type="number" placeholder="Unlimited" class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary" />
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Status</label>
              <select v-model="form.status" class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
              </select>
            </div>
          </div>

          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Description</label>
            <textarea v-model="form.description" rows="3" class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary"></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showModal = false" class="px-4 py-2 text-xs font-bold rounded-lg bg-neutral-background border border-neutral-ivory hover:bg-neutral-background text-neutral-muted transition">Cancel</button>
            <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg text-white bg-secondary hover:bg-secondary-light transition">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useAdminQuizzesStore } from '@/stores/admin/academy/quizzes';
import { useAdminCoursesStore } from '@/stores/admin/academy/courses';
const store = useAdminQuizzesStore();
const coursesStore = useAdminCoursesStore();

const loading = ref(false);
const quizzes = computed(() => store.quizzes);
const showModal = ref(false);
const editingQuizId = ref<number | null>(null);

const form = ref({
  title: '',
  description: '',
  course_id: '' as string | number,
  passing_score: 70,
  time_limit: null as number | null,
  attempt_limit: null as number | null,
  status: 'draft' as 'draft' | 'published',
});

const loadQuizzes = async () => {
  loading.value = true;
  try {
    await Promise.all([
      store.fetchQuizzes(),
      coursesStore.fetchCourses({}, 1),
    ]);
  } catch (err: any) {
    alert(err.message || 'Failed to load quizzes.');
  } finally {
    loading.value = false;
  }
};

const getCourseTitle = (courseId: number) => {
  const c = coursesStore.courses.find((x) => x.id === courseId);
  return c ? c.title : `Course #${courseId}`;
};

const openQuizModal = (quiz: any = null) => {
  if (quiz) {
    editingQuizId.value = quiz.id;
    form.value.title = quiz.title;
    form.value.description = quiz.description || '';
    form.value.course_id = quiz.course_id;
    form.value.passing_score = quiz.passing_score;
    form.value.time_limit = quiz.time_limit;
    form.value.attempt_limit = quiz.attempt_limit;
    form.value.status = quiz.status;
  } else {
    editingQuizId.value = null;
    form.value.title = '';
    form.value.description = '';
    form.value.course_id = coursesStore.courses[0]?.id || '';
    form.value.passing_score = 70;
    form.value.time_limit = null;
    form.value.attempt_limit = null;
    form.value.status = 'draft';
  }
  showModal.value = true;
};

const saveQuiz = async () => {
  try {
    const payload = {
      ...form.value,
      course_id: Number(form.value.course_id),
      time_limit: form.value.time_limit !== null ? form.value.time_limit : undefined,
      attempt_limit: form.value.attempt_limit !== null ? form.value.attempt_limit : undefined
    };
    if (editingQuizId.value) {
      await store.updateQuiz(editingQuizId.value, payload);
    } else {
      await store.createQuiz(payload);
    }
    showModal.value = false;
    await loadQuizzes();
  } catch (err: any) {
    alert(err.message);
  }
};

const deleteQuiz = async (quiz: any) => {
  if (confirm(`Are you sure you want to delete quiz "${quiz.title}"?`)) {
    try {
      await store.deleteQuiz(quiz.id);
      await loadQuizzes();
    } catch (err: any) {
      alert(err.message);
    }
  }
};

onMounted(() => {
  loadQuizzes();
});
</script>
