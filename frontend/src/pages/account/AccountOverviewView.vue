<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { accountService, type AccountSummaryResponse } from '@/services/account/accountService';
import { 
  CheckCircle2, 
  XCircle, 
  Calendar, 
  ShoppingBag, 
  Heart, 
  Bell, 
  ExternalLink, 
  Sparkles,
  HelpCircle,
  Mail,
  MapPin,
  ShieldCheck,
  ChevronRight
} from 'lucide-vue-next';

const summary = ref<AccountSummaryResponse | null>(null);
const isLoading = ref<boolean>(true);
const errorMessage = ref<string | null>(null);

const loadSummary = async () => {
  isLoading.value = true;
  errorMessage.value = null;
  try {
    summary.value = await accountService.getSummary();
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load account overview.';
  } finally {
    isLoading.value = false;
  }
};

const profileCompleteness = computed(() => {
  if (!summary.value) {
    return { percentage: 0, steps: [], completedCount: 0, totalCount: 4 };
  }
  const user = summary.value.user;
  const steps = [
    { 
      label: 'Full Name', 
      completed: Boolean(user.name && user.name.trim().length > 0),
      hint: 'Display name configured' 
    },
    { 
      label: 'Email Verification', 
      completed: Boolean(user.email_verified),
      hint: user.email_verified ? 'Email address verified' : 'Action needed: verify your email' 
    },
    { 
      label: 'Community Role', 
      completed: Boolean(user.community_status),
      hint: user.community_status || 'Member profile active' 
    },
    { 
      label: 'Security & Access', 
      completed: (summary.value.applications?.length ?? 0) > 0,
      hint: 'Connected platform applications' 
    }
  ];
  const completedCount = steps.filter(s => s.completed).length;
  const percentage = Math.round((completedCount / steps.length) * 100);
  return { percentage, steps, completedCount, totalCount: steps.length };
});

onMounted(() => {
  loadSummary();
});
</script>

