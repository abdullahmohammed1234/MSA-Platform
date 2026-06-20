<script setup lang="ts">
import { onMounted } from 'vue';
import { useNotificationsStore } from '@/stores/notifications';

const notificationsStore = useNotificationsStore();

onMounted(() => {
  notificationsStore.fetchPreferences();
});

const handleTogglePreference = (key: string, value: boolean) => {
  notificationsStore.updatePreferences({ [key]: value });
};
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <router-link
        to="/notifications"
        class="p-2 text-neutral-muted hover:text-neutral-black hover:bg-neutral-background rounded-lg border border-neutral-ivory shadow-soft transition-colors cursor-pointer"
      >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
      </router-link>
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-neutral-black">Notification Preferences</h1>
        <p class="text-sm text-neutral-muted mt-1">Configure how and when you receive updates from SFU MSA.</p>
      </div>
    </div>

    <div v-if="notificationsStore.loading && !notificationsStore.preferences" class="py-12 flex justify-center">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>

    <div v-else-if="notificationsStore.preferences" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      <!-- Left: Channel Preferences -->
      <div class="bg-white p-6 rounded-2xl border border-neutral-ivory shadow-soft space-y-6 lg:col-span-1">
        <h3 class="font-extrabold text-sm text-neutral-black uppercase tracking-wider border-b border-neutral-background pb-3">
          Delivery Channels
        </h3>
        
        <div class="space-y-5">
          <!-- In-App Switch -->
          <div class="flex items-center justify-between gap-4">
            <div class="text-left">
              <label for="pref-in-app" class="font-bold text-sm text-neutral-black">In-App Notifications</label>
              <p class="text-xs text-neutral-muted mt-0.5">Show notifications inside the platform header bell dropdown.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input
                id="pref-in-app"
                type="checkbox"
                :checked="notificationsStore.preferences.in_app_enabled"
                @change="handleTogglePreference('in_app_enabled', !notificationsStore.preferences.in_app_enabled)"
                class="sr-only peer"
              />
              <div class="w-11 h-6 bg-neutral-background peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-ivory after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
            </label>
          </div>

          <!-- Email Switch -->
          <div class="flex items-center justify-between gap-4">
            <div class="text-left">
              <label for="pref-email" class="font-bold text-sm text-neutral-black">Email Notifications</label>
              <p class="text-xs text-neutral-muted mt-0.5">Send formatted digest emails to your registered address.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input
                id="pref-email"
                type="checkbox"
                :checked="notificationsStore.preferences.email_enabled"
                @change="handleTogglePreference('email_enabled', !notificationsStore.preferences.email_enabled)"
                class="sr-only peer"
              />
              <div class="w-11 h-6 bg-neutral-background peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-ivory after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
            </label>
          </div>
        </div>
      </div>

      <!-- Right: Category Preferences -->
      <div class="bg-white p-6 rounded-2xl border border-neutral-ivory shadow-soft space-y-6 lg:col-span-2">
        <h3 class="font-extrabold text-sm text-neutral-black uppercase tracking-wider border-b border-neutral-background pb-3">
          Event Toggles
        </h3>

        <div class="space-y-4">
          <!-- Course Completion -->
          <div class="p-4 rounded-xl border border-neutral-background hover:bg-neutral-background/30 transition-colors flex items-start justify-between gap-4">
            <div class="text-left">
              <label for="pref-course-completion" class="font-bold text-sm text-neutral-black">Course Completion Updates</label>
              <p class="text-xs text-neutral-muted mt-0.5">Get notified immediately upon completing academy courses (100% progress).</p>
            </div>
            <input
              id="pref-course-completion"
              type="checkbox"
              :checked="notificationsStore.preferences.course_completion"
              @change="handleTogglePreference('course_completion', !notificationsStore.preferences.course_completion)"
              class="h-4 w-4 rounded border-neutral-ivory text-primary focus:ring-primary/20 mt-1 cursor-pointer"
            />
          </div>

          <!-- New Announcements -->
          <div class="p-4 rounded-xl border border-neutral-background hover:bg-neutral-background/30 transition-colors flex items-start justify-between gap-4">
            <div class="text-left">
              <label for="pref-new-announcements" class="font-bold text-sm text-neutral-black">New Announcements</label>
              <p class="text-xs text-neutral-muted mt-0.5">Hear about news, updates, volunteer circulars, and community board posts.</p>
            </div>
            <input
              id="pref-new-announcements"
              type="checkbox"
              :checked="notificationsStore.preferences.new_announcements"
              @change="handleTogglePreference('new_announcements', !notificationsStore.preferences.new_announcements)"
              class="h-4 w-4 rounded border-neutral-ivory text-primary focus:ring-primary/20 mt-1 cursor-pointer"
            />
          </div>

          <!-- Upcoming Training -->
          <div class="p-4 rounded-xl border border-neutral-background hover:bg-neutral-background/30 transition-colors flex items-start justify-between gap-4">
            <div class="text-left">
              <label for="pref-upcoming-training" class="font-bold text-sm text-neutral-black">Upcoming Training & Events</label>
              <p class="text-xs text-neutral-muted mt-0.5">Receive reminders (24h, 3d, 7d before) about scheduled workshops and lectures.</p>
            </div>
            <input
              id="pref-upcoming-training"
              type="checkbox"
              :checked="notificationsStore.preferences.upcoming_training"
              @change="handleTogglePreference('upcoming_training', !notificationsStore.preferences.upcoming_training)"
              class="h-4 w-4 rounded border-neutral-ivory text-primary focus:ring-primary/20 mt-1 cursor-pointer"
            />
          </div>

          <!-- Certificate Earned -->
          <div class="p-4 rounded-xl border border-neutral-background hover:bg-neutral-background/30 transition-colors flex items-start justify-between gap-4">
            <div class="text-left">
              <label for="pref-certificate-earned" class="font-bold text-sm text-neutral-black">Certificate Awards</label>
              <p class="text-xs text-neutral-muted mt-0.5">Get notified immediately when you unlock certifications, with direct verification links.</p>
            </div>
            <input
              id="pref-certificate-earned"
              type="checkbox"
              :checked="notificationsStore.preferences.certificate_earned"
              @change="handleTogglePreference('certificate_earned', !notificationsStore.preferences.certificate_earned)"
              class="h-4 w-4 rounded border-neutral-ivory text-primary focus:ring-primary/20 mt-1 cursor-pointer"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
