<template>
  <div class="volunteer-profile-page min-h-screen bg-neutral-background text-neutral-black pt-24 sm:pt-32 pb-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto space-y-8">
      
      <!-- HERO SECTION -->
      <section class="relative rounded-3xl bg-white border border-neutral-ivory p-6 sm:p-10 shadow-premium overflow-hidden text-center sm:text-left">
        <!-- Background Decorative Elements -->
        <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-accent-gold/20 blur-3xl pointer-events-none" />
        <div class="absolute -left-16 -bottom-16 w-64 h-64 rounded-full bg-secondary/10 blur-3xl pointer-events-none" />

        <div class="relative z-10 max-w-3xl space-y-4">
          <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-widest bg-primary/10 text-primary border border-primary/20">
            <Heart class="w-3.5 h-3.5 text-secondary" />
            <span>SFU MSA Volunteering</span>
          </div>

          <h1 class="text-3xl sm:text-4xl font-display font-extrabold text-primary leading-tight tracking-tight">
            My Volunteer Profile & Skills
          </h1>

          <p class="text-sm sm:text-base text-neutral-black/70 leading-relaxed font-sans">
            Configure your capabilities, interests, and availability for smart opportunity matching and track your community service milestones.
          </p>

          <div class="flex flex-wrap items-center gap-3 pt-2 justify-center sm:justify-start">
            <router-link
              to="/volunteer"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-xs font-extrabold uppercase tracking-wider text-white bg-primary hover:bg-secondary transition-all shadow-brand hover:shadow-premium"
            >
              <Search class="w-4 h-4" />
              <span>Browse Opportunities</span>
            </router-link>

            <router-link
              to="/volunteer/my-history"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-xs font-extrabold uppercase tracking-wider text-primary border border-primary/30 hover:bg-primary/5 transition-all"
            >
              <Clock class="w-4 h-4 text-primary" />
              <span>View Service History</span>
            </router-link>
          </div>
        </div>
      </section>

      <!-- Loading / Error States -->
      <div v-if="loading" class="bg-white rounded-3xl border border-neutral-ivory p-12 text-center space-y-4 shadow-soft">
        <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto" />
        <p class="text-sm font-bold text-neutral-black/70">Loading your volunteer profile...</p>
      </div>

      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-3xl p-6 text-center space-y-3">
        <AlertCircle class="w-10 h-10 text-red-600 mx-auto" />
        <p class="text-sm font-bold text-red-900">{{ error }}</p>
      </div>

      <div v-else class="space-y-8">

        <!-- Profile Completion Card -->
        <div class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 shadow-soft flex flex-col sm:flex-row items-center justify-between gap-6">
          <div class="space-y-2 text-center sm:text-left">
            <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider bg-primary/10 text-primary border border-primary/20">
              <Sparkles class="w-3.5 h-3.5 text-secondary" />
              <span>Profile Completeness</span>
            </div>
            <h2 class="text-2xl font-display font-extrabold text-primary">
              {{ profile?.profile_completion_percentage ?? 0 }}% Complete
            </h2>
            <p class="text-xs sm:text-sm text-neutral-black/70 max-w-lg">
              Complete your skills, interests, and availability to receive better matched volunteer recommendations.
            </p>
          </div>
          <div class="w-32 h-32 flex items-center justify-center relative shrink-0">
            <svg class="w-28 h-28 transform -rotate-90">
              <circle cx="56" cy="56" r="48" stroke="currentColor" stroke-width="8" class="text-neutral-ivory" fill="transparent"/>
              <circle
                cx="56"
                cy="56"
                r="48"
                stroke="currentColor"
                stroke-width="8"
                class="text-primary transition-all duration-700"
                fill="transparent"
                :stroke-dasharray="301.59"
                :stroke-dashoffset="301.59 - (301.59 * (profile?.profile_completion_percentage ?? 0)) / 100"
              />
            </svg>
            <span class="absolute text-lg font-extrabold text-primary font-display">{{ profile?.profile_completion_percentage ?? 0 }}%</span>
          </div>
        </div>

        <!-- VMS-8 Recognition & Progression Card -->
        <div v-if="recognitionData" class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 space-y-6 shadow-soft">
          <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-neutral-ivory pb-4">
            <div>
              <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-accent-gold/20 text-neutral-black border border-accent-gold/40 rounded-full text-xs font-extrabold uppercase tracking-wider">
                <Trophy class="w-3.5 h-3.5 text-secondary" />
                <span>Recognition & Achievements</span>
              </div>
              <h3 class="text-xl font-display font-extrabold text-primary mt-2">
                {{ recognitionData.summary.earned_count }} Achievements Earned • {{ recognitionData.summary.total_points }} Points
              </h3>
            </div>
            <div class="flex items-center gap-6 text-sm text-neutral-black/80">
              <div class="text-right">
                <div class="text-xs text-neutral-black/50 font-bold uppercase tracking-wider">Verified Service Hours</div>
                <div class="font-extrabold text-primary text-xl font-display">{{ recognitionData.summary.total_service_hours }} hrs</div>
              </div>
              <div class="text-right">
                <div class="text-xs text-neutral-black/50 font-bold uppercase tracking-wider">Status</div>
                <div class="font-extrabold text-secondary capitalize text-sm">{{ recognitionData.summary.retention_status }}</div>
              </div>
            </div>
          </div>

          <!-- Next Milestone Progress (If Any) -->
          <div v-if="recognitionData.next_milestone" class="bg-neutral-background border border-neutral-ivory rounded-2xl p-4 space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="font-extrabold text-primary">Next Service Milestone: {{ recognitionData.next_milestone.threshold_hours }} Hours</span>
              <span class="text-neutral-black/70 font-medium">{{ recognitionData.next_milestone.current_hours }} / {{ recognitionData.next_milestone.threshold_hours }} hrs ({{ recognitionData.next_milestone.remaining_hours }} hrs remaining)</span>
            </div>
            <div class="w-full bg-neutral-ivory rounded-full h-3 overflow-hidden">
              <div
                class="bg-gradient-to-r from-primary to-secondary h-3 rounded-full transition-all duration-500"
                :style="{ width: `${recognitionData.next_milestone.progress_percentage}%` }"
              ></div>
            </div>
          </div>

          <!-- Earned Achievements Grid -->
          <div v-if="recognitionData.achievements.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="ua in recognitionData.achievements"
              :key="ua.id"
              class="bg-neutral-background border border-neutral-ivory rounded-2xl p-4 flex items-start gap-3 hover:border-primary/40 transition-colors"
            >
              <div class="w-10 h-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
                <Award class="w-5 h-5" />
              </div>
              <div class="space-y-1 min-w-0">
                <div class="font-extrabold text-primary text-sm truncate">{{ ua.achievement?.name }}</div>
                <div class="text-xs text-neutral-black/70 line-clamp-2">{{ ua.achievement?.description }}</div>
                <div class="text-[10px] text-secondary font-mono font-bold">
                  +{{ ua.achievement?.points }} pts • {{ new Date(ua.awarded_at).toLocaleDateString() }}
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-4 text-xs font-bold text-neutral-black/50">
            Complete volunteering opportunities to unlock your first achievement badge!
          </div>
        </div>

        <!-- Invitations Section (If Any) -->
        <div v-if="invitations.length > 0" class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 space-y-4 shadow-soft">
          <div class="flex items-center justify-between border-b border-neutral-ivory pb-3">
            <h3 class="text-lg font-display font-extrabold text-primary flex items-center gap-2">
              <Mail class="w-5 h-5 text-secondary" />
              <span>Special Volunteer Invitations ({{ invitations.length }})</span>
            </h3>
          </div>
          <div class="space-y-3">
            <div
              v-for="inv in invitations"
              :key="inv.id"
              class="bg-neutral-background border border-neutral-ivory rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
            >
              <div>
                <div class="font-bold text-primary">{{ inv.opportunity?.title }}</div>
                <div class="text-xs text-neutral-black/60 mt-1">
                  Invited by {{ inv.inviter?.name ?? 'Coordinator' }} • Status: <span class="capitalize font-bold" :class="inv.status === 'pending' ? 'text-amber-600' : 'text-emerald-600'">{{ inv.status }}</span>
                </div>
                <p v-if="inv.message" class="text-xs text-neutral-black/80 italic mt-2">"{{ inv.message }}"</p>
              </div>
              <div v-if="inv.status === 'pending'" class="flex items-center gap-2">
                <button
                  @click="acceptInvitation(inv.uuid)"
                  class="px-4 py-2 bg-primary hover:bg-secondary text-white text-xs font-extrabold uppercase tracking-wider rounded-full transition-all shadow-brand cursor-pointer"
                >
                  Accept & Register
                </button>
                <button
                  @click="declineInvitation(inv.uuid)"
                  class="px-4 py-2 bg-white hover:bg-neutral-ivory text-neutral-black/70 border border-neutral-ivory text-xs font-extrabold uppercase tracking-wider rounded-full transition-all cursor-pointer"
                >
                  Decline
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Form & Sections -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          
          <!-- Left Column: Core Profile Details & Preferences -->
          <div class="lg:col-span-1 space-y-6">
            <div class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft space-y-4">
              <h3 class="text-lg font-display font-extrabold text-primary border-b border-neutral-ivory pb-3">Basic Information</h3>
              
              <form @submit.prevent="saveBasicProfile" class="space-y-4 text-sm">
                <div>
                  <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Experience Level</label>
                  <select
                    v-model="profileForm.experience_level"
                    class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2.5 text-neutral-black focus:outline-none focus:border-primary text-xs"
                  >
                    <option value="beginner">Beginner (New Volunteer)</option>
                    <option value="intermediate">Intermediate (Some Experience)</option>
                    <option value="advanced">Advanced (Experienced Leader)</option>
                    <option value="expert">Expert (Subject Specialist)</option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Years of Experience</label>
                  <input
                    type="number"
                    step="0.5"
                    v-model.number="profileForm.years_experience"
                    class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2.5 text-neutral-black focus:outline-none focus:border-primary text-xs"
                  />
                </div>

                <div>
                  <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Preferred Hours / Week</label>
                  <input
                    type="number"
                    v-model.number="profileForm.preferred_hours_per_week"
                    placeholder="e.g. 5"
                    class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2.5 text-neutral-black focus:outline-none focus:border-primary text-xs"
                  />
                </div>

                <div>
                  <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Short Bio</label>
                  <textarea
                    v-model="profileForm.bio"
                    rows="3"
                    placeholder="Tell coordinators about your background..."
                    class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2.5 text-neutral-black focus:outline-none focus:border-primary text-xs"
                  ></textarea>
                </div>

                <div>
                  <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Available Days</label>
                  <div class="grid grid-cols-2 gap-2 text-xs">
                    <label v-for="day in daysOfWeek" :key="day" class="flex items-center gap-2 cursor-pointer text-neutral-black/80 font-medium">
                      <input
                        type="checkbox"
                        :value="day"
                        v-model="profileForm.availability_days"
                        class="rounded border-neutral-ivory text-primary focus:ring-0"
                      />
                      <span class="capitalize">{{ day }}</span>
                    </label>
                  </div>
                </div>

                <button
                  type="submit"
                  :disabled="saving"
                  class="w-full py-3 bg-primary hover:bg-secondary text-white font-extrabold text-xs uppercase tracking-wider rounded-full transition-all shadow-brand cursor-pointer disabled:opacity-50"
                >
                  {{ saving ? 'Saving...' : 'Save Profile Details' }}
                </button>
              </form>
            </div>
          </div>

          <!-- Right Column: Skills, Interests & Experience -->
          <div class="lg:col-span-2 space-y-6">
            
            <!-- Skills Section -->
            <div class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft space-y-4">
              <div class="flex items-center justify-between border-b border-neutral-ivory pb-3">
                <div>
                  <h3 class="text-lg font-display font-extrabold text-primary">My Skills</h3>
                  <p class="text-xs text-neutral-black/60">Select skills you possess to match required opportunity criteria.</p>
                </div>
              </div>

              <!-- Active Attached Skills -->
              <div class="flex flex-wrap gap-2 min-h-[40px] items-center">
                <div
                  v-for="s in profile?.skills"
                  :key="s.id"
                  class="inline-flex items-center gap-2 px-3 py-1.5 bg-primary/10 border border-primary/20 text-primary rounded-xl text-xs font-bold"
                >
                  <span>{{ s.name }}</span>
                  <span class="text-[10px] px-1.5 py-0.5 bg-primary text-white rounded font-extrabold uppercase">
                    {{ s.pivot?.proficiency_level ?? 'intermediate' }}
                  </span>
                  <button @click="detachSkill(s.id)" class="text-neutral-black/40 hover:text-red-600 font-bold ml-1 cursor-pointer">×</button>
                </div>
                <p v-if="!profile?.skills?.length" class="text-xs text-neutral-black/50 italic">No skills attached yet.</p>
              </div>

              <!-- Add Skill Form -->
              <div class="pt-2 flex flex-col sm:flex-row items-center gap-2 text-sm">
                <select v-model="selectedSkillId" class="flex-1 w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary text-xs">
                  <option :value="null">-- Select a skill --</option>
                  <option v-for="sk in availableSkills" :key="sk.id" :value="sk.id">{{ sk.name }} ({{ sk.category }})</option>
                </select>
                <select v-model="selectedProficiency" class="w-full sm:w-36 bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary text-xs">
                  <option value="beginner">Beginner</option>
                  <option value="intermediate">Intermediate</option>
                  <option value="advanced">Advanced</option>
                  <option value="expert">Expert</option>
                </select>
                <button
                  @click="attachSkill"
                  :disabled="!selectedSkillId"
                  class="w-full sm:w-auto px-4 py-2 bg-primary hover:bg-secondary disabled:opacity-50 text-white text-xs font-extrabold uppercase tracking-wider rounded-full transition-all shadow-brand cursor-pointer whitespace-nowrap"
                >
                  + Add Skill
                </button>
              </div>
            </div>

            <!-- Interests Section -->
            <div class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft space-y-4">
              <div class="flex items-center justify-between border-b border-neutral-ivory pb-3">
                <div>
                  <h3 class="text-lg font-display font-extrabold text-primary">Volunteering Interests</h3>
                  <p class="text-xs text-neutral-black/60">Select topics & areas you are passionate about.</p>
                </div>
              </div>

              <!-- Active Attached Interests -->
              <div class="flex flex-wrap gap-2 min-h-[40px] items-center">
                <div
                  v-for="i in profile?.interests"
                  :key="i.id"
                  class="inline-flex items-center gap-2 px-3 py-1.5 bg-secondary/10 border border-secondary/20 text-secondary rounded-xl text-xs font-bold"
                >
                  <span>{{ i.name }}</span>
                  <button @click="detachInterest(i.id)" class="text-neutral-black/40 hover:text-red-600 font-bold ml-1 cursor-pointer">×</button>
                </div>
                <p v-if="!profile?.interests?.length" class="text-xs text-neutral-black/50 italic">No interests attached yet.</p>
              </div>

              <!-- Add Interest Form -->
              <div class="pt-2 flex items-center gap-2 text-sm">
                <select v-model="selectedInterestId" class="flex-1 bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary text-xs">
                  <option :value="null">-- Select an interest --</option>
                  <option v-for="it in availableInterests" :key="it.id" :value="it.id">{{ it.name }}</option>
                </select>
                <button
                  @click="attachInterest"
                  :disabled="!selectedInterestId"
                  class="px-4 py-2 bg-secondary hover:bg-primary disabled:opacity-50 text-white text-xs font-extrabold uppercase tracking-wider rounded-full transition-all shadow-brand cursor-pointer whitespace-nowrap"
                >
                  + Add Interest
                </button>
              </div>
            </div>

            <!-- External Experiences Section -->
            <div class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft space-y-4">
              <div class="flex items-center justify-between border-b border-neutral-ivory pb-3">
                <div>
                  <h3 class="text-lg font-display font-extrabold text-primary">External Volunteer Experiences</h3>
                  <p class="text-xs text-neutral-black/60">List prior community or organization roles.</p>
                </div>
                <button
                  @click="showExperienceModal = true"
                  class="px-4 py-2 bg-neutral-background hover:bg-neutral-ivory border border-neutral-ivory text-primary text-xs font-extrabold uppercase tracking-wider rounded-full transition-all cursor-pointer"
                >
                  + Add Experience
                </button>
              </div>

              <div class="space-y-3">
                <div
                  v-for="exp in profile?.experiences"
                  :key="exp.id"
                  class="bg-neutral-background border border-neutral-ivory rounded-2xl p-4 flex items-start justify-between gap-4"
                >
                  <div>
                    <h4 class="font-extrabold text-primary text-sm">{{ exp.role_title }}</h4>
                    <div class="text-xs text-secondary font-bold">{{ exp.organization }}</div>
                    <p v-if="exp.description" class="text-xs text-neutral-black/70 mt-2">{{ exp.description }}</p>
                  </div>
                  <button @click="deleteExperience(exp.id)" class="text-red-600 hover:text-red-700 text-xs font-extrabold cursor-pointer">Delete</button>
                </div>

                <p v-if="!profile?.experiences?.length" class="text-xs text-neutral-black/50 italic py-2 text-center">
                  No external volunteer experiences added yet.
                </p>
              </div>
            </div>

          </div>

        </div>

      </div>

    </div>

    <!-- Add Experience Modal -->
    <div v-if="showExperienceModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white border border-neutral-ivory rounded-3xl max-w-md w-full p-6 space-y-4 text-neutral-black shadow-premium">
        <h3 class="text-lg font-display font-extrabold text-primary border-b border-neutral-ivory pb-2">Add External Experience</h3>
        
        <form @submit.prevent="saveExperience" class="space-y-4 text-xs">
          <div>
            <label class="block font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Organization Name *</label>
            <input v-model="expForm.organization" required class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary" />
          </div>
          <div>
            <label class="block font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Role Title *</label>
            <input v-model="expForm.role_title" required class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary" />
          </div>
          <div>
            <label class="block font-extrabold uppercase tracking-wider text-neutral-black/70 mb-1">Description</label>
            <textarea v-model="expForm.description" rows="3" class="w-full bg-white border border-neutral-ivory rounded-xl px-3 py-2 text-neutral-black focus:outline-none focus:border-primary"></textarea>
          </div>
          <div class="flex items-center justify-end gap-3 pt-2">
            <button type="button" @click="showExperienceModal = false" class="px-4 py-2 bg-neutral-background hover:bg-neutral-ivory border border-neutral-ivory rounded-full font-extrabold text-xs uppercase tracking-wider text-neutral-black/70 cursor-pointer">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-primary hover:bg-secondary text-white rounded-full font-extrabold text-xs uppercase tracking-wider shadow-brand cursor-pointer">Save</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Heart, Sparkles, Trophy, Award, Mail, Search, Clock, AlertCircle } from 'lucide-vue-next';
