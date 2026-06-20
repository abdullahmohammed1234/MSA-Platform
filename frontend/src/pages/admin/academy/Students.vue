<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6 text-neutral-black">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-extrabold tracking-tight text-primary">
        Student Registry
      </h1>
      <p class="text-sm text-neutral-muted mt-1">Manage student profiles, monitor course enrollments, and toggle system access.</p>
    </div>

    <!-- Filters & Search -->
    <div class="p-4 rounded-xl bg-white border border-neutral-ivory flex flex-col md:flex-row gap-4 items-center justify-between">
      <div class="w-full md:w-80 relative">
        <input 
          v-model="filters.search"
          @input="onSearch"
          type="text"
          placeholder="Search by name or email..."
          class="w-full bg-white border border-neutral-ivory rounded-lg py-2 px-3 pl-9 text-neutral-black placeholder-neutral-gray text-sm focus:outline-none focus:border-primary transition"
        />
        <span class="absolute left-3 top-2.5 text-neutral-muted text-sm">🔍</span>
      </div>

      <div class="flex gap-3 w-full md:w-auto">
        <select v-model="filters.status" @change="onFilter" class="bg-neutral-background border border-neutral-ivory text-neutral-black text-sm rounded-lg p-2 focus:outline-none focus:border-primary">
          <option value="">All Statuses</option>
          <option value="active">Active Only</option>
          <option value="suspended">Suspended Only</option>
        </select>
      </div>
    </div>

    <!-- Grid / Table -->
    <div v-if="store.loading" class="flex justify-center py-20">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <div v-else-if="store.students.length === 0" class="p-12 text-center rounded-2xl bg-white border border-neutral-ivory">
      <p class="text-sm text-neutral-muted">No students registered yet.</p>
    </div>

    <div v-else class="space-y-4">
      <div class="overflow-x-auto rounded-2xl bg-white border border-neutral-ivory shadow-xl">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-neutral-ivory/80 text-xs font-bold text-neutral-muted uppercase tracking-wider bg-neutral-background/20">
              <th class="p-4">Student Name</th>
              <th class="p-4">Email</th>
              <th class="p-4">Enrollments</th>
              <th class="p-4">Certificates</th>
              <th class="p-4">Status</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory text-sm">
            <tr v-for="std in store.students" :key="std.id" class="hover:bg-neutral-background/20 transition">
              <td class="p-4 font-bold text-white flex items-center gap-3">
                <div class="h-8 w-8 rounded-full bg-neutral-background flex items-center justify-center text-xs border border-neutral-ivory">
                  {{ std.name.charAt(0) }}
                </div>
                <span>{{ std.name }}</span>
              </td>
              <td class="p-4 text-neutral-muted">{{ std.email }}</td>
              <td class="p-4 text-neutral-muted font-semibold">{{ std.enrollments_count || 0 }} Courses</td>
              <td class="p-4 text-amber-400 font-semibold">{{ std.certificate_awards_count || 0 }} Earned</td>
              <td class="p-4">
                <span :class="`text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full ${std.is_active ? 'bg-secondary/10 text-secondary border border-secondary/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`">
                  {{ std.is_active ? 'Active' : 'Suspended' }}
                </span>
              </td>
              <td class="p-4 text-right space-x-2">
                <button 
                  @click="viewProfile(std)"
                  class="px-2.5 py-1 text-xs font-semibold rounded bg-neutral-background hover:bg-neutral-background text-neutral-black"
                >
                  🔍 View
                </button>
                <button 
                  v-if="std.is_active"
                  @click="toggleStatus(std)"
                  class="px-2.5 py-1 text-xs font-semibold rounded bg-red-950/20 text-red-400 border border-red-900/30 hover:bg-red-950/45"
                >
                  Suspend
                </button>
                <button 
                  v-else
                  @click="toggleStatus(std)"
                  class="px-2.5 py-1 text-xs font-semibold rounded bg-primary/5 text-secondary border border-primary/15 hover:bg-primary/10"
                >
                  Reactivate
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Student Detail Modal -->
    <div v-if="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-background/70 backdrop-blur-sm">
      <div class="w-full max-w-2xl bg-white border border-neutral-ivory rounded-2xl p-6 shadow-2xl max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-4">
          <h3 class="text-lg font-bold text-white">Student Progress Profile</h3>
          <button @click="showDetailModal = false" class="text-neutral-muted hover:text-white">✕</button>
        </div>

        <div v-if="profileLoading" class="flex justify-center py-10">
          <div class="h-8 w-8 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
        </div>

        <div v-else-if="store.currentStudent" class="space-y-6">
          <!-- Bio card -->
          <div class="flex gap-4 p-4 rounded-xl border-neutral-ivory border border-neutral-ivory">
            <div class="h-12 w-12 rounded-full bg-secondary text-white font-bold flex items-center justify-center text-lg">
              {{ store.currentStudent.name.charAt(0) }}
            </div>
            <div>
              <h4 class="text-base font-extrabold text-white">{{ store.currentStudent.name }}</h4>
              <p class="text-xs text-neutral-muted">{{ store.currentStudent.email }}</p>
              <p class="text-[10px] text-neutral-muted mt-1">Status: {{ store.currentStudent.is_active ? 'Active User' : 'Suspended' }}</p>
            </div>
          </div>

          <!-- Enrollments Progress -->
          <div class="space-y-3">
            <h5 class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Course Progress</h5>
            <div v-if="!store.currentStudent.enrollments || store.currentStudent.enrollments.length === 0" class="text-xs text-neutral-muted italic">
              No enrollments registered yet.
            </div>
            <div 
              v-else
              v-for="enr in store.currentStudent.enrollments" 
              :key="enr.course_id"
              class="p-4 rounded-xl bg-white/60 border border-neutral-ivory/80 space-y-2"
            >
              <div class="flex justify-between text-xs font-bold">
                <span class="text-white">{{ enr.title }}</span>
                <span class="text-secondary">{{ enr.progress }}%</span>
              </div>
              <div class="w-full bg-neutral-background rounded-full h-2">
                <div class="bg-secondary h-2 rounded-full" :style="`width: ${enr.progress}%`"></div>
              </div>
              <div class="flex justify-between text-[10px] text-neutral-muted">
                <span>Enrolled: {{ formatDate(enr.enrolled_at) }}</span>
                <span class="capitalize">{{ enr.status }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAdminStudentsStore } from '@/stores/admin/academy/students';

const store = useAdminStudentsStore();

const filters = ref({
  search: '',
  status: '',
});

const showDetailModal = ref(false);
const profileLoading = ref(false);

const onSearch = () => {
  store.fetchStudents(filters.value);
};

const onFilter = () => {
  store.fetchStudents(filters.value);
};

const toggleStatus = async (student: any) => {
  try {
    if (student.is_active) {
      await store.suspendStudent(student.id);
    } else {
      await store.reactivateStudent(student.id);
    }
  } catch (err: any) {
    alert(err.message || 'Failed to toggle student status.');
  }
};

const viewProfile = async (student: any) => {
  showDetailModal.value = true;
  profileLoading.value = true;
  try {
    await store.fetchStudentProfile(student.id);
  } catch (err) {
    alert('Failed to load student progress.');
  } finally {
    profileLoading.value = false;
  }
};

const formatDate = (dateStr?: string) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString();
};

onMounted(() => {
  store.fetchStudents(filters.value);
});
</script>
