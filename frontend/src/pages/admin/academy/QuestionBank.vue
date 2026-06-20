<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6 text-neutral-black">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-primary">
          Question Bank
        </h1>
        <p class="text-sm text-neutral-muted mt-1">Manage all evaluation questions, categories, and tags in a centralized bank.</p>
      </div>
      <button 
        @click="openQuestionModal()"
        class="px-4 py-2 text-sm font-semibold text-white bg-secondary hover:bg-secondary-light rounded-lg transition shadow-lg shadow-soft"
      >
        ➕ Add Question
      </button>
    </div>

    <!-- Filters -->
    <div class="p-4 rounded-xl bg-white border border-neutral-ivory flex flex-col md:flex-row gap-4 items-center justify-between">
      <div class="w-full md:w-80 relative">
        <input 
          v-model="filters.search"
          @input="onSearch"
          type="text"
          placeholder="Search question content..."
          class="w-full bg-white border border-neutral-ivory rounded-lg py-2 px-3 pl-9 text-neutral-black placeholder-neutral-gray text-sm focus:outline-none focus:border-primary transition"
        />
        <span class="absolute left-3 top-2.5 text-neutral-muted text-sm">🔍</span>
      </div>

      <div class="flex flex-wrap gap-3 w-full md:w-auto">
        <select v-model="filters.type" @change="onFilter" class="bg-neutral-background border border-neutral-ivory text-neutral-black text-sm rounded-lg p-2 focus:outline-none focus:border-primary">
          <option value="">All Types</option>
          <option value="multiple_choice">Multiple Choice</option>
          <option value="multiple_select">Multiple Select</option>
          <option value="true_false">True / False</option>
          <option value="short_answer">Short Answer</option>
        </select>

        <select v-model="filters.difficulty" @change="onFilter" class="bg-neutral-background border border-neutral-ivory text-neutral-black text-sm rounded-lg p-2 focus:outline-none focus:border-primary">
          <option value="">All Difficulties</option>
          <option value="easy">Easy</option>
          <option value="medium">Medium</option>
          <option value="hard">Hard</option>
        </select>

        <select v-model="filters.category" @change="onFilter" class="bg-neutral-background border border-neutral-ivory text-neutral-black text-sm rounded-lg p-2 focus:outline-none focus:border-primary">
          <option value="">All Categories</option>
          <option value="Fiqh">Fiqh</option>
          <option value="Aqeedah">Aqeedah</option>
          <option value="Seerah">Seerah</option>
          <option value="Methodology">Methodology</option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <div v-if="store.loading" class="flex justify-center py-20">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <div v-else-if="store.questions.length === 0" class="p-12 text-center rounded-2xl bg-white border border-neutral-ivory">
      <p class="text-sm text-neutral-muted">No questions found matching the selected filters.</p>
    </div>

    <div v-else class="space-y-4">
      <div class="overflow-x-auto rounded-2xl bg-white border border-neutral-ivory shadow-xl">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-neutral-ivory/80 text-xs font-bold text-neutral-muted uppercase tracking-wider bg-neutral-background/20">
              <th class="p-4">Question Text</th>
              <th class="p-4">Type</th>
              <th class="p-4">Category</th>
              <th class="p-4">Difficulty</th>
              <th class="p-4">Points</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory text-sm">
            <tr v-for="q in store.questions" :key="q.id" class="hover:bg-neutral-background/20 transition">
              <td class="p-4 font-semibold text-white max-w-md truncate">{{ q.question }}</td>
              <td class="p-4 capitalize text-neutral-muted">{{ q.type?.replace('_', ' ') }}</td>
              <td class="p-4 text-neutral-muted">{{ q.category || 'N/A' }}</td>
              <td class="p-4 text-neutral-muted capitalize">
                <span :class="`px-2 py-0.5 rounded-full text-[10px] font-bold ${q.difficulty === 'hard' ? 'bg-red-500/10 text-red-400' : q.difficulty === 'medium' ? 'bg-accent-gold/20 text-amber-400' : 'bg-secondary/10 text-secondary'}`">
                  {{ q.difficulty || 'Easy' }}
                </span>
              </td>
              <td class="p-4 text-secondary font-bold">{{ q.points }} pts</td>
              <td class="p-4 text-right space-x-2">
                <button @click="openQuestionModal(q)" class="px-2.5 py-1 text-xs font-semibold rounded bg-neutral-background hover:bg-neutral-background text-neutral-black">✏️ Edit</button>
                <button @click="deleteQuestion(q)" class="px-2.5 py-1 text-xs font-semibold rounded bg-red-950/20 border border-red-900/30 text-red-400 hover:bg-red-950/40">🗑️ Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between mt-4 text-xs text-neutral-muted font-semibold">
        <span>Showing page {{ store.pagination.currentPage }} of {{ store.pagination.lastPage }}</span>
        <div class="flex gap-2">
          <button 
            @click="onPageChange(store.pagination.currentPage - 1)" 
            :disabled="store.pagination.currentPage === 1"
            class="px-3 py-1.5 rounded-lg bg-neutral-background hover:bg-neutral-background disabled:opacity-50"
          >
            Prev
          </button>
          <button 
            @click="onPageChange(store.pagination.currentPage + 1)" 
            :disabled="store.pagination.currentPage === store.pagination.lastPage"
            class="px-3 py-1.5 rounded-lg bg-neutral-background hover:bg-neutral-background disabled:opacity-50"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Question Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-background/70 backdrop-blur-sm">
      <div class="w-full max-w-lg bg-white border border-neutral-ivory rounded-2xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-white mb-4">{{ editingQuestionId ? 'Edit Question' : 'Create Question' }}</h3>
        <form @submit.prevent="saveQuestion" class="space-y-4">
          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Associated Quiz *</label>
            <select v-model="form.quiz_id" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary">
              <option value="" disabled>Select quiz...</option>
              <option v-for="qz in quizzes" :key="qz.id" :value="qz.id">{{ qz.title }}</option>
            </select>
          </div>

          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Question Content *</label>
            <input v-model="form.question" type="text" required placeholder="Type the question question..." class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Question Type *</label>
              <select v-model="form.type" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary">
                <option value="multiple_choice">Multiple Choice</option>
                <option value="multiple_select">Multiple Select</option>
                <option value="true_false">True / False</option>
                <option value="short_answer">Short Answer</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Points *</label>
              <input v-model.number="form.points" type="number" min="1" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Category</label>
              <select v-model="form.category" class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary">
                <option value="Fiqh">Fiqh</option>
                <option value="Aqeedah">Aqeedah</option>
                <option value="Seerah">Seerah</option>
                <option value="Methodology">Methodology</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Difficulty</label>
              <select v-model="form.difficulty" class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary">
                <option value="easy">Easy</option>
                <option value="medium">Medium</option>
                <option value="hard">Hard</option>
              </select>
            </div>
          </div>

          <!-- Options Editor for Multiple Choice/Select -->
          <div v-if="form.type === 'multiple_choice' || form.type === 'multiple_select'" class="space-y-2 border-t border-neutral-ivory/80 pt-3">
            <label class="text-xs font-bold text-neutral-muted">Answer Options</label>
            <div v-for="(_, idx) in form.options" :key="idx" class="flex gap-2 items-center">
              <input type="checkbox" v-if="form.type === 'multiple_select'" :checked="isOptionCorrect(idx)" @change="toggleCorrectSelect(idx)" />
              <input type="radio" v-else name="correct_radio" :checked="isOptionCorrect(idx)" @change="setCorrectRadio(idx)" />
              <input v-model="form.options[idx]" type="text" placeholder="Option text..." required class="flex-1 border-neutral-ivory border border-neutral-ivory rounded p-1.5 text-xs text-neutral-black" />
              <button type="button" @click="removeOption(idx)" class="text-red-400 text-xs">🗑️</button>
            </div>
            <button type="button" @click="addOption" class="text-xs text-secondary font-semibold hover:underline">+ Add Option</button>
          </div>

          <!-- True/False Editor -->
          <div v-else-if="form.type === 'true_false'" class="space-y-2 border-t border-neutral-ivory/80 pt-3">
            <label class="text-xs font-bold text-neutral-muted">Correct Answer</label>
            <select v-model="form.correct_answer[0]" class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm">
              <option value="true">True</option>
              <option value="false">False</option>
            </select>
          </div>

          <!-- Short Answer Editor -->
          <div v-else-if="form.type === 'short_answer'" class="space-y-2 border-t border-neutral-ivory/80 pt-3">
            <label class="text-xs font-bold text-neutral-muted">Correct Keywords (Comma separated)</label>
            <input v-model="shortAnswerText" type="text" placeholder="e.g. key, answers, phrases" class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none" />
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
import { ref, onMounted, watch } from 'vue';
import { useAdminQuizzesStore } from '@/stores/admin/academy/quizzes';

