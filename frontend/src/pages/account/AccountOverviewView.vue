<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { accountService, type AccountSummaryResponse } from '@/services/account/accountService';
import { 
  CheckCircle2, 
  XCircle, 
  Calendar, 
  ShoppingBag, 
  Heart, 
  BookOpen, 
  Bell, 
  ExternalLink, 
  Sparkles
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
      <!-- 1. High Level Stat Counters -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
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
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60">Courses</span>
            <BookOpen class="h-4 w-4" />
          </div>
          <div class="text-2xl font-black text-primary">{{ summary.counts.courses }}</div>
          <p class="text-[10px] text-neutral-black/50 mt-1">Enrolled academy courses</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft hover:shadow-premium transition-all col-span-2 md:col-span-1">
          <div class="flex items-center justify-between text-primary mb-2">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60">Unread</span>
            <Bell class="h-4 w-4" />
          </div>
          <div class="text-2xl font-black text-primary">{{ summary.counts.unread_notifications }}</div>
          <p class="text-[10px] text-neutral-black/50 mt-1">Unread notifications</p>
        </div>
      </div>

      <!-- 2. Identity & Access Grid -->
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

      <!-- 3. Learning & Volunteer Highlights -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
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

        <!-- Academy Summary -->
        <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
          <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-4">
            <div class="flex items-center gap-2">
              <BookOpen class="h-4 w-4 text-primary" />
              <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Dawah Academy Progress</h2>
            </div>
            <router-link to="/academy" class="text-xs font-bold text-primary hover:underline">
              Course Player &rarr;
            </router-link>
          </div>

          <div class="grid grid-cols-3 gap-3 mb-4 text-center">
            <div class="p-3 bg-neutral-background rounded-2xl border border-neutral-ivory">
              <div class="text-lg font-black text-primary">{{ summary.academy.enrolled_courses }}</div>
              <span class="text-[9px] font-extrabold uppercase text-neutral-black/60">Enrolled</span>
            </div>
            <div class="p-3 bg-neutral-background rounded-2xl border border-neutral-ivory">
              <div class="text-lg font-black text-primary">{{ summary.academy.completed_courses }}</div>
              <span class="text-[9px] font-extrabold uppercase text-neutral-black/60">Completed</span>
            </div>
            <div class="p-3 bg-neutral-background rounded-2xl border border-neutral-ivory">
              <div class="text-lg font-black text-primary">{{ summary.academy.certificates }}</div>
              <span class="text-[9px] font-extrabold uppercase text-neutral-black/60">Certificates</span>
            </div>
          </div>

          <div v-if="summary.academy.recent_courses.length > 0">
            <span class="text-[10px] font-extrabold uppercase text-neutral-black/50 block mb-2">Recent Courses</span>
            <div class="space-y-2">
              <div 
                v-for="enrollment in summary.academy.recent_courses" 
                :key="enrollment.id"
                class="p-2.5 rounded-xl border border-neutral-ivory bg-neutral-background/40 flex items-center justify-between text-xs"
              >
                <span class="font-bold text-neutral-black truncate">{{ enrollment.course?.title || 'Course' }}</span>
                <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase bg-primary/10 text-primary border border-primary/20">
                  {{ enrollment.status }}
                </span>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-4 text-neutral-black/50">
            <p class="text-xs font-bold">No active academy course enrollments.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
