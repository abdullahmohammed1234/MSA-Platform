<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { User, Mail, Bell, Shield, Save } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/components/feedback/toast';

const authStore = useAuthStore();
const toast = useToastStore();

const name = ref('');
const email = ref('');
const saving = ref(false);

onMounted(() => {
  name.value = authStore.user?.name || '';
  email.value = authStore.user?.email || '';
});

const saveProfile = async () => {
  if (!name.value.trim() || !email.value.trim()) {
    toast.warning('Name and email are required.');
    return;
  }

  saving.value = true;
  try {
    await authStore.updateProfile({
      name: name.value.trim(),
      email: email.value.trim(),
    });
    toast.success('Profile updated successfully.');
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Failed to update profile.');
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <div class="space-y-8 pb-16 max-w-3xl">
    <div>
      <h1 class="text-3xl font-display font-bold text-primary tracking-tight">Account Settings</h1>
      <p class="text-neutral-muted text-sm mt-1 font-light">
        Manage your academy profile and notification preferences.
      </p>
    </div>

    <section class="rounded-3xl border border-neutral-ivory bg-white p-6 shadow-soft space-y-5">
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
          <User class="h-5 w-5" />
        </div>
        <div>
          <h2 class="text-lg font-semibold text-primary">Profile</h2>
          <p class="text-xs text-neutral-muted">Update your display name and contact email.</p>
        </div>
      </div>

      <div class="space-y-4">
        <label class="block space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Full Name</span>
          <div class="relative">
            <User class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-muted" />
            <input v-model="name" type="text" class="input-base pl-11" />
          </div>
        </label>

        <label class="block space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Email</span>
          <div class="relative">
            <Mail class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-muted" />
            <input v-model="email" type="email" class="input-base pl-11" />
          </div>
        </label>
      </div>

      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-white disabled:opacity-60"
        :disabled="saving"
        @click="saveProfile"
      >
        <Save class="h-4 w-4" />
        {{ saving ? 'Saving...' : 'Save Profile' }}
      </button>
    </section>

    <section class="rounded-3xl border border-neutral-ivory bg-white p-6 shadow-soft space-y-4">
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-secondary text-primary">
          <Bell class="h-5 w-5" />
        </div>
        <div>
          <h2 class="text-lg font-semibold text-primary">Notifications</h2>
          <p class="text-xs text-neutral-muted">Configure syllabus, instructor, and campus alerts.</p>
        </div>
      </div>

      <router-link
        to="/academy/settings/notifications"
        class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
      >
        Open notification preferences
      </router-link>
    </section>

    <section class="rounded-3xl border border-neutral-ivory bg-neutral-background/60 p-6">
      <div class="flex items-start gap-3">
        <Shield class="h-5 w-5 text-primary mt-0.5" />
        <div>
          <h2 class="text-sm font-semibold text-primary">Account Security</h2>
          <p class="mt-1 text-xs text-neutral-muted leading-relaxed">
            Password changes are handled through the MSA authentication flow. Use the forgot-password page if you need to reset your credentials.
          </p>
        </div>
      </div>
    </section>
  </div>
</template>
