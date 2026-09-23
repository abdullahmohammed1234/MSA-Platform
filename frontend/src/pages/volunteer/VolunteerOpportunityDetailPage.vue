<template>
  <div class="volunteer-detail-page min-h-screen bg-slate-900 text-slate-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto space-y-8">
      
      <!-- Back Navigation -->
      <div>
        <router-link to="/volunteer" class="inline-flex items-center text-sm font-medium text-emerald-400 hover:text-emerald-300 transition">
          &larr; Back to Opportunities
        </router-link>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="h-96 rounded-2xl bg-slate-800/60 animate-pulse"></div>

      <!-- Not Found -->
      <div v-else-if="!opportunity" class="text-center py-16 bg-slate-800/40 rounded-3xl border border-slate-800">
        <h2 class="text-2xl font-bold text-slate-200">Opportunity Not Found</h2>
        <p class="text-slate-400 mt-2">The requested volunteer position may have been closed or removed.</p>
      </div>

      <template v-else>
        <!-- Header Card -->
        <div class="bg-slate-800/80 border border-slate-700/60 rounded-3xl p-6 sm:p-8 space-y-6 shadow-xl backdrop-blur-md">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <span v-if="opportunity.event" class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
              Linked Event: {{ opportunity.event.name }}
            </span>
            <span v-else class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-700 text-slate-300">
              Standalone Volunteer Opportunity
            </span>
          </div>

          <div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-100 tracking-tight">
              {{ opportunity.title }}
            </h1>
            <p class="text-slate-300 mt-4 whitespace-pre-line leading-relaxed">
              {{ opportunity.description || 'No detailed description available.' }}
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-700/50 text-sm">
            <div v-if="opportunity.location" class="flex items-center gap-3">
              <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              </svg>
              <span>{{ opportunity.location }}</span>
            </div>
            <div v-if="opportunity.start_at" class="flex items-center gap-3">
              <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span>{{ formatDate(opportunity.start_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Registration Form Card -->
        <div class="bg-slate-800/90 border border-slate-700/60 rounded-3xl p-6 sm:p-8 space-y-6 shadow-xl">
          <h2 class="text-2xl font-bold text-slate-100 border-b border-slate-700/60 pb-4">
            Volunteer Registration Form
          </h2>

          <!-- Alert feedback -->
          <div v-if="errorMessage" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
            {{ errorMessage }}
          </div>
          <div v-if="successMessage" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
            {{ successMessage }}
          </div>

          <form @submit.prevent="handleSignup" class="space-y-6">
            <!-- Shift Selection -->
            <div v-if="opportunity.shifts && opportunity.shifts.length > 0" class="space-y-3">
              <label class="block text-sm font-medium text-slate-300">
                Select Shift <span class="text-emerald-400">*</span>
              </label>
              <div class="grid grid-cols-1 gap-3">
                <label
                  v-for="shift in opportunity.shifts"
                  :key="shift.id"
                  :class="[
                    'flex items-center justify-between p-4 rounded-xl border cursor-pointer transition',
                    selectedShiftId === shift.id
                      ? 'bg-emerald-950/40 border-emerald-500 text-emerald-200'
                      : 'bg-slate-900 border-slate-700 text-slate-300 hover:border-slate-600'
                  ]"
                >
                  <div class="flex items-center gap-3">
                    <input
                      type="radio"
                      name="shift"
                      :value="shift.id"
                      v-model="selectedShiftId"
                      class="text-emerald-500 focus:ring-emerald-500 h-4 w-4 bg-slate-900 border-slate-700"
                    />
                    <div>
                      <div class="font-semibold">{{ shift.name || 'Shift ' + shift.id }}</div>
                      <div class="text-xs text-slate-400">{{ formatDate(shift.start_at) }} - {{ formatDate(shift.end_at) }}</div>
                    </div>
                  </div>
                  <span class="text-xs px-2.5 py-1 rounded-md bg-slate-800 text-slate-400 font-mono">
                    Capacity: {{ shift.capacity }}
                  </span>
                </label>
              </div>
            </div>

            <!-- Team Selection if available -->
            <div v-if="opportunity.teams && opportunity.teams.length > 0" class="space-y-2">
              <label class="block text-sm font-medium text-slate-300">Preferred Team</label>
              <select
                v-model="selectedTeamId"
                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 focus:border-emerald-500 focus:outline-none"
              >
                <option :value="null">Any / No Preference</option>
                <option v-for="team in opportunity.teams" :key="team.id" :value="team.id">
                  {{ team.name }}
                </option>
              </select>
            </div>

            <!-- Contact Fields -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Full Name <span class="text-emerald-400">*</span></label>
                <input
                  v-model="form.name"
                  type="text"
                  required
                  placeholder="Your full name"
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 focus:border-emerald-500 focus:outline-none"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Email Address <span class="text-emerald-400">*</span></label>
                <input
                  v-model="form.email"
                  type="email"
                  required
                  placeholder="name@example.com"
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 focus:border-emerald-500 focus:outline-none"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-300 mb-1">Phone Number</label>
              <input
                v-model="form.phone"
                type="tel"
                placeholder="(604) 123-4567"
                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 focus:border-emerald-500 focus:outline-none"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-300 mb-1">Prior Experience or Special Skills</label>
              <textarea
                v-model="form.experience"
                rows="3"
                placeholder="Mention any past event, setup, AV, or customer service experience..."
                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 focus:border-emerald-500 focus:outline-none"
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-300 mb-1">Additional Notes</label>
              <textarea
                v-model="form.notes"
                rows="2"
                placeholder="Any availability constraints or questions..."
                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 focus:border-emerald-500 focus:outline-none"
              ></textarea>
            </div>

            <button
              type="submit"
              :disabled="submitting"
              class="w-full inline-flex justify-center items-center px-6 py-3 rounded-xl font-bold text-slate-900 bg-emerald-400 hover:bg-emerald-300 disabled:opacity-50 transition shadow-lg shadow-emerald-950/30"
            >
              <span v-if="submitting">Processing Registration...</span>
              <span v-else>Confirm & Submit Volunteer Signup</span>
            </button>
          </form>
        </div>
      </template>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { volunteeringService, type VolunteerOpportunity } from '../../services/volunteeringService';

const route = useRoute();
const slug = route.params.slug as string;

const opportunity = ref<VolunteerOpportunity | null>(null);
const loading = ref(true);
const submitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const selectedShiftId = ref<number | null>(null);
const selectedTeamId = ref<number | null>(null);

const form = ref({
  name: '',
  email: '',
  phone: '',
  experience: '',
  notes: '',
});

const fetchOpportunity = async () => {
  loading.value = true;
  try {
    const res = await volunteeringService.getOpportunityBySlug(slug);
    opportunity.value = res.data;
    if (opportunity.value?.shifts && opportunity.value.shifts.length > 0) {
      selectedShiftId.value = opportunity.value.shifts[0].id;
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load opportunity details.';
  } finally {
    loading.value = false;
  }
};

const handleSignup = async () => {
  if (!opportunity.value) return;
  errorMessage.value = '';
  successMessage.value = '';
  submitting.value = true;

  try {
    await volunteeringService.submitSignup({
      opportunity_id: opportunity.value.id,
      shift_id: selectedShiftId.value || undefined,
      team_id: selectedTeamId.value || undefined,
      name: form.value.name,
      email: form.value.email,
      phone: form.value.phone || undefined,
      experience: form.value.experience || undefined,
      notes: form.value.notes || undefined,
    });

    successMessage.value = 'JazakAllah Khair! Your volunteer signup has been received successfully.';
    form.value = { name: '', email: '', phone: '', experience: '', notes: '' };
  } catch (err: any) {
    const msg = err.response?.data?.message || err.response?.data?.errors?.[Object.keys(err.response?.data?.errors || {})[0]]?.[0];
    errorMessage.value = msg || 'An error occurred while submitting your signup. Please try again.';
  } finally {
    submitting.value = false;
  }
};

const formatDate = (dateStr?: string | null) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

onMounted(() => {
  fetchOpportunity();
});
</script>
