<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6 text-neutral-black">
    <!-- Breadcrumbs -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2 text-xs text-neutral-muted font-bold uppercase tracking-wider">
        <router-link to="/admin/academy/quizzes" class="hover:text-neutral-muted transition">Quizzes</router-link>
        <span>/</span>
        <span class="text-neutral-muted">Builder</span>
      </div>
      <router-link to="/admin/academy/quizzes" class="text-xs font-bold text-neutral-muted hover:text-white transition">
        ← Back to Quizzes
      </router-link>
    </div>

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-neutral-ivory pb-4">
      <div>
        <h1 class="text-3xl font-extrabold text-white">Quiz Builder</h1>
        <p class="text-xs text-neutral-muted mt-0.5">
          Assemble, reorder, and configure evaluation questions for: 
          <span class="text-secondary font-bold" v-if="currentQuiz">{{ currentQuiz.title }}</span>
          <span class="text-neutral-muted italic" v-else>Loading quiz...</span>
        </p>
      </div>

      <div class="flex gap-2">
        <button 
          v-if="currentQuiz"
          @click="openPreviewModal"
          class="px-4 py-2 text-xs font-bold rounded-lg bg-neutral-background hover:bg-neutral-background text-neutral-black border border-neutral-ivory/60"
        >
          👁️ Preview Quiz
        </button>
      </div>
    </div>

    <!-- Main Workspace -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Left: Quiz Questions (Curriculum Draft) -->
      <div class="lg:col-span-2 space-y-4">
        <div class="flex justify-between items-center">
          <h2 class="text-lg font-bold text-white">Quiz Questions ({{ quizQuestions.length }})</h2>
          <span class="text-xs text-neutral-muted font-semibold">Total: {{ totalPoints }} Points</span>
        </div>

        <div v-if="loading" class="flex justify-center py-20">
          <div class="h-8 w-8 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
        </div>

        <div v-else-if="quizQuestions.length === 0" class="p-12 text-center rounded-2xl bg-white border border-neutral-ivory">
          <p class="text-sm text-neutral-muted">No questions in this quiz. Add existing questions from the bank or create new ones.</p>
        </div>

        <div class="space-y-3" v-else>
          <div 
            v-for="(q, idx) in quizQuestions" 
            :key="q.id"
            class="flex items-center justify-between p-4 rounded-xl bg-white border border-neutral-ivory hover:border-neutral-ivory transition"
          >
            <div class="flex items-start gap-4">
              <span class="text-xs font-bold text-neutral-muted mt-0.5">Q{{ idx + 1 }}</span>
              <div>
                <p class="text-sm font-bold text-neutral-black">{{ q.question }}</p>
                <div class="flex items-center gap-2 mt-1.5 text-[10px] text-neutral-muted font-bold uppercase tracking-wider">
                  <span class="px-1.5 py-0.5 rounded border-neutral-ivory text-neutral-muted">{{ q.type?.replace('_', ' ') }}</span>
                  <span>•</span>
                  <span class="text-secondary">{{ q.points }} Points</span>
                  <span>•</span>
                  <span>Category: {{ q.category || 'General' }}</span>
                </div>
              </div>
            </div>

            <!-- Question Actions -->
            <div class="flex items-center gap-2">
              <button 
                @click="removeQuestion(q.id)"
                class="p-1.5 text-xs text-red-400 hover:text-red-350 transition"
                title="Remove from Quiz"
              >
                🗑️
              </button>
              <div class="flex flex-col text-[8px] gap-0.5 ml-2">
                <button @click="moveQuestion(idx, -1)" class="hover:text-secondary">▲</button>
                <button @click="moveQuestion(idx, 1)" class="hover:text-secondary">▼</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Toolbox & Question Bank -->
      <div class="space-y-6">
        <!-- Add New Question -->
        <div class="rounded-2xl bg-white border border-neutral-ivory p-5 space-y-4">
          <h3 class="text-sm font-bold text-white flex items-center gap-2">
            <span>➕</span> Add New Question
          </h3>
          <p class="text-xs text-neutral-muted">Create a new question and assign it directly to this evaluation.</p>
          <button 
            @click="openQuestionFormModal"
            class="w-full py-2 text-xs font-bold rounded-lg text-white bg-secondary hover:bg-secondary-light transition text-center"
          >
            Create Question
          </button>
        </div>

        <!-- Add Existing from Bank -->
        <div class="rounded-2xl bg-white border border-neutral-ivory p-5 space-y-4">
          <h3 class="text-sm font-bold text-white flex items-center gap-2">
            <span>📂</span> Bank Questions
          </h3>
          <p class="text-xs text-neutral-muted">Import questions created in other quizzes or general categories.</p>
          
          <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
            <div v-if="bankQuestions.length === 0" class="text-center text-xs text-neutral-muted py-4">
              No additional bank questions.
            </div>
            <div 
              v-else
              v-for="bq in bankQuestions" 
              :key="bq.id"
              class="p-3 rounded-lg border-neutral-ivory/60 border border-neutral-ivory flex justify-between items-center gap-3"
            >
              <div class="min-w-0">
                <p class="text-xs font-semibold text-neutral-black truncate">{{ bq.question }}</p>
                <p class="text-[9px] text-neutral-muted mt-0.5">{{ bq.type?.replace('_', ' ') }} • {{ bq.points }} pts</p>
              </div>
              <button 
                @click="importQuestion(bq)"
                class="px-2 py-1 text-[10px] font-bold text-secondary hover:bg-primary/5 border border-primary/15 rounded"
              >
                + Add
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quiz Preview Modal -->
    <div v-if="showPreviewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-background/80 backdrop-blur-sm">
      <div class="w-full max-w-2xl bg-white border border-neutral-ivory rounded-3xl p-6 shadow-2xl max-h-[85vh] overflow-y-auto text-neutral-black">
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-4">
          <h3 class="text-xl font-black text-white">👀 Quiz Simulator (Preview)</h3>
          <button @click="showPreviewModal = false" class="text-neutral-muted hover:text-white text-lg">✕</button>
        </div>

        <div class="space-y-6">
          <div v-for="(q, idx) in quizQuestions" :key="q.id" class="p-4 rounded-2xl border-neutral-ivory/50 border border-neutral-ivory space-y-3">
            <h4 class="text-sm font-bold text-white">Q{{ idx + 1 }}. {{ q.question }}</h4>
            
            <!-- MCQ Option List -->
            <div v-if="q.type === 'multiple_choice'" class="space-y-2">
              <label 
                v-for="(opt, optIdx) in q.options" 
                :key="optIdx"
                class="flex items-center gap-3 p-2.5 rounded-lg bg-white/40 border border-neutral-ivory/80 cursor-pointer hover:bg-neutral-background/20 transition text-xs"
              >
                <input type="radio" :name="`preview_q_${q.id}`" />
                <span>{{ opt }}</span>
              </label>
            </div>

            <!-- Multiple Select Option List -->
            <div v-else-if="q.type === 'multiple_select'" class="space-y-2">
              <label 
                v-for="(opt, optIdx) in q.options" 
                :key="optIdx"
                class="flex items-center gap-3 p-2.5 rounded-lg bg-white/40 border border-neutral-ivory/80 cursor-pointer hover:bg-neutral-background/20 transition text-xs"
              >
                <input type="checkbox" />
                <span>{{ opt }}</span>
              </label>
            </div>

            <!-- True False Option List -->
            <div v-else-if="q.type === 'true_false'" class="space-y-2">
              <label class="flex items-center gap-3 p-2.5 rounded-lg bg-white/40 border border-neutral-ivory/80 cursor-pointer hover:bg-neutral-background/20 transition text-xs">
                <input type="radio" :name="`preview_q_${q.id}`" />
                <span>True</span>
              </label>
              <label class="flex items-center gap-3 p-2.5 rounded-lg bg-white/40 border border-neutral-ivory/80 cursor-pointer hover:bg-neutral-background/20 transition text-xs">
                <input type="radio" :name="`preview_q_${q.id}`" />
                <span>False</span>
              </label>
            </div>

            <!-- Short Answer -->
            <div v-else-if="q.type === 'short_answer'" class="space-y-2">
              <input type="text" placeholder="Type your answer here..." class="w-full bg-white border border-neutral-ivory rounded-lg p-2.5 text-xs focus:outline-none" />
            </div>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-neutral-ivory flex justify-end">
          <button @click="showPreviewModal = false" class="px-5 py-2 text-xs font-bold rounded-xl border-neutral-ivory hover:bg-neutral-background text-neutral-muted">
            Close Preview
          </button>
        </div>
      </div>
    </div>

    <!-- Question Form Modal -->
    <div v-if="showQuestionFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-background/70 backdrop-blur-sm">
      <div class="w-full max-w-md bg-white border border-neutral-ivory rounded-2xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-white mb-4">Create Question</h3>
        <form @submit.prevent="createAndAddQuestion" class="space-y-4">
          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Question Content *</label>
            <input v-model="questionForm.question" type="text" required placeholder="Type the question..." class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Type *</label>
              <select v-model="questionForm.type" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none">
                <option value="multiple_choice">Multiple Choice</option>
                <option value="multiple_select">Multiple Select</option>
                <option value="true_false">True / False</option>
                <option value="short_answer">Short Answer</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted">Points *</label>
              <input v-model.number="questionForm.points" type="number" min="1" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none" />
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showQuestionFormModal = false" class="px-4 py-2 text-xs font-bold rounded-lg bg-neutral-background border border-neutral-ivory hover:bg-neutral-background text-neutral-muted transition">Cancel</button>
            <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg text-white bg-secondary hover:bg-secondary-light transition">Save & Add</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import client from '@/services/api';

