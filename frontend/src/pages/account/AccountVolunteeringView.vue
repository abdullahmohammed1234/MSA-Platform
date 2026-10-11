<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { accountService, type AccountSummaryResponse } from '@/services/account/accountService';
import { Heart, Clock, CheckCircle2, ArrowRight, UserCheck } from 'lucide-vue-next';

const summary = ref<AccountSummaryResponse | null>(null);
const isLoading = ref<boolean>(true);
const errorMessage = ref<string | null>(null);

const loadData = async () => {
  isLoading.value = true;
  errorMessage.value = null;
  try {
    summary.value = await accountService.getSummary();
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load volunteer details.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  loadData();
});
</script>

<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <Heart class="h-6 w-6 text-primary shrink-0" />
        <div>
          <h2 class="text-lg font-display font-black text-primary">Volunteering & Community Service</h2>
          <p class="text-xs text-neutral-black/60 font-medium">Manage your volunteer participation, profile, and shift records.</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <router-link
          to="/volunteer/profile"
          class="px-4 py-2 bg-primary/10 text-primary border border-primary/20 rounded-xl text-xs font-extrabold uppercase tracking-wider hover:bg-primary/20 transition-all inline-flex items-center gap-1.5"
        >
          <UserCheck class="h-3.5 w-3.5" />
          Edit Volunteer Profile
        </router-link>

        <router-link
          to="/volunteer/my-history"
          class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-extrabold uppercase tracking-wider hover:bg-secondary transition-all inline-flex items-center gap-1.5 shadow-soft"
        >
          <Clock class="h-3.5 w-3.5" />
          Full History
        </router-link>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-16 flex flex-col items-center justify-center space-y-4">
      <div class="h-10 w-10 border-4 border-primary/20 border-t-primary rounded-full animate-spin" />
      <p class="text-xs font-bold text-neutral-black/60 uppercase tracking-wider">Loading volunteer data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="bg-red-50 border border-red-200 rounded-2xl p-6 text-center text-red-700">
      <p class="font-bold text-sm">{{ errorMessage }}</p>
      <button @click="loadData" class="mt-3 px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-secondary transition-all">
        Retry
      </button>
    </div>

    <!-- Volunteer Content -->
    <div v-else-if="summary" class="space-y-8">
      <!-- Volunteer Status Card -->
      <div v-if="summary.volunteer" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
          <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/50 block mb-1">Status</span>
          <div class="flex items-center gap-2">
            <CheckCircle2 class="h-5 w-5 text-emerald-600" />
            <span class="text-lg font-black text-primary capitalize">{{ summary.volunteer.status }}</span>
          </div>
          <p class="text-[11px] text-neutral-black/60 mt-2 font-medium">Registered SFU MSA Volunteer</p>
        </div>

        <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
          <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/50 block mb-1">Profile Completion</span>
          <div class="text-2xl font-black text-primary">{{ summary.volunteer.completion_percentage }}%</div>
          <div class="w-full bg-neutral-background h-2 rounded-full mt-3 overflow-hidden border border-neutral-ivory">
            <div class="bg-primary h-full rounded-full transition-all duration-500" :style="{ width: `${summary.volunteer.completion_percentage}%` }" />
          </div>
        </div>

        <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
          <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/50 block mb-1">Shifts Completed</span>
          <div class="text-2xl font-black text-primary">{{ summary.volunteer.completed_shifts_count }}</div>
          <p class="text-[11px] text-neutral-black/60 mt-1 font-medium">Assigned shifts attended</p>
        </div>
      </div>

      <!-- Non-Volunteer CTA -->
      <div v-else class="bg-white p-8 rounded-3xl border border-neutral-ivory shadow-soft text-center max-w-2xl mx-auto space-y-4">
        <Heart class="h-12 w-12 text-primary/30 mx-auto" />
        <h3 class="text-xl font-display font-black text-primary">Join the SFU MSA Volunteer Team</h3>
        <p class="text-xs text-neutral-black/70 leading-relaxed font-medium">
          Earn community service hours, build lifelong brotherhood and sisterhood, and support MSA events and spiritual initiatives across campus.
        </p>
        <router-link 
          to="/volunteer/profile" 
          class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-2xl text-xs font-extrabold uppercase tracking-wider hover:bg-secondary transition-all shadow-soft hover:shadow-premium"
        >
          <span>Complete Volunteer Profile</span>
          <ArrowRight class="h-4 w-4" />
        </router-link>
      </div>

      <!-- Registered Skills & Direct Links -->
      <div v-if="summary.volunteer" class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft space-y-6">
        <h3 class="text-sm font-extrabold uppercase tracking-wider text-primary border-b border-neutral-ivory pb-3">
          My Registered Skills & Preferences
        </h3>

        <div v-if="summary.volunteer.skills.length > 0">
          <div class="flex flex-wrap gap-2">
            <span 
              v-for="skill in summary.volunteer.skills" 
              :key="skill"
              class="px-3 py-1.5 bg-neutral-background rounded-xl text-xs font-bold text-neutral-black/80 border border-neutral-ivory shadow-soft"
            >
              {{ skill }}
            </span>
          </div>
        </div>
        <div v-else class="text-xs text-neutral-black/50 font-medium">
          No skills attached yet. <router-link to="/volunteer/profile" class="text-primary font-bold hover:underline">Update skills & preferences &rarr;</router-link>
        </div>
      </div>
    </div>
  </div>
</template>
