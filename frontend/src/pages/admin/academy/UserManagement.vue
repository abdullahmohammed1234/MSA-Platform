<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useAdminStudentsStore } from '@/stores/admin/academy/students';
import { useAdminMentorsStore } from '@/stores/admin/academy/mentors';
import { Users, ShieldCheck, GraduationCap, Search, RefreshCw, UserCheck, UserX } from 'lucide-vue-next';

const authStore = useAuthStore();
const studentsStore = useAdminStudentsStore();
const mentorsStore = useAdminMentorsStore();
const searchQuery = ref('');

const loadRoster = async () => {
  await Promise.all([
    studentsStore.fetchStudents(),
    mentorsStore.fetchMentors(),
  ]);
};

const normalizeRole = (roles: string[] = []) => {
  if (roles.includes('admin') || roles.includes('super-admin')) return 'Admin';
  if (roles.includes('mentor')) return 'Mentor';
  if (roles.includes('volunteer')) return 'Volunteer';
  return 'Student';
};

type RosterEntry = {
  id: number;
  name: string;
  email: string;
  role: string;
  active: boolean;
  source: string;
  student?: (typeof studentsStore.students)[number];
  mentor?: (typeof mentorsStore.mentors)[number];
};

const roster = computed((): RosterEntry[] => {
  const currentUser = authStore.user
    ? [{
        id: authStore.user.id,
        name: authStore.user.name,
        email: authStore.user.email,
        role: normalizeRole(authStore.user.roles),
        active: authStore.user.is_active,
        source: 'Current user',
      }]
    : [];

  const studentRows = studentsStore.students.map((student) => ({
    id: student.id,
    name: student.name,
    email: student.email,
    role: 'Student',
    active: student.is_active,
    source: `${student.enrollments_count || 0} enrollments`,
    student,
  }));

  const mentorRows = mentorsStore.mentors.map((mentor) => ({
    id: mentor.id,
    name: mentor.name,
    email: mentor.email,
    role: 'Mentor',
    active: true,
    source: `${mentor.students_count} learners`,
    mentor,
  }));

  return [...currentUser, ...mentorRows, ...studentRows].filter((entry) => {
    const term = searchQuery.value.toLowerCase();
    if (!term) return true;
    return entry.name.toLowerCase().includes(term) || entry.email.toLowerCase().includes(term) || entry.role.toLowerCase().includes(term);
  });
});

const roleCounts = computed(() => {
  const counts = { Admin: 0, Mentor: 0, Volunteer: 0, Student: 0 };
  roster.value.forEach((entry) => {
    counts[entry.role as keyof typeof counts] += 1;
  });
  return counts;
});

const toggleStudentActive = async (entry: RosterEntry) => {
  if (!entry.student) return;

  if (entry.student.is_active) {
    await studentsStore.suspendStudent(entry.student.id);
  } else {
    await studentsStore.reactivateStudent(entry.student.id);
  }
};

onMounted(loadRoster);
</script>