const route = useRoute();

const loading = ref(false);
const currentQuiz = ref<any>(null);
const quizQuestions = ref<any[]>([]);
const bankQuestions = ref<any[]>([]);

// Previews
const showPreviewModal = ref(false);
const showQuestionFormModal = ref(false);

const questionForm = ref({
  question: '',
  type: 'multiple_choice',
  points: 1,
  options: ['Option 1', 'Option 2'],
  correct_answer: [0],
});

const totalPoints = computed(() => {
  return quizQuestions.value.reduce((sum, q) => sum + (q.points || 0), 0);
});

const loadQuizDetails = async () => {
  const quizId = route.query.quiz_id;
  if (!quizId) return;

  loading.value = true;
  try {
    const response = await client.get(`/admin/academy/questions?quiz_id=${quizId}`);
    if (response.data.success) {
      quizQuestions.value = response.data.questions || [];
      // Re-order locally
      quizQuestions.value.sort((a, b) => (a.order || 0) - (b.order || 0));
    }

    // Get course quizzes to identify the title
    const coursesResponse = await client.get('/admin/academy/courses');
    const courses = coursesResponse.data.courses || [];
    for (const c of courses) {
      const qResp = await client.get(`/academy/courses/${c.id}`);
      if (qResp.data.success && qResp.data.course.quizzes) {
        const found = qResp.data.course.quizzes.find((x: any) => String(x.id) === String(quizId));
        if (found) {
          currentQuiz.value = found;
          break;
        }
      }
    }
  } catch (e) {
    alert('Failed to load quiz details.');
  } finally {
    loading.value = false;
  }
};