<template>
  <div>
    <!-- Loading State -->
    <div v-if="isLoading" class="py-16 flex flex-col items-center justify-center space-y-4">
      <div class="h-10 w-10 border-4 border-primary/20 border-t-primary rounded-full animate-spin" />
      <p class="text-xs font-bold text-neutral-black/60 uppercase tracking-wider">Loading member overview...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="bg-red-50 border border-red-200 rounded-2xl p-6 text-center text-red-700">
      <p class="font-bold text-sm">{{ errorMessage }}</p>
      <button @click="loadSummary" class="mt-3 px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-secondary transition-all">
        Retry
      </button>
    </div>

    <!-- Summary Content -->
    <div v-else-if="summary" class="space-y-8">
      <!-- 1. High Level Stat Counters (4 Columns) -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft hover:shadow-premium transition-all">
          <div class="flex items-center justify-between text-primary mb-2">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60">Events</span>
            <Calendar class="h-4 w-4" />
          </div>
          <div class="text-2xl font-black text-primary">{{ summary.counts.upcoming_events }}</div>
          <p class="text-[10px] text-neutral-black/50 mt-1">Upcoming registrations</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft hover:shadow-premium transition-all">
          <div class="flex items-center justify-between text-primary mb-2">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60">Orders</span>
            <ShoppingBag class="h-4 w-4" />
          </div>
          <div class="text-2xl font-black text-primary">{{ summary.counts.orders }}</div>
          <p class="text-[10px] text-neutral-black/50 mt-1">Store purchases</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft hover:shadow-premium transition-all">
          <div class="flex items-center justify-between text-primary mb-2">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60">Volunteer</span>
            <Heart class="h-4 w-4" />
          </div>
          <div class="text-2xl font-black text-primary">{{ summary.counts.volunteer_shifts }}</div>
          <p class="text-[10px] text-neutral-black/50 mt-1">Assigned shifts</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft hover:shadow-premium transition-all">
          <div class="flex items-center justify-between text-primary mb-2">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60">Notifications</span>
            <Bell class="h-4 w-4" />
          </div>
          <div class="text-2xl font-black text-primary">{{ summary.counts.unread_notifications }}</div>
          <p class="text-[10px] text-neutral-black/50 mt-1">Unread alerts</p>
        </div>
      </div>

      <!-- 2. Profile Completeness Widget (ACCOUNT-02) -->
      <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-black">
              <ShieldCheck class="h-5 w-5" />
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Profile Completeness</h2>
                <span 
                  :class="[
                    'text-[10px] font-extrabold px-2 py-0.5 rounded-full border',
                    profileCompleteness.percentage === 100 
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                      : 'bg-primary/10 text-primary border-primary/20'
                  ]"
                >
                  {{ profileCompleteness.percentage }}% Complete
                </span>
              </div>
              <p class="text-[11px] text-neutral-black/60 font-medium">
                {{ profileCompleteness.percentage === 100 
                  ? 'All essential profile credentials and verifications are in place.' 
                  : 'Complete all steps below to unlock full MSA platform capabilities.' 
                }}
              </p>
            </div>
          </div>

          <router-link 
            v-if="profileCompleteness.percentage < 100"
            to="/account/profile" 
            class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline self-start sm:self-auto"
          >
            Complete Profile
            <ChevronRight class="h-3.5 w-3.5" />
          </router-link>
        </div>

        <!-- Progress Bar -->
        <div class="w-full bg-neutral-background h-2.5 rounded-full overflow-hidden mb-5 border border-neutral-ivory">
          <div 
            class="h-full bg-gradient-to-r from-primary to-secondary transition-all duration-500 rounded-full"
            :style="{ width: `${profileCompleteness.percentage}%` }"
          />
        </div>

        <!-- Checklist Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div 
            v-for="step in profileCompleteness.steps" 
            :key="step.label"
            :class="[
              'p-3 rounded-2xl border flex items-center gap-2.5 transition-all',
              step.completed 
                ? 'bg-emerald-50/50 border-emerald-200/80 text-emerald-950' 
                : 'bg-neutral-background/60 border-neutral-ivory text-neutral-black/70'
            ]"
          >
            <CheckCircle2 v-if="step.completed" class="h-4 w-4 text-emerald-600 shrink-0" />
            <XCircle v-else class="h-4 w-4 text-amber-500 shrink-0" />
            <div class="min-w-0">
              <span class="block text-[11px] font-extrabold truncate">{{ step.label }}</span>
              <span class="block text-[10px] text-neutral-black/50 truncate">{{ step.hint }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Identity & Access Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Identity Card -->
        <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-4">
              <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Identity Info</h2>
              <span 
                :class="[
                  'inline-flex items-center gap-1 text-[10px] font-extrabold px-2.5 py-1 rounded-full border',
                  summary.user.email_verified 
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                    : 'bg-amber-50 text-amber-700 border-amber-200'
                ]"
              >
                <CheckCircle2 v-if="summary.user.email_verified" class="h-3 w-3" />
                <XCircle v-else class="h-3 w-3" />
                {{ summary.user.email_verified ? 'Verified Email' : 'Verification Needed' }}
              </span>
            </div>

            <div class="space-y-3 text-xs">
              <div>
                <span class="text-neutral-black/50 font-bold block text-[10px] uppercase">Community Role</span>
                <span class="font-extrabold text-primary text-sm">{{ summary.user.community_status }}</span>
              </div>

              <div>
                <span class="text-neutral-black/50 font-bold block text-[10px] uppercase">Member Since</span>
                <span class="font-bold text-neutral-black">{{ summary.user.member_since || '2026' }}</span>
              </div>

              <div>
                <span class="text-neutral-black/50 font-bold block text-[10px] uppercase">Registered Email</span>
                <span class="font-medium text-neutral-black/80 font-mono text-[11px]">{{ summary.user.email }}</span>
              </div>
            </div>
          </div>

          <div class="pt-6 border-t border-neutral-ivory mt-6 flex items-center justify-between">
            <router-link to="/account/profile" class="text-xs font-bold text-primary hover:underline">
              Edit Profile Info &rarr;
            </router-link>
          </div>
        </div>

        <!-- Accessible Applications Grid (2 Cols) -->
        <div class="lg:col-span-2 bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
          <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-4">
            <div>
              <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">My Platform Applications</h2>
              <p class="text-[11px] text-neutral-black/60 font-medium">Subsystems accessible via your MSA account</p>
            </div>
            <Sparkles class="h-4 w-4 text-primary" />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div 
              v-for="app in summary.applications" 
              :key="app.slug"
              class="p-4 rounded-2xl border border-neutral-ivory bg-neutral-background/50 hover:bg-white hover:border-primary/30 transition-all flex flex-col justify-between group"
            >
              <div>
                <div class="flex items-center justify-between mb-1">
                  <h3 class="font-extrabold text-xs text-neutral-black group-hover:text-primary transition-colors">
                    {{ app.title }}
                  </h3>
                  <span v-if="app.is_admin" class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase bg-primary/10 text-primary border border-primary/20">
                    Admin
                  </span>
                </div>
                <p class="text-[11px] text-neutral-black/60 font-medium leading-relaxed">
                  {{ app.description }}
                </p>
              </div>

              <div class="mt-4 pt-2 flex items-center justify-between">
                <router-link 
                  :to="app.path" 
                  class="inline-flex items-center gap-1 text-[11px] font-extrabold text-primary hover:underline"
                >
                  Launch Application
                  <ExternalLink class="h-3 w-3" />
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 4. Volunteer Highlights & Support Grid (ACCOUNT-04) -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Volunteer Summary -->
        <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
          <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-4">
            <div class="flex items-center gap-2">
              <Heart class="h-4 w-4 text-primary" />
              <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Volunteering Overview</h2>
            </div>
            <router-link to="/volunteer/profile" class="text-xs font-bold text-primary hover:underline">
              Volunteer Portal &rarr;
            </router-link>
          </div>

          <div v-if="summary.volunteer" class="space-y-4">
            <div class="flex items-center justify-between bg-primary/5 border border-primary/15 rounded-2xl p-4">
              <div>
                <span class="text-[10px] font-extrabold uppercase text-primary tracking-wider">Volunteer Profile Status</span>
                <p class="text-sm font-black text-primary capitalize">{{ summary.volunteer.status }}</p>
              </div>
              <div class="text-right">
                <span class="text-[10px] font-extrabold uppercase text-primary tracking-wider">Shifts Completed</span>
                <p class="text-sm font-black text-primary">{{ summary.volunteer.completed_shifts_count }}</p>
              </div>
            </div>

            <div v-if="summary.volunteer.skills.length > 0">
              <span class="text-[10px] font-extrabold uppercase text-neutral-black/50 block mb-2">Registered Skills</span>
              <div class="flex flex-wrap gap-1.5">
                <span 
                  v-for="skill in summary.volunteer.skills" 
                  :key="skill"
                  class="px-2.5 py-1 bg-neutral-background rounded-xl text-[10px] font-bold text-neutral-black/80 border border-neutral-ivory"
                >
                  {{ skill }}
                </span>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-6 text-neutral-black/50">
            <p class="text-xs font-bold">Not registered as an active volunteer yet.</p>
            <router-link to="/volunteer" class="mt-2 inline-block px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-secondary transition-all">
              Become a Volunteer
            </router-link>
          </div>
        </div>

        <!-- Help & Support Card (ACCOUNT-04) -->
        <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-4">
              <div class="flex items-center gap-2">
                <HelpCircle class="h-4 w-4 text-primary" />
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Need Help & Support?</h2>
              </div>
              <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20">
                Support Desk
              </span>
            </div>

            <p class="text-xs text-neutral-black/70 leading-relaxed mb-4">
              Have questions regarding event registrations, bookstore orders, volunteer hours, or platform access? Our executive team is here to assist.
            </p>

            <div class="space-y-3">
              <div class="flex items-start gap-3 p-3 rounded-2xl bg-neutral-background/60 border border-neutral-ivory text-xs">
                <Mail class="h-4 w-4 text-primary shrink-0 mt-0.5" />
                <div>
                  <span class="font-extrabold text-neutral-black block text-[11px]">Direct Support Email</span>
                  <a href="mailto:support@sfumsa.ca" class="text-primary font-bold hover:underline">support@sfumsa.ca</a>
                  <span class="text-neutral-black/40 text-[10px] block mt-0.5">Response typically within 24-48 hours</span>
                </div>
              </div>

              <div class="flex items-start gap-3 p-3 rounded-2xl bg-neutral-background/60 border border-neutral-ivory text-xs">
                <MapPin class="h-4 w-4 text-primary shrink-0 mt-0.5" />
                <div>
                  <span class="font-extrabold text-neutral-black block text-[11px]">Campus Headquarters</span>
                  <span class="text-neutral-black/70 font-medium">Student Union Building (SUB), Room 1420</span>
                  <span class="text-neutral-black/40 text-[10px] block mt-0.5">SFU Burnaby Campus</span>
                </div>
              </div>
            </div>
          </div>

          <div class="pt-6 border-t border-neutral-ivory mt-6 flex items-center justify-between">
            <a 
              href="mailto:support@sfumsa.ca?subject=MSA%20Platform%20Support%20Inquiry" 
              class="text-xs font-bold text-primary hover:underline inline-flex items-center gap-1"
            >
              Contact Support Team &rarr;
            </a>
            <router-link to="/about" class="text-xs font-bold text-neutral-black/60 hover:text-primary transition-colors">
              About SFU MSA
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
