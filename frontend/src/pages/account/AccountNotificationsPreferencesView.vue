<script setup lang="ts">
import { onMounted } from 'vue';
import { useNotificationsStore } from '@/stores/notifications';
import { Bell, Mail, BookOpen, Megaphone, Calendar, Award, CheckCircle } from 'lucide-vue-next';

const notificationsStore = useNotificationsStore();

onMounted(() => {
  notificationsStore.fetchPreferences();
});

const handleToggle = (key: string, value: boolean) => {
  notificationsStore.updatePreferences({ [key]: value });
};
</script>

<template>
  <div class="space-y-6">
    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft space-y-6">
      <div class="flex items-center justify-between border-b border-neutral-ivory pb-5">
        <div>
          <h2 class="text-xl font-display font-bold text-primary tracking-tight">Notification Preferences</h2>
          <p class="text-xs text-neutral-black/70 mt-1">
            Control which platform notifications you receive and choose your preferred delivery channels.
          </p>
        </div>
        <div class="p-3 bg-primary/10 rounded-2xl text-primary">
          <Bell class="h-6 w-6" />
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="notificationsStore.loading && !notificationsStore.preferences" class="py-12 text-center space-y-3">
        <div class="h-8 w-8 border-4 border-primary/20 border-t-primary rounded-full animate-spin mx-auto" />
        <p class="text-xs font-bold text-neutral-black/60 uppercase tracking-wider">Loading preferences...</p>
      </div>

      <template v-else-if="notificationsStore.preferences">
        <!-- 1. Global Delivery Channels -->
        <div class="space-y-4">
          <h3 class="text-xs font-extrabold uppercase tracking-widest text-primary">Global Delivery Channels</h3>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-5 rounded-2xl border border-neutral-ivory bg-neutral-background/50 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary/10 text-primary">
                  <Mail class="h-5 w-5" />
                </div>
                <div>
                  <h4 class="text-sm font-bold text-neutral-black">Email Notifications</h4>
                  <p class="text-xs text-neutral-black/60">Receive notification summaries in your inbox</p>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  :checked="notificationsStore.preferences.email_enabled"
                  @change="handleToggle('email_enabled', !notificationsStore.preferences.email_enabled)"
                  class="sr-only peer"
                />
                <div class="w-11 h-6 bg-neutral-ivory peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-gray after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
              </label>
            </div>

            <div class="p-5 rounded-2xl border border-neutral-ivory bg-neutral-background/50 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-accent-gold/20 text-primary">
                  <Bell class="h-5 w-5" />
                </div>
                <div>
                  <h4 class="text-sm font-bold text-neutral-black">In-App Notifications</h4>
                  <p class="text-xs text-neutral-black/60">Show notification bell indicator & history</p>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  :checked="notificationsStore.preferences.in_app_enabled"
                  @change="handleToggle('in_app_enabled', !notificationsStore.preferences.in_app_enabled)"
                  class="sr-only peer"
                />
                <div class="w-11 h-6 bg-neutral-ivory peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-gray after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
              </label>
            </div>
          </div>
        </div>

        <!-- 2. Category Subscriptions -->
        <div class="space-y-4 pt-4 border-t border-neutral-ivory">
          <h3 class="text-xs font-extrabold uppercase tracking-widest text-primary">Category Subscriptions</h3>

          <div class="space-y-3">
            <div class="p-4 rounded-2xl border border-neutral-ivory flex items-center justify-between hover:border-primary/30 transition-all">
              <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-700">
                  <Megaphone class="h-5 w-5" />
                </div>
                <div>
                  <h4 class="text-sm font-bold text-neutral-black">Community Announcements</h4>
                  <p class="text-xs text-neutral-black/60">Important news, updates, and community alerts</p>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  :checked="notificationsStore.preferences.new_announcements"
                  @change="handleToggle('new_announcements', !notificationsStore.preferences.new_announcements)"
                  class="sr-only peer"
                />
                <div class="w-11 h-6 bg-neutral-ivory peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-gray after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
              </label>
            </div>

            <div class="p-4 rounded-2xl border border-neutral-ivory flex items-center justify-between hover:border-primary/30 transition-all">
              <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-blue-500/10 text-blue-700">
                  <Calendar class="h-5 w-5" />
                </div>
                <div>
                  <h4 class="text-sm font-bold text-neutral-black">Upcoming Events & Training</h4>
                  <p class="text-xs text-neutral-black/60">Reminders for registered events, workshops, and training</p>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  :checked="notificationsStore.preferences.upcoming_training"
                  @change="handleToggle('upcoming_training', !notificationsStore.preferences.upcoming_training)"
                  class="sr-only peer"
                />
                <div class="w-11 h-6 bg-neutral-ivory peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-gray after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
              </label>
            </div>

            <div class="p-4 rounded-2xl border border-neutral-ivory flex items-center justify-between hover:border-primary/30 transition-all">
              <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-indigo-500/10 text-indigo-700">
                  <BookOpen class="h-5 w-5" />
                </div>
                <div>
                  <h4 class="text-sm font-bold text-neutral-black">Course Completion Updates</h4>
                  <p class="text-xs text-neutral-black/60">Dawah Academy course progress and lesson completions</p>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  :checked="notificationsStore.preferences.course_completion"
                  @change="handleToggle('course_completion', !notificationsStore.preferences.course_completion)"
                  class="sr-only peer"
                />
                <div class="w-11 h-6 bg-neutral-ivory peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-gray after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
              </label>
            </div>

            <div class="p-4 rounded-2xl border border-neutral-ivory flex items-center justify-between hover:border-primary/30 transition-all">
              <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-amber-500/10 text-amber-700">
                  <Award class="h-5 w-5" />
                </div>
                <div>
                  <h4 class="text-sm font-bold text-neutral-black">Certificates & Credentials</h4>
                  <p class="text-xs text-neutral-black/60">Earned certificate notifications and verification details</p>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  :checked="notificationsStore.preferences.certificate_earned"
                  @change="handleToggle('certificate_earned', !notificationsStore.preferences.certificate_earned)"
                  class="sr-only peer"
                />
                <div class="w-11 h-6 bg-neutral-ivory peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-gray after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
              </label>
            </div>
          </div>
        </div>

        <div class="p-4 rounded-2xl bg-neutral-background border border-neutral-ivory text-xs text-neutral-black/70 flex items-center gap-2">
          <CheckCircle class="h-4 w-4 text-emerald-600 shrink-0" />
          <span>Note: Security alerts (password resets & email verification) are mandatory and bypass preference opt-outs.</span>
        </div>
      </template>
    </div>
  </div>
</template>