const store = useAdminQuizzesStore();

const filters = ref({
  search: '',
  type: '',
  difficulty: '',
  category: '',
});

const quizzes = ref<any[]>([]);
const showModal = ref(false);
const editingQuestionId = ref<number | null>(null);

const form = ref({
  quiz_id: '' as string | number,
  type: 'multiple_choice' as 'multiple_choice' | 'multiple_select' | 'true_false' | 'short_answer',
  question: '',
  options: [] as string[],
  correct_answer: [] as any[],
  points: 1,
  category: 'Fiqh',
  difficulty: 'easy',
});

const shortAnswerText = ref('');

const loadQuizzes = async () => {
  try {
    await store.fetchQuizzes();
    quizzes.value = store.quizzes;
  } catch (e) {
    // Quiz dropdown is optional; question bank still loads without it.
  }
};

const onSearch = () => {
  store.fetchQuestions(filters.value, 1);
};

const onFilter = () => {
  store.fetchQuestions(filters.value, 1);
};

const onPageChange = (page: number) => {
  store.fetchQuestions(filters.value, page);
};

const openQuestionModal = (question: any = null) => {
  loadQuizzes();
  if (question) {
    editingQuestionId.value = question.id;
    form.value.quiz_id = question.quiz_id;
    form.value.type = question.type;
    form.value.question = question.question;
    form.value.options = question.options ? [...question.options] : [];
    form.value.correct_answer = question.correct_answer ? [...question.correct_answer] : [];
    form.value.points = question.points;
    form.value.category = question.category || 'Fiqh';
    form.value.difficulty = question.difficulty || 'easy';
    
    if (question.type === 'short_answer') {
      shortAnswerText.value = form.value.correct_answer.join(', ');
    }
  } else {
    editingQuestionId.value = null;
    form.value.quiz_id = quizzes.value[0]?.id || '';
    form.value.type = 'multiple_choice';
    form.value.question = '';
    form.value.options = ['Option 1', 'Option 2'];
    form.value.correct_answer = [0];
    form.value.points = 1;
    form.value.category = 'Fiqh';
    form.value.difficulty = 'easy';
    shortAnswerText.value = '';
  }
  showModal.value = true;
};