const loadBankQuestions = async () => {
  try {
    const response = await client.get('/admin/academy/questions');
    if (response.data.success) {
      const allQuestions = response.data.questions || [];
      const quizId = route.query.quiz_id;
      // Exclude questions that are already in this quiz
      bankQuestions.value = allQuestions.filter((x: any) => String(x.quiz_id) !== String(quizId));
    }
  } catch (e) {
    // console.log(e);
  }
};

const removeQuestion = async (id: number) => {
  if (confirm('Are you sure you want to remove this question? This deletes it permanently.')) {
    try {
      await client.delete(`/admin/academy/questions/${id}`);
      await loadQuizDetails();
      await loadBankQuestions();
    } catch (e) {
      alert('Failed to delete question.');
    }
  }
};

const moveQuestion = async (index: number, direction: number) => {
  const targetIndex = index + direction;
  if (targetIndex < 0 || targetIndex >= quizQuestions.value.length) return;

  const questions = [...quizQuestions.value];
  const temp = questions[index];
  questions[index] = questions[targetIndex];
  questions[targetIndex] = temp;

  // Save ordering
  try {
    for (let i = 0; i < questions.length; i++) {
      await client.put(`/admin/academy/questions/${questions[i].id}`, {
        order: i + 1,
        quiz_id: route.query.quiz_id,
        type: questions[i].type,
        question: questions[i].question,
        points: questions[i].points,
      });
    }
    await loadQuizDetails();
  } catch (e) {
    alert('Failed to save reordered questions.');
  }
};

const openPreviewModal = () => {
  showPreviewModal.value = true;
};

const openQuestionFormModal = () => {
  questionForm.value = {
    question: '',
    type: 'multiple_choice',
    points: 1,
    options: ['Option 1', 'Option 2'],
    correct_answer: [0],
  };
  showQuestionFormModal.value = true;
};

const createAndAddQuestion = async () => {
  const quizId = route.query.quiz_id;
  if (!quizId) return;

  try {
    const response = await client.post('/admin/academy/questions', {
      quiz_id: quizId,
      ...questionForm.value,
    });
    if (response.data.success) {
      showQuestionFormModal.value = false;
      await loadQuizDetails();
      await loadBankQuestions();
    }
  } catch (e) {
    alert('Failed to create question.');
  }
};

const importQuestion = async (bq: any) => {
  const quizId = route.query.quiz_id;
  if (!quizId) return;

  // Simulate importing by cloning the question into this quiz
  try {
    const response = await client.post('/admin/academy/questions', {
      quiz_id: quizId,
      type: bq.type,
      question: bq.question,
      options: bq.options,
      correct_answer: bq.correct_answer,
      points: bq.points,
      category: bq.category,
      difficulty: bq.difficulty,
    });
    if (response.data.success) {
      await loadQuizDetails();
      await loadBankQuestions();
    }
  } catch (e) {
    alert('Failed to import question.');
  }
};

onMounted(() => {
  loadQuizDetails();
  loadBankQuestions();
});
</script>
