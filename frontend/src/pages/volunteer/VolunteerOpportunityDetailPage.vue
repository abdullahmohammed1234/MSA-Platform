<template>
  <div class="volunteer-detail-page min-h-screen bg-neutral-background text-neutral-black pt-24 sm:pt-32 pb-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto space-y-8">
      
      <!-- Back Link -->
      <div>
        <router-link
          to="/volunteer"
          class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 hover:text-primary transition"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Opportunities</span>
        </router-link>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="bg-white rounded-3xl border border-neutral-ivory p-12 text-center space-y-4 shadow-soft">
        <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto" />
        <p class="text-sm font-bold text-neutral-black/70">Loading opportunity details...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error || !opportunity" class="bg-red-50 border border-red-200 rounded-3xl p-8 text-center space-y-4 max-w-md mx-auto">
        <AlertCircle class="w-12 h-12 text-red-600 mx-auto" />
        <h3 class="text-xl font-bold text-red-900">Opportunity Not Found</h3>
        <p class="text-xs text-red-700">{{ error || 'The requested opportunity could not be found.' }}</p>
        <router-link
          to="/volunteer"
          class="inline-block px-5 py-2.5 rounded-full bg-red-600 text-white font-extrabold text-xs uppercase tracking-wider hover:bg-red-700 transition"
        >
          View All Opportunities
        </router-link>
      </div>

      <!-- Detail Experience -->
      <template v-else>
        <!-- HERO CARD -->
        <div class="bg-white rounded-3xl border border-neutral-ivory p-6 sm:p-10 shadow-premium space-y-6 relative overflow-hidden">
          <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-primary via-secondary to-accent-gold" />

          <div class="flex flex-wrap items-center justify-between gap-3">
            <!-- Event Link Badge vs Standalone Badge -->
            <router-link
              v-if="opportunity.event"
              :to="`/events/${opportunity.event.slug}`"
              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wider bg-primary/10 text-primary border border-primary/20 hover:bg-primary/20 transition-all"
            >
              <Sparkles class="w-3.5 h-3.5" />
              Event: {{ opportunity.event.name }}
            </router-link>
            <span
              v-else
              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wider bg-neutral-ivory/80 text-neutral-black/70 border border-neutral-ivory"
            >
              Standalone Volunteer Role
            </span>

            <VolunteerCapacityBadge
              :status="opportunity.status"
              :capacity="opportunity.capacity"
              :signups-count="opportunity.signups_count"
            />
          </div>

          <div class="space-y-3">
            <h1 class="text-3xl sm:text-4xl font-display font-extrabold text-primary leading-tight">
              {{ opportunity.title }}
            </h1>
            <p v-if="opportunity.description" class="text-sm sm:text-base text-neutral-black/80 leading-relaxed">
              {{ opportunity.description }}
            </p>
          </div>

          <!-- Metadata Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-neutral-ivory text-xs sm:text-sm text-neutral-black/80">
            <div v-if="opportunity.location" class="flex items-center gap-2.5">
              <MapPin class="w-4 h-4 text-primary shrink-0" />
              <span>Location: <strong>{{ opportunity.location }}</strong></span>
            </div>

            <div v-if="formattedStartDate" class="flex items-center gap-2.5">
              <Calendar class="w-4 h-4 text-primary shrink-0" />
              <span>Start Date: <strong>{{ formattedStartDate }}</strong></span>
            </div>
          </div>
        </div>

        <!-- SIGNUP SUCCESS CONFIRMATION PANEL -->
        <div v-if="signupSuccess" class="bg-emerald-50 border border-emerald-200 rounded-3xl p-8 sm:p-10 shadow-premium space-y-6 text-center">
          <div class="w-16 h-16 bg-emerald-600 text-white rounded-full flex items-center justify-center mx-auto shadow-lg">
            <CheckCircle2 class="w-10 h-10" />
          </div>

          <div class="space-y-2">
            <h2 class="text-2xl sm:text-3xl font-display font-extrabold text-emerald-900">
              {{ signupResponse?.status === 'waitlisted' ? "You're on the Waitlist!" : "You're Signed Up!" }}
            </h2>
            <p class="text-sm text-emerald-800 max-w-lg mx-auto">
              {{ signupResponse?.status === 'waitlisted' 
                ? "This position is currently at capacity. We've added you to the waitlist queue and will notify you if a spot opens up!" 
                : "Jazakum Allahu Khayran for volunteering with SFU MSA. Your signup has been recorded successfully." }}
            </p>
          </div>

          <div class="bg-white rounded-2xl p-6 border border-emerald-200 text-left max-w-md mx-auto space-y-3 text-xs sm:text-sm text-neutral-black/80">
            <div><span class="text-neutral-muted uppercase tracking-wider font-bold">Opportunity:</span> {{ opportunity.title }}</div>
            <div v-if="selectedTeamObject"><span class="text-neutral-muted uppercase tracking-wider font-bold">Team:</span> {{ selectedTeamObject.name }}</div>
            <div v-if="selectedShiftObject"><span class="text-neutral-muted uppercase tracking-wider font-bold">Shift:</span> {{ formatShiftTime(selectedShiftObject) }}</div>
            <div><span class="text-neutral-muted uppercase tracking-wider font-bold">Status:</span> <span class="capitalize font-bold text-primary">{{ signupResponse?.status || 'Confirmed' }}</span></div>
          </div>

          <div class="pt-4 flex flex-wrap justify-center gap-4">
            <router-link
              to="/volunteer/my-history"
              class="px-6 py-3 rounded-full text-xs font-extrabold uppercase tracking-wider text-white bg-primary hover:bg-secondary transition shadow-brand"
            >
              View My Volunteer History
            </router-link>
            <router-link
              to="/volunteer"
              class="px-6 py-3 rounded-full text-xs font-extrabold uppercase tracking-wider text-neutral-black/80 border border-neutral-ivory hover:bg-white transition"
            >
              Explore Other Roles
            </router-link>
          </div>
        </div>

        <!-- MAIN CONTENT: TEAMS/SHIFTS & SIGNUP FORM -->
        <div v-else class="space-y-8">
          
          <!-- TEAMS & SHIFTS DETAILS -->
          <div v-if="(opportunity.teams && opportunity.teams.length > 0) || (opportunity.shifts && opportunity.shifts.length > 0)" class="space-y-6">
            
            <!-- Teams Section -->
            <div v-if="opportunity.teams && opportunity.teams.length > 0" class="bg-white rounded-3xl border border-neutral-ivory p-6 sm:p-8 shadow-soft space-y-4">
              <h2 class="text-xl font-display font-extrabold text-primary flex items-center gap-2">
                <Users class="w-5 h-5 text-secondary" />
                <span>Available Teams</span>
              </h2>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div
                  v-for="team in opportunity.teams"
                  :key="team.id"
                  :class="[
                    'p-4 rounded-2xl border transition-all cursor-pointer',
                    selectedTeamId === team.id ? 'border-primary bg-primary/5 shadow-soft' : 'border-neutral-ivory hover:border-primary/40'
                  ]"
                  @click="selectedTeamId = team.id"
                >
                  <div class="flex items-center justify-between gap-2">
                    <h3 class="font-bold text-neutral-black">{{ team.name }}</h3>
                    <span
                      :class="[
                        'text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-0.5 rounded-full',
                        team.status === 'closed' ? 'bg-gray-100 text-gray-600' : 'bg-emerald-50 text-emerald-800'
                      ]"
                    >
                      {{ team.status }}
                    </span>
                  </div>
                  <p v-if="team.description" class="text-xs text-neutral-black/70 mt-1">{{ team.description }}</p>
                </div>
              </div>
            </div>

            <!-- Shifts Section -->
            <div v-if="opportunity.shifts && opportunity.shifts.length > 0" class="bg-white rounded-3xl border border-neutral-ivory p-6 sm:p-8 shadow-soft space-y-4">
              <h2 class="text-xl font-display font-extrabold text-primary flex items-center gap-2">
                <Clock class="w-5 h-5 text-secondary" />
                <span>Shift Schedule</span>
              </h2>

              <div class="space-y-3">
                <div
                  v-for="shift in filteredShifts"
                  :key="shift.id"
                  :class="[
                    'p-4 rounded-2xl border transition-all cursor-pointer flex flex-col sm:flex-row justify-between sm:items-center gap-3',
                    selectedShiftId === shift.id ? 'border-primary bg-primary/5 shadow-soft' : 'border-neutral-ivory hover:border-primary/40'
                  ]"
                  @click="selectedShiftId = shift.id"
                >
                  <div>
                    <div class="font-bold text-sm text-neutral-black">{{ shift.name || 'General Shift' }}</div>
                    <div class="text-xs text-neutral-muted mt-0.5">{{ formatShiftTime(shift) }}</div>
                  </div>

                  <div class="flex items-center gap-3">
                    <span class="text-xs font-mono font-bold text-neutral-black/70">
                      Capacity: {{ shift.capacity || 'Open' }}
                    </span>
                    <span
                      :class="[
                        'text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-0.5 rounded-full',
                        shift.status === 'closed' ? 'bg-gray-100 text-gray-600' : 'bg-emerald-50 text-emerald-800'
                      ]"
                    >
                      {{ shift.status }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- SIGNUP FORM (CENTERED CARD) -->
          <div class="bg-white rounded-3xl border border-neutral-ivory p-6 sm:p-10 shadow-premium space-y-6 max-w-4xl mx-auto">
            <div class="border-b border-neutral-ivory pb-4 text-center sm:text-left">
              <h2 class="text-2xl font-display font-extrabold text-primary">Sign Up to Volunteer</h2>
              <p class="text-xs sm:text-sm text-neutral-black/70 mt-1">Fill out your contact info to register for this position.</p>
            </div>

            <!-- Form Feedback Error -->
            <div v-if="formError" class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-2xl text-xs sm:text-sm flex items-start gap-3">
              <AlertCircle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
              <span>{{ formError }}</span>
            </div>

            <form @submit.prevent="handleSignup" class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
              <!-- Team Selection Dropdown if teams exist -->
              <div v-if="opportunity.teams && opportunity.teams.length > 0" class="space-y-1.5 sm:col-span-1">
                <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70">Select Team *</label>
                <select
                  v-model="selectedTeamId"
                  required
                  class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-sm text-neutral-black bg-white focus:outline-none focus:border-primary"
                >
                  <option :value="undefined" disabled>Choose a team</option>
                  <option v-for="team in opportunity.teams" :key="team.id" :value="team.id">
                    {{ team.name }}
                  </option>
                </select>
              </div>

              <!-- Shift Selection Dropdown if shifts exist -->
              <div v-if="opportunity.shifts && opportunity.shifts.length > 0" class="space-y-1.5 sm:col-span-1">
                <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70">Select Shift *</label>
                <select
                  v-model="selectedShiftId"
                  required
                  class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-sm text-neutral-black bg-white focus:outline-none focus:border-primary"
                >
                  <option :value="undefined" disabled>Choose a shift</option>
                  <option v-for="shift in filteredShifts" :key="shift.id" :value="shift.id">
                    {{ shift.name || 'Shift' }} ({{ formatShiftTime(shift) }})
                  </option>
                </select>
              </div>

              <div class="space-y-1.5 sm:col-span-1">
                <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70">Full Name *</label>
                <input
                  v-model="form.name"
                  type="text"
                  required
                  placeholder="e.g. Fatima Ahmed"
                  class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-sm text-neutral-black focus:outline-none focus:border-primary"
                />
              </div>

              <div class="space-y-1.5 sm:col-span-1">
                <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70">Email Address *</label>
                <input
                  v-model="form.email"
                  type="email"
                  required
                  placeholder="e.g. fatima@sfu.ca"
                  class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-sm text-neutral-black focus:outline-none focus:border-primary"
                />
              </div>

              <div class="space-y-1.5 sm:col-span-2">
                <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70">Phone Number (Optional)</label>
                <input
                  v-model="form.phone"
                  type="tel"
                  placeholder="e.g. (778) 123-4567"
                  class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-sm text-neutral-black focus:outline-none focus:border-primary"
                />
              </div>

              <div class="space-y-1.5 sm:col-span-2">
                <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70">Experience & Notes (Optional)</label>
                <textarea
                  v-model="form.experience"
                  rows="3"
                  placeholder="Relevant experience or scheduling notes..."
                  class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-sm text-neutral-black focus:outline-none focus:border-primary"
                />
              </div>

              <div class="sm:col-span-2 pt-2">
                <button
                  type="submit"
                  :disabled="submitting || opportunity.status === 'closed'"
                  class="w-full py-4 rounded-full text-xs font-extrabold uppercase tracking-wider text-white bg-primary hover:bg-secondary transition shadow-brand disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                >
                  <span v-if="submitting">Submitting...</span>
                  <span v-else-if="opportunity.status === 'closed'">Opportunity Closed</span>
                  <span v-else>Confirm Volunteer Signup</span>
                </button>
              </div>
            </form>
          </div>

        </div>
      </template>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowLeft, Calendar, MapPin, Sparkles, Users, Clock, AlertCircle, CheckCircle2 } from 'lucide-vue-next';