import { volunteeringService, type VolunteerProfile, type Skill, type Interest, type VolunteerInvitation, type VolunteerRecognitionData } from '@/services/volunteeringService';

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);

const profile = ref<VolunteerProfile | null>(null);
const allSkills = ref<Skill[]>([]);
const allInterests = ref<Interest[]>([]);
const invitations = ref<VolunteerInvitation[]>([]);
const recognitionData = ref<VolunteerRecognitionData | null>(null);

const selectedSkillId = ref<number | null>(null);
const selectedProficiency = ref<string>('intermediate');
const selectedInterestId = ref<number | null>(null);
const showExperienceModal = ref(false);

const daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

const profileForm = ref({
  bio: '',
  experience_level: 'beginner',
  years_experience: 0,
  preferred_hours_per_week: 5,
  availability_days: [] as string[],
  privacy_level: 'private',
});

const expForm = ref({
  organization: '',
  role_title: '',
  description: '',
});

const availableSkills = computed(() => {
  const attachedIds = new Set(profile.value?.skills?.map((s) => s.id) ?? []);
  return allSkills.value.filter((s) => !attachedIds.has(s.id));
});

const availableInterests = computed(() => {
  const attachedIds = new Set(profile.value?.interests?.map((i) => i.id) ?? []);
  return allInterests.value.filter((i) => !attachedIds.has(i.id));
});