const isOptionCorrect = (idx: number) => {
  return form.value.correct_answer.includes(idx);
};

const toggleCorrectSelect = (idx: number) => {
  const cArr = [...form.value.correct_answer];
  const found = cArr.indexOf(idx);
  if (found === -1) {
    cArr.push(idx);
  } else {
    cArr.splice(found, 1);
  }
  form.value.correct_answer = cArr;
};

const setCorrectRadio = (idx: number) => {
  form.value.correct_answer = [idx];
};

const addOption = () => {
  form.value.options.push(`Option ${form.value.options.length + 1}`);
};

const removeOption = (idx: number) => {
  form.value.options.splice(idx, 1);
  form.value.correct_answer = form.value.correct_answer
    .map((v) => (v > idx ? v - 1 : v))
    .filter((v) => v !== idx && v < form.value.options.length);
};

watch(() => form.value.type, (newType) => {
  if (newType === 'true_false') {
    form.value.options = ['True', 'False'];
    form.value.correct_answer = ['true'];
  } else if (newType === 'short_answer') {
    form.value.options = [];
    form.value.correct_answer = [];
  } else if (form.value.options.length === 0) {
    form.value.options = ['Option 1', 'Option 2'];
    form.value.correct_answer = [0];
  }
});

const saveQuestion = async () => {
  try {
    const payload = {
      ...form.value,
      quiz_id: Number(form.value.quiz_id)
    };
    if (payload.type === 'short_answer') {
      payload.correct_answer = shortAnswerText.value.split(',').map((x) => x.trim()).filter((x) => x);
    }
    if (editingQuestionId.value) {
      await store.updateQuestion(editingQuestionId.value, payload);
    } else {
      await store.createQuestion(payload);
    }
    showModal.value = false;
    store.fetchQuestions(filters.value, store.pagination.currentPage);
  } catch (err: any) {
    alert(err.message);
  }
};

const deleteQuestion = async (question: any) => {
  if (confirm('Delete this question from the bank?')) {
    try {
      await store.deleteQuestion(question.id);
      store.fetchQuestions(filters.value, store.pagination.currentPage);
    } catch (err: any) {
      alert(err.message);
    }
  }
};

onMounted(() => {
  store.fetchQuestions(filters.value, 1);
  loadQuizzes();
});
</script>