import { volunteeringService, type VolunteerOpportunity, type VolunteerShift } from '@/services/volunteeringService';
import VolunteerCapacityBadge from '@/components/volunteering/VolunteerCapacityBadge.vue';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const authStore = useAuthStore();

const opportunity = ref<VolunteerOpportunity | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const selectedTeamId = ref<number | undefined>(undefined);
const selectedShiftId = ref<number | undefined>(undefined);

const form = ref({
  name: authStore.user?.name || '',
  email: authStore.user?.email || '',
  phone: '',
  experience: '',
  notes: '',
});

const submitting = ref(false);
const formError = ref<string | null>(null);
const signupSuccess = ref(false);
const signupResponse = ref<any>(null);

const fetchDetail = async () => {
  loading.value = true;
  error.value = null;
  const slug = route.params.slug as string;

  try {
    const res = await volunteeringService.getOpportunityBySlug(slug);
    opportunity.value = res.data || res;

    if (opportunity.value?.teams && opportunity.value.teams.length > 0) {
      selectedTeamId.value = opportunity.value.teams[0].id;
    }
    if (opportunity.value?.shifts && opportunity.value.shifts.length > 0) {
      selectedShiftId.value = opportunity.value.shifts[0].id;
    }
  } catch (err: any) {
    console.error('Failed to load opportunity detail:', err);
    error.value = err?.response?.data?.message || 'Failed to load opportunity details.';
  } finally {
    loading.value = false;
  }
};