<template>
  <div class="space-y-8 max-w-7xl mx-auto p-1 text-neutral-black">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white border border-neutral-ivory rounded-2xl p-6 shadow-xl">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-primary">
          User Management
        </h1>
        <p class="text-sm text-neutral-muted mt-1">Coordinate roster access, review staff assignments, and move between roles, mentors, and student records.</p>
      </div>
      <button
        @click="loadRoster"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-neutral-background border border-neutral-ivory text-sm font-semibold hover:bg-neutral-background transition"
      >
        <RefreshCw class="h-4 w-4" />
        Refresh roster
      </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Admins</div>
        <div class="mt-2 text-3xl font-black text-white">{{ roleCounts.Admin }}</div>
      </div>
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Mentors</div>
        <div class="mt-2 text-3xl font-black text-white">{{ roleCounts.Mentor }}</div>
      </div>
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Volunteers</div>
        <div class="mt-2 text-3xl font-black text-white">{{ roleCounts.Volunteer }}</div>
      </div>
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Students</div>
        <div class="mt-2 text-3xl font-black text-white">{{ roleCounts.Student }}</div>
      </div>
    </div>

    <div class="rounded-2xl bg-white border border-neutral-ivory p-4">
      <div class="relative">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-muted" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by name, email, or role..."
          class="w-full rounded-xl bg-neutral-background border border-neutral-ivory pl-10 pr-4 py-3 text-sm text-neutral-black placeholder:text-neutral-muted focus:outline-none focus:border-primary"
        />
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2"><Users class="h-4 w-4 text-secondary" /> Roster</h2>
            <p class="text-sm text-neutral-muted">Combined view of staff and learners currently loaded in the admin store.</p>
          </div>
          <router-link to="/admin/roles" class="text-sm font-semibold text-secondary hover:text-secondary-light">Manage roles</router-link>
        </div>

        <div v-if="studentsStore.loading || mentorsStore.loading" class="rounded-2xl bg-white border border-neutral-ivory p-8 text-center text-neutral-muted">
          Loading roster...
        </div>

        <div v-else class="space-y-3">
          <div v-for="entry in roster" :key="`${entry.role}-${entry.id}`" class="rounded-2xl bg-white border border-neutral-ivory p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
              <div class="h-11 w-11 rounded-xl bg-neutral-background flex items-center justify-center text-neutral-muted font-black">
                {{ entry.name.slice(0, 1) }}
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-white truncate">{{ entry.name }}</span>
                  <span class="text-[10px] uppercase tracking-[0.2em] px-2 py-0.5 rounded-full border border-neutral-ivory text-neutral-muted">{{ entry.role }}</span>
                </div>
                <p class="text-sm text-neutral-muted truncate">{{ entry.email }}</p>
                <p class="text-xs text-neutral-muted mt-1">{{ entry.source }}</p>
              </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <span :class="entry.active ? 'bg-secondary/10 text-secondary-light border-secondary/20' : 'bg-accent-red/10 text-accent-red border-accent-red/20'" class="text-xs font-semibold px-3 py-1 rounded-full border">
                {{ entry.active ? 'Active' : 'Inactive' }}
              </span>
              <button
                v-if="entry.student"
                @click="toggleStudentActive(entry)"
                class="inline-flex items-center gap-1.5 rounded-lg bg-neutral-background border border-neutral-ivory px-3 py-1.5 text-xs font-semibold hover:bg-neutral-background transition"
              >
                <component :is="entry.student.is_active ? UserX : UserCheck" class="h-3.5 w-3.5" />
                {{ entry.student.is_active ? 'Suspend' : 'Reactivate' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
          <h3 class="text-sm font-bold text-white flex items-center gap-2"><ShieldCheck class="h-4 w-4 text-secondary" /> Access shortcuts</h3>
          <div class="mt-4 space-y-2 text-sm">
            <router-link to="/admin/permissions" class="block rounded-xl bg-neutral-background/70 hover:bg-neutral-background px-4 py-3 text-neutral-black transition">Permissions matrix</router-link>
            <router-link to="/admin/roles" class="block rounded-xl bg-neutral-background/70 hover:bg-neutral-background px-4 py-3 text-neutral-black transition">Role definitions</router-link>
            <router-link to="/admin/academy/students" class="block rounded-xl bg-neutral-background/70 hover:bg-neutral-background px-4 py-3 text-neutral-black transition">Student roster</router-link>
            <router-link to="/admin/academy/mentors" class="block rounded-xl bg-neutral-background/70 hover:bg-neutral-background px-4 py-3 text-neutral-black transition">Mentor desk</router-link>
          </div>
        </div>

        <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
          <h3 class="text-sm font-bold text-white flex items-center gap-2"><GraduationCap class="h-4 w-4 text-primary" /> Privileged access</h3>
          <p class="mt-3 text-sm text-neutral-muted">Admin and super-admin accounts now resolve to the same privileged access path across the dashboard, guards, and admin layouts.</p>
        </div>
      </div>
    </div>
  </div>
</template>
