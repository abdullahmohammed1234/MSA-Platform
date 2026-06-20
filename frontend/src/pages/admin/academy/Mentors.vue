<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6 text-neutral-black">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-extrabold tracking-tight text-primary">
        Mentor Management
      </h1>
      <p class="text-sm text-neutral-muted mt-1">Review active academy mentors, their workloads, and track student success metrics.</p>
    </div>

    <!-- Loading State -->
    <div v-if="store.loading" class="flex justify-center py-20">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="store.mentors.length === 0" class="p-12 text-center rounded-2xl bg-white border border-neutral-ivory">
      <p class="text-sm text-neutral-muted">No mentors registered in the system yet.</p>
    </div>

    <!-- Mentors Table -->
    <div v-else class="space-y-4">
      <div class="overflow-x-auto rounded-2xl bg-white border border-neutral-ivory shadow-xl">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-neutral-ivory/80 text-xs font-bold text-neutral-muted uppercase tracking-wider bg-neutral-background/20">
              <th class="p-4">Mentor Name</th>
              <th class="p-4">Email</th>
              <th class="p-4">Assigned Students</th>
              <th class="p-4">Load Status</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory text-sm">
            <tr v-for="men in store.mentors" :key="men.id" class="hover:bg-neutral-background/20 transition">
              <td class="p-4 font-bold text-white flex items-center gap-3">
                <div class="h-8 w-8 rounded-full bg-primary/10 border border-primary/20 text-primary flex items-center justify-center text-xs">
                  {{ men.name.charAt(0) }}
                </div>
                <span>{{ men.name }}</span>
              </td>
              <td class="p-4 text-neutral-muted">{{ men.email }}</td>
              <td class="p-4 text-neutral-muted font-semibold">{{ men.students_count }} students</td>
              <td class="p-4">
                <span 
                  :class="`text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ${men.students_count >= 15 ? 'bg-red-500/10 text-red-400' : men.students_count >= 10 ? 'bg-accent-gold/20 text-amber-400' : 'bg-secondary/10 text-secondary'}`"
                >
                  {{ men.students_count >= 15 ? 'Full Load' : 'Available' }}
                </span>
              </td>
              <td class="p-4 text-right">
                <button 
                  @click="viewProfile(men)"
                  class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-neutral-background hover:bg-neutral-background text-neutral-black transition"
                >
                  🔍 View Workload
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mentor Workload Detail Modal -->
    <div v-if="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-background/70 backdrop-blur-sm">
      <div class="w-full max-w-2xl bg-white border border-neutral-ivory rounded-2xl p-6 shadow-2xl max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-4">
          <h3 class="text-lg font-bold text-white">Mentor Workload & Metrics</h3>
          <button @click="showDetailModal = false" class="text-neutral-muted hover:text-white">✕</button>
        </div>

        <div v-if="profileLoading" class="flex justify-center py-10">
          <div class="h-8 w-8 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
        </div>

        <div v-else-if="store.currentMentor" class="space-y-6">
          <!-- Bio card -->
          <div class="flex gap-4 p-4 rounded-xl border-neutral-ivory border border-neutral-ivory items-center">
            <div class="h-12 w-12 rounded-full bg-primary text-white font-bold flex items-center justify-center text-lg">
              {{ store.currentMentor.name.charAt(0) }}
            </div>
            <div class="flex-1">
              <h4 class="text-base font-extrabold text-white">{{ store.currentMentor.name }}</h4>
              <p class="text-xs text-neutral-muted">{{ store.currentMentor.email }}</p>
            </div>
            <div class="text-right">
              <p class="text-xl font-black text-white">{{ store.currentMentor.assigned_count }} / {{ store.currentMentor.capacity }}</p>
              <p class="text-[10px] text-neutral-muted uppercase font-bold tracking-wider">Active Capacity</p>
            </div>
          </div>

          <!-- Engagement Metrics -->
          <div class="grid grid-cols-2 gap-4">
            <div class="p-4 rounded-xl border-neutral-ivory/50 border border-neutral-ivory/80 text-center">
              <p class="text-2xl font-black text-secondary">{{ store.currentMentor.engagement_metrics?.total_lessons_completed || 0 }}</p>
              <p class="text-[10px] text-neutral-muted font-bold uppercase tracking-wider mt-1">Student Lessons Completed</p>
            </div>
            <div class="p-4 rounded-xl border-neutral-ivory/50 border border-neutral-ivory/80 text-center">
              <p class="text-2xl font-black text-primary">{{ store.currentMentor.assigned_count }}</p>
              <p class="text-[10px] text-neutral-muted font-bold uppercase tracking-wider mt-1">Currently Monitored</p>
            </div>
          </div>

          <!-- Assigned Students List -->
          <div class="space-y-3">
            <h5 class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Assigned Students</h5>
            <div v-if="!store.currentMentor.students || store.currentMentor.students.length === 0" class="text-xs text-neutral-muted italic">
              No students currently assigned to this mentor.
            </div>
            <div class="space-y-2" v-else>
              <div 
                v-for="std in store.currentMentor.students" 
                :key="std.id"
                class="flex items-center justify-between p-3 rounded-lg bg-white/60 border border-neutral-ivory/85 text-xs"
              >
                <div>
                  <p class="font-bold text-neutral-black">{{ std.name }}</p>
                  <p class="text-[10px] text-neutral-muted">{{ std.email }}</p>
                </div>
                <div class="text-right">
                  <span class="text-secondary font-semibold">{{ std.completed_count }} / {{ std.enrollments_count }} Completed</span>
                  <p class="text-[9px] text-neutral-muted mt-0.5">Connected on {{ formatDate(std.assigned_at) }}</p>
                </div>
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
import { useAdminMentorsStore } from '@/stores/admin/academy/mentors';

const store = useAdminMentorsStore();

const showDetailModal = ref(false);
const profileLoading = ref(false);

const viewProfile = async (mentor: any) => {
  showDetailModal.value = true;
  profileLoading.value = true;
  try {
    await store.fetchMentorProfile(mentor.id);
  } catch (err) {
    alert('Failed to load mentor profile.');
  } finally {
    profileLoading.value = false;
  }
};

const formatDate = (dateStr?: string) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString();
};

onMounted(() => {
  store.fetchMentors();
});
</script>