const filteredShifts = computed(() => {
  if (!opportunity.value?.shifts) return [];
  if (!selectedTeamId.value) return opportunity.value.shifts;
  return opportunity.value.shifts.filter(s => !s.team_id || s.team_id === selectedTeamId.value);
});

const selectedTeamObject = computed(() => {
  if (!selectedTeamId.value || !opportunity.value?.teams) return null;
  return opportunity.value.teams.find(t => t.id === selectedTeamId.value);
});

const selectedShiftObject = computed(() => {
  if (!selectedShiftId.value || !opportunity.value?.shifts) return null;
  return opportunity.value.shifts.find(s => s.id === selectedShiftId.value);
});

const formattedStartDate = computed(() => {
  if (!opportunity.value?.start_at) return null;
  return new Date(opportunity.value.start_at).toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
});

const formatShiftTime = (shift: VolunteerShift) => {
  if (!shift.start_at || !shift.end_at) return '';
  const start = new Date(shift.start_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  const end = new Date(shift.end_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  return `${start} - ${end}`;
};

const handleSignup = async () => {
  if (!opportunity.value) return;
  submitting.value = true;
  formError.value = null;

  try {
    const rawTeamId = selectedTeamId.value;
    const rawShiftId = selectedShiftId.value;

    const teamId = (typeof rawTeamId === 'number' && !Number.isNaN(rawTeamId)) ? rawTeamId : null;
    const shiftId = (typeof rawShiftId === 'number' && !Number.isNaN(rawShiftId)) ? rawShiftId : null;

    const payload = {
      opportunity_id: opportunity.value.id,
      team_id: teamId,
      shift_id: shiftId,
      name: form.value.name ? form.value.name.trim() : '',
      email: form.value.email ? form.value.email.trim() : '',
      phone: form.value.phone ? form.value.phone.trim() : null,
      experience: form.value.experience ? form.value.experience.trim() : null,
      notes: form.value.notes ? form.value.notes.trim() : null,
    };

    const res = await volunteeringService.submitSignup(payload);
    signupResponse.value = res.data || res;
    signupSuccess.value = true;
  } catch (err: any) {
    console.error('Signup failed:', err);
    if (err?.response?.data?.errors) {
      const errsObj = err.response.data.errors;
      const firstField = Object.keys(errsObj)[0];
      const fieldErr = errsObj[firstField];
      const message = Array.isArray(fieldErr) ? fieldErr[0] : fieldErr;
      formError.value = message || err?.response?.data?.message || 'Failed to submit volunteer signup.';
    } else {
      formError.value = err?.response?.data?.message || 'Failed to submit volunteer signup. Please check form details and try again.';
    }
  } finally {
    submitting.value = false;
  }
};

onMounted(() => {
  fetchDetail();
});
</script>