async function loadData() {
  loading.value = true;
  error.value = null;
  try {
    const [pRes, skRes, itRes, invRes, recRes] = await Promise.all([
      volunteeringService.getProfile(),
      volunteeringService.getActiveSkills(),
      volunteeringService.getActiveInterests(),
      volunteeringService.getInvitations(),
      volunteeringService.getRecognition(),
    ]);

    profile.value = pRes.data;
    allSkills.value = skRes.data ?? [];
    allInterests.value = itRes.data ?? [];
    invitations.value = invRes.data ?? [];
    recognitionData.value = recRes ?? null;

    if (profile.value) {
      profileForm.value = {
        bio: profile.value.bio ?? '',
        experience_level: profile.value.experience_level ?? 'beginner',
        years_experience: profile.value.years_experience ?? 0,
        preferred_hours_per_week: profile.value.preferred_hours_per_week ?? 5,
        availability_days: profile.value.availability_days ?? [],
        privacy_level: profile.value.privacy_level ?? 'private',
      };
    }
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load volunteer profile data.';
  } finally {
    loading.value = false;
  }
}

async function saveBasicProfile() {
  saving.value = true;
  try {
    const res = await volunteeringService.updateProfile(profileForm.value);
    profile.value = res.data;
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to save profile');
  } finally {
    saving.value = false;
  }
}

