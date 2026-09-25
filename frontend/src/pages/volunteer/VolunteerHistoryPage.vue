<template>
  <div class="volunteer-history-page min-h-screen bg-neutral-background text-neutral-black pt-24 sm:pt-32 pb-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto space-y-8">
      
      <!-- Header Bar -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-neutral-ivory pb-6">
        <div>
          <span class="text-xs font-extrabold uppercase tracking-widest text-secondary">My Activity</span>
          <h1 class="text-3xl font-display font-extrabold text-primary">Volunteer History</h1>
          <p class="text-xs sm:text-sm text-neutral-black/70 mt-1">Review your upcoming shifts, waitlists, and past community contributions.</p>
        </div>

        <router-link
          to="/volunteer"
          class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-xs font-extrabold uppercase tracking-wider text-white bg-primary hover:bg-secondary transition shadow-brand"
        >
          <Sparkles class="w-4 h-4" />
          <span>Find Opportunities</span>
        </router-link>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="bg-white rounded-3xl border border-neutral-ivory p-12 text-center space-y-4 shadow-soft">
        <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto" />
        <p class="text-sm font-bold text-neutral-black/70">Loading your volunteer history...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-3xl p-8 text-center space-y-4 max-w-md mx-auto">
        <AlertCircle class="w-12 h-12 text-red-600 mx-auto" />
        <h3 class="text-xl font-bold text-red-900">Couldn't Load History</h3>
        <p class="text-xs text-red-700">{{ error }}</p>
        <button
          @click="fetchHistory"
          class="px-5 py-2.5 rounded-full bg-red-600 text-white font-extrabold text-xs uppercase tracking-wider hover:bg-red-700 transition"
        >
          Retry
        </button>
      </div>

      <!-- Empty State -->
      <div v-else-if="signups.length === 0" class="bg-white rounded-3xl border border-neutral-ivory p-12 text-center space-y-4 max-w-lg mx-auto shadow-soft">
        <Calendar class="w-12 h-12 text-neutral-muted mx-auto" />
        <h3 class="text-xl font-bold text-primary">No Volunteer Signups Yet</h3>
        <p class="text-xs text-neutral-black/70">You haven't signed up for any volunteer positions yet. Explore active opportunities and get involved!</p>
        <router-link
          to="/volunteer"
          class="inline-block px-6 py-3 rounded-full text-xs font-extrabold uppercase tracking-wider text-white bg-primary hover:bg-secondary transition shadow-brand"
        >
          Explore Opportunities
        </router-link>
      </div>

      <!-- Signups List -->
      <div v-else class="space-y-4">
        <div
          v-for="signup in signups"
          :key="signup.id"
          class="bg-white rounded-3xl border border-neutral-ivory p-6 shadow-soft hover:shadow-premium transition-all space-y-4"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <span
                :class="[
                  'px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider border',
                  statusBadgeClass(signup.status)
                ]"
              >
                {{ signup.status }}
              </span>
              <span class="text-xs text-neutral-muted font-mono">
                Signed up on {{ formatDate(signup.created_at) }}
              </span>
            </div>

            <!-- Cancel Button if Active -->
            <button
              v-if="canCancel(signup.status)"
              @click="confirmCancel(signup)"
              class="px-4 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wider text-red-600 border border-red-200 hover:bg-red-50 transition cursor-pointer"
            >
              Cancel Signup
            </button>
          </div>

          <div class="space-y-2">
            <h3 class="text-xl font-bold text-primary">
              <router-link v-if="signup.opportunity?.slug" :to="`/volunteer/${signup.opportunity.slug}`" class="hover:underline">
                {{ signup.opportunity?.title || 'Volunteer Opportunity' }}
              </router-link>
              <span v-else>{{ signup.opportunity?.title || 'Volunteer Opportunity' }}</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-neutral-ivory/60 text-xs sm:text-sm text-neutral-black/80">
              <div v-if="signup.team?.name">
                <span class="text-neutral-muted uppercase tracking-wider font-bold">Team:</span>
                <span class="font-bold ml-1 text-primary">{{ signup.team.name }}</span>
              </div>

              <div v-if="signup.shift">
                <span class="text-neutral-muted uppercase tracking-wider font-bold">Shift:</span>
                <span class="ml-1 font-mono text-xs">{{ formatShift(signup.shift) }}</span>
              </div>

              <div v-if="signup.opportunity?.location">
                <span class="text-neutral-muted uppercase tracking-wider font-bold">Location:</span>
                <span class="ml-1">{{ signup.opportunity.location }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cancellation Confirmation Modal -->
      <Teleport to="body">
        <div v-if="signupToCancel" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-primary/30 backdrop-blur-sm">
          <div class="bg-white rounded-3xl border border-neutral-ivory p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-6">
            <div class="space-y-2 text-center sm:text-left">
              <h3 class="text-xl font-display font-extrabold text-primary">Cancel Volunteer Signup?</h3>
              <p class="text-xs text-neutral-black/70">
                Are you sure you want to cancel your signup for <strong>{{ signupToCancel.opportunity?.title }}</strong>? This action cannot be undone.
              </p>
            </div>

            <div v-if="cancelError" class="bg-red-50 border border-red-200 text-red-800 p-3 rounded-2xl text-xs">
              {{ cancelError }}
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <button
                @click="signupToCancel = null"
                class="px-5 py-2.5 rounded-full text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 border border-neutral-ivory hover:bg-neutral-background transition cursor-pointer"
              >
                Keep Signup
              </button>
              <button
                @click="executeCancel"
                :disabled="cancelling"
                class="px-5 py-2.5 rounded-full text-xs font-extrabold uppercase tracking-wider text-white bg-red-600 hover:bg-red-700 transition shadow-lg disabled:opacity-50 cursor-pointer"
              >
                {{ cancelling ? 'Cancelling...' : 'Confirm Cancellation' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Calendar, Sparkles, AlertCircle } from 'lucide-vue-next';
import { volunteeringService, type VolunteerSignup } from '@/services/volunteeringService';

const signups = ref<VolunteerSignup[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const signupToCancel = ref<VolunteerSignup | null>(null);
const cancelling = ref(false);
const cancelError = ref<string | null>(null);

const fetchHistory = async () => {
  loading.value = true;
  error.value = null;
  try {
    const res = await volunteeringService.getMyHistory();
    const data = res.data || res;
    signups.value = Array.isArray(data.signups) ? data.signups : (Array.isArray(data) ? data : []);
  } catch (err: any) {
    console.error('Failed to load volunteer history:', err);
    error.value = err?.response?.data?.message || 'Failed to fetch volunteer history.';
  } finally {
    loading.value = false;
  }
};

const statusBadgeClass = (status: string) => {
  const s = status?.toLowerCase();
  if (s === 'confirmed' || s === 'approved') return 'bg-emerald-50 text-emerald-800 border-emerald-200';
  if (s === 'signed_up' || s === 'signed-up') return 'bg-sky-50 text-sky-800 border-sky-200';
  if (s === 'waitlisted') return 'bg-amber-50 text-amber-800 border-amber-200';
  if (s === 'cancelled') return 'bg-gray-100 text-gray-600 border-gray-200';
  return 'bg-neutral-ivory/60 text-neutral-black/70 border-neutral-ivory';
};

const canCancel = (status: string) => {
  const s = status?.toLowerCase();
  return s === 'signed_up' || s === 'signed-up' || s === 'confirmed' || s === 'approved' || s === 'waitlisted' || s === 'pending';
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const formatShift = (shift: any) => {
  if (!shift) return 'General';
  const name = shift.name ? `${shift.name}: ` : '';
  if (!shift.start_at || !shift.end_at) return name || 'Scheduled';
  const start = new Date(shift.start_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  const end = new Date(shift.end_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  return `${name}${start} - ${end}`;
};

const confirmCancel = (signup: VolunteerSignup) => {
  signupToCancel.value = signup;
  cancelError.value = null;
};

const executeCancel = async () => {
  if (!signupToCancel.value) return;
  cancelling.value = true;
  cancelError.value = null;

  try {
    await volunteeringService.cancelSignup(signupToCancel.value.uuid);
    signupToCancel.value = null;
    await fetchHistory();
  } catch (err: any) {
    console.error('Cancellation failed:', err);
    cancelError.value = err?.response?.data?.message || 'Failed to cancel volunteer signup.';
  } finally {
    cancelling.value = false;
  }
};

onMounted(() => {
  fetchHistory();
});
</script>
