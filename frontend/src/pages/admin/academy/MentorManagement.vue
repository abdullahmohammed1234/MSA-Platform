<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useAdminMentorsStore } from '@/stores/admin/academy/mentors';
import { Users, RefreshCw } from 'lucide-vue-next';

const mentorsStore = useAdminMentorsStore();
const search = ref('');

const loadMentors = async () => {
  await mentorsStore.fetchMentors();
};

const filteredMentors = () => mentorsStore.mentors.filter((mentor) =>
  [mentor.name, mentor.email].some((field) => field.toLowerCase().includes(search.value.toLowerCase()))
);

onMounted(loadMentors);
</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto p-1 text-neutral-black">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div>
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary/70">Academy Admin</p>
        <h1 class="text-3xl font-display font-extrabold text-primary">Mentor Management</h1>
        <p class="text-sm text-neutral-muted mt-1">Track mentor assignments, learner coverage, and staff contact details.</p>
      </div>
      <button @click="loadMentors" class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory bg-white px-4 py-2.5 text-sm hover:bg-neutral-background transition"><RefreshCw class="h-4 w-4" /> Refresh</button>
    </div>

    <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
      <div class="flex items-center gap-3"><Users class="h-5 w-5 text-primary" /><h2 class="text-lg font-display font-semibold">Mentor roster</h2></div>
      <div class="mt-4">
        <input v-model="search" type="search" placeholder="Search mentors" class="w-full rounded-xl border border-neutral-ivory bg-neutral-background px-3 py-2.5 text-sm outline-none focus:border-primary/30 md:max-w-md" />
      </div>

      <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <article v-for="mentor in filteredMentors()" :key="mentor.uuid" class="rounded-2xl border border-neutral-ivory p-4">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="font-display text-base font-semibold text-neutral-black">{{ mentor.name }}</h3>
              <p class="text-sm text-neutral-muted">{{ mentor.email }}</p>
            </div>
            <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold uppercase tracking-wide text-primary">{{ mentor.students_count }} learners</span>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <router-link to="/admin/academy/assignments" class="rounded-xl border border-neutral-ivory px-3 py-2 text-sm hover:bg-neutral-background transition">Assignments</router-link>
            <router-link to="/admin/academy/students" class="rounded-xl border border-neutral-ivory px-3 py-2 text-sm hover:bg-neutral-background transition">Students</router-link>
            <router-link to="/admin/academy/progress" class="rounded-xl border border-neutral-ivory px-3 py-2 text-sm hover:bg-neutral-background transition">Progress</router-link>
          </div>
        </article>
      </div>

      <div v-if="mentorsStore.loading" class="mt-4 text-sm text-neutral-muted">Loading mentor records...</div>
    </div>
  </div>
</template>