async function attachSkill() {
  if (!selectedSkillId.value) return;
  try {
    const res = await volunteeringService.attachSkill(selectedSkillId.value, selectedProficiency.value);
    profile.value = res.data;
    selectedSkillId.value = null;
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to attach skill');
  }
}

async function detachSkill(skillId: number) {
  try {
    const res = await volunteeringService.detachSkill(skillId);
    profile.value = res.data;
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to detach skill');
  }
}

async function attachInterest() {
  if (!selectedInterestId.value) return;
  try {
    const res = await volunteeringService.attachInterest(selectedInterestId.value);
    profile.value = res.data;
    selectedInterestId.value = null;
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to attach interest');
  }
}

async function detachInterest(interestId: number) {
  try {
    const res = await volunteeringService.detachInterest(interestId);
    profile.value = res.data;
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to detach interest');
  }
}

async function saveExperience() {
  if (!expForm.value.organization || !expForm.value.role_title) return;
  try {
    await volunteeringService.addExperience(expForm.value);
    showExperienceModal.value = false;
    expForm.value = { organization: '', role_title: '', description: '' };
    const pRes = await volunteeringService.getProfile();
    profile.value = pRes.data;
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to add experience');
  }
}

async function deleteExperience(expId: number) {
  try {
    await volunteeringService.deleteExperience(expId);
    const pRes = await volunteeringService.getProfile();
    profile.value = pRes.data;
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to delete experience');
  }
}

async function acceptInvitation(uuid: string) {
  try {
    await volunteeringService.acceptInvitation(uuid);
    alert('Invitation accepted!');
    await loadData();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to accept invitation');
  }
}

async function declineInvitation(uuid: string) {
  try {
    await volunteeringService.declineInvitation(uuid);
    await loadData();
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to decline invitation');
  }
}

onMounted(() => {
  loadData();
});
</script>
