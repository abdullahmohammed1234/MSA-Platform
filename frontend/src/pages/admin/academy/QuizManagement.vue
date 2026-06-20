<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useAdminQuizzesStore } from '@/stores/admin/academy/quizzes';
import { useAdminCoursesStore } from '@/stores/admin/academy/courses';
import { FileQuestion, RefreshCw, Trash2, PlusCircle } from 'lucide-vue-next';

const quizzesStore = useAdminQuizzesStore();
const coursesStore = useAdminCoursesStore();
const search = ref('');

const loadData = async () => {
  await Promise.all([
    quizzesStore.fetchQuestions(),
    coursesStore.fetchCourses(),
  ]);
};

const filteredQuestions = () => quizzesStore.questions.filter((question) =>
  question.question.toLowerCase().includes(search.value.toLowerCase())
);

const deleteQuestion = async (id: number) => {
  if (!window.confirm('Delete this question?')) return;
  await quizzesStore.deleteQuestion(id);
  await quizzesStore.fetchQuestions();
};

onMounted(loadData);
</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto p-1 text-neutral-black">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div>
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary/70">Academy Admin</p>
        <h1 class="text-3xl font-display font-extrabold text-primary">Quiz Management</h1>
        <p class="text-sm text-neutral-muted mt-1">Manage question banks and connect quizzes to academy courses.</p>
      </div>
      <button @click="loadData" class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory bg-white px-4 py-2.5 text-sm hover:bg-neutral-background transition"><RefreshCw class="h-4 w-4" /> Refresh</button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"><p class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-muted">Questions</p><p class="mt-2 text-3xl font-display font-bold text-primary">{{ quizzesStore.questions.length }}</p></div>
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"><p class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-muted">Courses</p><p class="mt-2 text-3xl font-display font-bold text-primary">{{ coursesStore.courses.length }}</p></div>
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"><p class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-muted">Draft quizzes</p><p class="mt-2 text-3xl font-display font-bold text-primary">{{ quizzesStore.questions.filter((q) => q.quiz?.title).length }}</p></div>
      <router-link to="/admin/academy/quiz-builder" class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft hover:bg-neutral-background transition"><p class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-muted">Builder</p><div class="mt-2 text-lg font-semibold text-primary flex items-center gap-2"><PlusCircle class="h-4 w-4" /> Open builder</div></router-link>
    </div>

    <section class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <h2 class="text-lg font-display font-semibold flex items-center gap-2"><FileQuestion class="h-5 w-5 text-primary" /> Question bank</h2>
        <input v-model="search" type="search" placeholder="Search questions" class="w-full md:w-80 rounded-xl border border-neutral-ivory bg-neutral-background px-3 py-2.5 text-sm outline-none focus:border-primary/30" />
      </div>

      <div class="mt-4 space-y-3">
        <article v-for="question in filteredQuestions()" :key="question.id" class="rounded-2xl border border-neutral-ivory p-4">
          <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-wide text-neutral-muted">{{ question.type }}</p>
              <h3 class="mt-1 font-display text-base font-semibold text-neutral-black">{{ question.question }}</h3>
              <p class="mt-1 text-sm text-neutral-muted">{{ question.quiz?.title || 'Unassigned quiz' }}</p>
            </div>
            <button @click="deleteQuestion(question.id)" class="inline-flex items-center gap-1 rounded-xl border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50 transition"><Trash2 class="h-4 w-4" /> Delete</button>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>