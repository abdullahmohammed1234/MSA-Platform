<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6 text-neutral-black">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-primary">
          Mentor Assignments
        </h1>
        <p class="text-sm text-neutral-muted mt-1">Assign, reassign, or remove student-mentor relationships, and perform bulk connections.</p>
      </div>

      <div class="flex gap-2">
        <button 
          @click="openAssignModal(false)"
          class="px-4 py-2 text-xs font-semibold rounded-lg bg-neutral-background border border-neutral-ivory hover:bg-neutral-background text-neutral-black transition"
        >
          ➕ Assign Mentor
        </button>
        <button 
          @click="openAssignModal(true)"
          class="px-4 py-2 text-sm font-semibold text-white bg-secondary hover:bg-secondary-light rounded-lg transition shadow-lg shadow-soft"
        >
          👥 Bulk Assign
        </button>
      </div>
    </div>

    <!-- Active Connections Table -->
    <div v-if="store.loading" class="flex justify-center py-20">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <div v-else-if="store.assignments.length === 0" class="p-12 text-center rounded-2xl bg-white border border-neutral-ivory">
      <p class="text-sm text-neutral-muted">No active student-mentor connections exist.</p>
    </div>

    <div v-else class="space-y-4">
      <div class="overflow-x-auto rounded-2xl bg-white border border-neutral-ivory shadow-xl">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-neutral-ivory/80 text-xs font-bold text-neutral-muted uppercase tracking-wider bg-neutral-background/20">
              <th class="p-4">Student</th>
              <th class="p-4">Assigned Mentor</th>
              <th class="p-4">Assigned By</th>
              <th class="p-4">Connected On</th>
              <th class="p-4">Status</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory text-sm">
            <tr v-for="asg in store.assignments" :key="asg.id" class="hover:bg-neutral-background/20 transition">
              <td class="p-4 font-bold text-white">{{ asg.student?.name || `Student #${asg.student_id}` }}</td>
              <td class="p-4 text-neutral-muted font-semibold">{{ asg.mentor?.name || `Mentor #${asg.mentor_id}` }}</td>
              <td class="p-4 text-neutral-muted text-xs">{{ asg.assigned_by_user?.name || 'System' }}</td>
              <td class="p-4 text-neutral-muted text-xs">{{ formatDate(asg.created_at) }}</td>
              <td class="p-4">
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-secondary/10 text-secondary border border-secondary/20">
                  {{ asg.status }}
                </span>
              </td>
              <td class="p-4 text-right">
                <button 
                  @click="removeAssignment(asg)"
                  class="px-2.5 py-1 text-xs font-semibold rounded bg-red-950/20 border border-red-900/30 text-red-400 hover:bg-red-950/40"
                >
                  Disconnect
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Assignment Modal (Single/Bulk) -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-background/70 backdrop-blur-sm">
      <div class="w-full max-w-md bg-white border border-neutral-ivory rounded-2xl p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-white mb-4">{{ isBulk ? 'Bulk Assign Students' : 'Assign Student' }}</h3>
        <form @submit.prevent="submitAssignment" class="space-y-4">
          <!-- Mentor Select -->
          <div class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Select Mentor *</label>
            <select v-model="form.mentor_id" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary">
              <option value="" disabled>Select mentor...</option>
              <option v-for="m in mentors" :key="m.id" :value="m.id">{{ m.name }} (Students: {{ m.students_count || 0 }})</option>
            </select>
          </div>

          <!-- Student Select (Single) -->
          <div v-if="!isBulk" class="space-y-1">
            <label class="text-xs font-bold text-neutral-muted">Select Student *</label>
            <select v-model="form.student_id" required class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none focus:border-primary">
              <option value="" disabled>Select student...</option>
              <option v-for="s in students" :key="s.id" :value="s.id">{{ s.name }} ({{ s.email }})</option>
            </select>
          </div>

          <!-- Students Selection (Bulk) -->
          <div v-else class="space-y-2">
            <label class="text-xs font-bold text-neutral-muted">Select Students (Check multiple) *</label>
            <div class="max-h-48 overflow-y-auto border-neutral-ivory rounded-lg p-2 border border-neutral-ivory space-y-2">
              <div v-for="s in students" :key="s.id" class="flex items-center gap-2 text-xs">
                <input type="checkbox" :value="s.id" v-model="form.student_ids" />
                <span class="text-neutral-muted">{{ s.name }}</span>
              </div>
            </div>
          </div>

          <div class="space-y-1" v-if="!isBulk">
            <label class="text-xs font-bold text-neutral-muted">Assignment Notes</label>
            <textarea v-model="form.notes" rows="2" class="w-full bg-neutral-background border border-neutral-ivory/60 rounded-lg p-2 text-neutral-black text-sm focus:outline-none"></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showModal = false" class="px-4 py-2 text-xs font-bold rounded-lg bg-neutral-background border border-neutral-ivory hover:bg-neutral-background text-neutral-muted transition">Cancel</button>
            <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg text-white bg-secondary hover:bg-secondary-light transition">Confirm Connect</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAdminAssignmentsStore } from '@/stores/admin/academy/assignments';
import client from '@/services/api/client';

const store = useAdminAssignmentsStore();

const showModal = ref(false);
const isBulk = ref(false);

const mentors = ref<any[]>([]);
const students = ref<any[]>([]);

const form = ref({
  mentor_id: '' as string | number,
  student_id: '' as string | number,
  student_ids: [] as number[],
  notes: '',
});

const loadFormOptions = async () => {
  try {
    const menResp = await client.get('/admin/academy/mentors');
    mentors.value = menResp.data.mentors || [];
    
    const stdResp = await client.get('/admin/academy/students');
    students.value = stdResp.data.students || [];
  } catch (e) {
    // console.log(e);
  }
};

const openAssignModal = async (bulk = false) => {
  isBulk.value = bulk;
  form.value = {
    mentor_id: mentors.value[0]?.id || '',
    student_id: students.value[0]?.id || '',
    student_ids: [],
    notes: '',
  };
  showModal.value = true;
};

const submitAssignment = async () => {
  try {
    if (isBulk.value) {
      if (form.value.student_ids.length === 0) {
        alert('Please select at least one student.');
        return;
      }
      await store.bulkAssign(Number(form.value.mentor_id), form.value.student_ids);
    } else {
      await store.assignMentor(Number(form.value.mentor_id), Number(form.value.student_id), form.value.notes);
    }
    showModal.value = false;
    store.fetchAssignments(1);
  } catch (err: any) {
    alert(err.message);
  }
};

const removeAssignment = async (asg: any) => {
  if (confirm('Disconnect student from mentor?')) {
    try {
      await store.removeAssignment(asg.mentor_id, asg.student_id);
    } catch (err: any) {
      alert(err.message);
    }
  }
};

const formatDate = (dateStr?: string) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString();
};

onMounted(() => {
  store.fetchAssignments(1);
  loadFormOptions();
});
</script>
