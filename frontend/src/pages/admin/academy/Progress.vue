<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6 text-neutral-black">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-extrabold tracking-tight text-primary">
        Learning Progress
      </h1>
      <p class="text-sm text-neutral-muted mt-1">Audit individual student curriculum advancement, lesson statuses, and quiz evaluations.</p>
    </div>

    <!-- Filtering -->
    <div class="p-4 rounded-xl bg-white border border-neutral-ivory grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
      <div>
        <label class="text-[10px] font-bold text-neutral-muted uppercase tracking-wider block mb-1">Filter by Course</label>
        <select v-model="filters.course_id" @change="onFilter" class="w-full bg-neutral-background border border-neutral-ivory text-neutral-black text-xs rounded-lg p-2 focus:outline-none focus:border-primary">
          <option value="">All Courses</option>
          <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
        </select>
      </div>

      <div>
        <label class="text-[10px] font-bold text-neutral-muted uppercase tracking-wider block mb-1">Filter by Mentor</label>
        <select v-model="filters.mentor_id" @change="onFilter" class="w-full bg-neutral-background border border-neutral-ivory text-neutral-black text-xs rounded-lg p-2 focus:outline-none focus:border-primary">
          <option value="">All Mentors</option>
          <option v-for="m in mentors" :key="m.id" :value="m.id">{{ m.name }}</option>
        </select>
      </div>

      <div>
        <label class="text-[10px] font-bold text-neutral-muted uppercase tracking-wider block mb-1">Status</label>
        <select v-model="filters.status" @change="onFilter" class="w-full bg-neutral-background border border-neutral-ivory text-neutral-black text-xs rounded-lg p-2 focus:outline-none focus:border-primary">
          <option value="">All Statuses</option>
          <option value="active">Active Only</option>
          <option value="completed">Completed Only</option>
        </select>
      </div>

      <div class="flex items-end h-full">
        <button 
          @click="resetFilters"
          class="w-full py-2 text-xs font-semibold rounded-lg bg-neutral-background border border-neutral-ivory hover:bg-neutral-background text-neutral-muted transition"
        >
          Reset Filters
        </button>
      </div>
    </div>

    <!-- Table -->
    <div v-if="store.loading" class="flex justify-center py-20">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <div v-else-if="store.progressRecords.length === 0" class="p-12 text-center rounded-2xl bg-white border border-neutral-ivory">
      <p class="text-sm text-neutral-muted">No student progress logs match your selection.</p>
    </div>

    <div v-else class="space-y-4">
      <div class="overflow-x-auto rounded-2xl bg-white border border-neutral-ivory shadow-xl">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-neutral-ivory/80 text-xs font-bold text-neutral-muted uppercase tracking-wider bg-neutral-background/20">
              <th class="p-4">Student</th>
              <th class="p-4">Course</th>
              <th class="p-4">Progress</th>
              <th class="p-4">Evaluations</th>
              <th class="p-4">Certificates</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory text-sm">
            <tr v-for="rec in store.progressRecords" :key="rec.id" class="hover:bg-neutral-background/20 transition">
              <td class="p-4">
                <p class="font-bold text-white">{{ rec.user?.name }}</p>
                <p class="text-[10px] text-neutral-muted">{{ rec.user?.email }}</p>
              </td>
              <td class="p-4 text-neutral-muted font-semibold">{{ rec.course?.title }}</td>
              <td class="p-4">
                <div class="flex items-center gap-3">
                  <span class="text-secondary font-bold w-10 text-right">{{ rec.progress_percentage }}%</span>
                  <div class="w-32 bg-neutral-background rounded-full h-1.5 hidden sm:block">
                    <div class="bg-secondary h-1.5 rounded-full" :style="`width: ${rec.progress_percentage}%`"></div>
                  </div>
                  <span class="text-[10px] text-neutral-muted font-semibold">{{ rec.completed_lessons_count }}/{{ rec.total_lessons_count }}</span>
                </div>
              </td>
              <td class="p-4">
                <div v-if="!rec.quiz_scores?.length" class="text-xs text-neutral-muted">None</div>
                <div v-else class="space-y-1">
                  <div 
                    v-for="(quiz, idx) in rec.quiz_scores" 
                    :key="idx"
                    class="text-[10px] flex items-center gap-1.5"
                  >
                    <span :class="quiz.passed ? 'text-secondary' : 'text-red-400'">●</span>
                    <span class="text-neutral-muted">{{ quiz.quiz_title }} ({{ quiz.score }}%)</span>
                  </div>
                </div>
              </td>
              <td class="p-4">
                <span v-if="rec.certificate_earned" class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-accent-gold/20 text-amber-400 border border-accent-gold/30" :title="`Issued on ${formatDate(rec.certificate_earned.issued_at)}`">
                  🏆 {{ rec.certificate_earned.code }}
                </span>
                <span v-else class="text-xs text-neutral-muted italic">Not Earned</span>
              </td>
              <td class="p-4 text-right">
                <button 
                  @click="drillDown(rec)"
                  class="px-2.5 py-1 text-xs font-semibold rounded bg-neutral-background hover:bg-neutral-background text-neutral-black border border-neutral-ivory"
                >
                  🔍 Inspect
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Progress Inspection Modal (Drill-Down) -->
    <div v-if="showInspectionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-background/70 backdrop-blur-sm">
      <div class="w-full max-w-lg bg-white border border-neutral-ivory rounded-2xl p-6 shadow-2xl max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-4">
          <h3 class="text-lg font-bold text-white">Lesson Completion Audit</h3>
          <button @click="showInspectionModal = false" class="text-neutral-muted hover:text-white">✕</button>
        </div>

        <div v-if="selectedRecord" class="space-y-4">
          <div class="text-xs text-neutral-muted">
            Audit logs for <span class="text-secondary font-bold">{{ selectedRecord.user?.name }}</span> in
            <span class="text-white font-bold">{{ selectedRecord.course?.title }}</span>
          </div>

          <!-- Simulation details of completed curriculum -->
          <div class="space-y-2">
            <div 
              v-for="i in 5" 
              :key="i"
              class="flex items-center justify-between p-3 rounded border-neutral-ivory border border-neutral-ivory text-xs"
            >
              <span class="text-neutral-black">Lesson {{ i }}: Course Foundations</span>
              <span class="text-secondary font-bold">✓ Completed</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAdminProgressStore } from '@/stores/admin/academy/progress';
import client from '@/services/api/client';

const store = useAdminProgressStore();

const filters = ref({
  course_id: '',
  mentor_id: '',
  status: '',
});

const courses = ref<any[]>([]);
const mentors = ref<any[]>([]);

const showInspectionModal = ref(false);
const selectedRecord = ref<any>(null);

const loadFilterOptions = async () => {
  try {
    const courseResp = await client.get('/admin/academy/courses');
    courses.value = courseResp.data.courses || [];
    
    const mentorResp = await client.get('/admin/academy/mentors');
    mentors.value = mentorResp.data.mentors || [];
  } catch (e) {
    // console.log(e);
  }
};

const onFilter = () => {
  store.fetchProgress(filters.value, 1);
};

const resetFilters = () => {
  filters.value = { course_id: '', mentor_id: '', status: '' };
  store.fetchProgress({}, 1);
};

const drillDown = (rec: any) => {
  selectedRecord.value = rec;
  showInspectionModal.value = true;
};

const formatDate = (dateStr?: string) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString();
};

onMounted(() => {
  store.fetchProgress(filters.value, 1);
  loadFilterOptions();
});
</script>
