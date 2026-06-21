<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { CalendarDays, Lock, Plus, Save, Trash2 } from 'lucide-vue-next';
import client from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/components/feedback/toast';

const authStore = useAuthStore();
const toast = useToastStore();

interface ScheduleSession {
  id: string;
  title: string;
  date: string;
  time: string;
  location: string;
  lead: string;
  focus: string;
  notes: string;
}

const sessions = ref<ScheduleSession[]>([]);
const canEdit = ref(false);
const isLoading = ref(true);
const isSaving = ref(false);
const error = ref('');
const status = ref('');

const user = computed(() => authStore.user);

const sortedSessions = computed(() => {
  return [...sessions.value].sort((a, b) => {
    return `${a.date} ${a.time}`.localeCompare(`${b.date} ${b.time}`);
  });
});

const generateUUID = () => {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
};

const emptySession = (): ScheduleSession => ({
  id: generateUUID(),
  title: "New Dawah Session",
  date: new Date().toISOString().slice(0, 10),
  time: "12:30 PM - 2:30 PM",
  location: "Burnaby Campus",
  lead: "Mentor Team",
  focus: "Outreach and follow-up",
  notes: "",
});

const loadSchedule = async () => {
  isLoading.value = true;
  error.value = '';
  try {
    const response = await client.get('/dawah-schedule');
    if (response.data && response.data.success) {
      sessions.value = response.data.sessions || [];
      canEdit.value = response.data.canEdit || false;
    } else {
      throw new Error(response.data?.error || 'Unable to load schedule.');
    }
  } catch (err: any) {
    error.value = err.response?.data?.error || err.message || 'Unable to load the Dawah schedule.';
  } finally {
    isLoading.value = false;
  }
};

const saveSchedule = async () => {
  isSaving.value = true;
  status.value = '';
  error.value = '';
  try {
    const response = await client.patch('/dawah-schedule', {
      sessions: sessions.value
    });
    if (response.data && response.data.success) {
      sessions.value = response.data.sessions || [];
      canEdit.value = response.data.canEdit || false;
      toast.success('Schedule saved successfully.');
      status.value = 'Schedule saved.';
    } else {
      throw new Error(response.data?.error || 'Unable to save schedule.');
    }
  } catch (err: any) {
    error.value = err.response?.data?.error || err.message || 'Unable to save the Dawah schedule.';
    toast.error('Failed to save the Dawah schedule.');
  } finally {
    isSaving.value = false;
  }
};

const updateSession = (id: string, field: keyof ScheduleSession, value: string) => {
  sessions.value = sessions.value.map((session) =>
    session.id === id ? { ...session, [field]: value } : session
  );
};

const removeSession = (id: string) => {
  sessions.value = sessions.value.filter((session) => session.id !== id);
};

const addSession = () => {
  sessions.value = [...sessions.value, emptySession()];
};

onMounted(() => {
  loadSchedule();
});
</script>

<template>
  <div class="space-y-8 animate-fade-in font-sans text-left">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-bold text-primary tracking-tight">
          Dawah at SFU Schedule
        </h1>
        <p class="text-neutral-muted text-sm mt-1 font-light">
          A shared view of upcoming Dawah tables, prep circles, and mentor-led debriefs.
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <template v-if="canEdit">
          <button
            @click="addSession"
            class="rounded-xl font-bold border border-neutral-ivory hover:bg-neutral-background text-neutral-black flex items-center gap-1.5 cursor-pointer h-11 px-4 text-xs bg-white"
          >
            <Plus class="h-4 w-4" /> Add Session
          </button>
          <button
            @click="saveSchedule"
            :disabled="isSaving"
            class="rounded-xl font-bold bg-primary hover:bg-secondary text-white flex items-center gap-1.5 cursor-pointer h-11 px-5 border text-xs shadow-soft disabled:opacity-50"
          >
            <Save class="h-4 w-4" /> Save Schedule
          </button>
        </template>
        <div
          v-else
          class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-neutral-muted"
        >
          <Lock class="h-4 w-4" /> View Only
        </div>
      </div>
    </div>

    <!-- Status Boxes -->
    <div class="rounded-3xl border border-neutral-ivory bg-white p-5 shadow-premium">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-bold text-primary uppercase tracking-widest font-mono">
            Access Tier: {{ user?.roles?.[0] || 'volunteer' }}
          </p>
          <p class="text-sm text-neutral-muted mt-1 leading-relaxed">
            Students and volunteers can view the schedule. Mentors, coordinators, and admins can edit and publish it.
          </p>
        </div>
        <CalendarDays class="h-9 w-9 text-primary/75 shrink-0" />
      </div>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-xs font-semibold text-red-700">
      {{ error }}
    </div>
    <div v-if="status" class="rounded-2xl border border-green-200 bg-green-50 p-4 text-xs font-semibold text-green-700">
      {{ status }}
    </div>

    <!-- Main Schedule Content -->
    <div v-if="isLoading" class="rounded-3xl border border-neutral-ivory bg-white p-8 text-sm text-neutral-muted shadow-premium">
      Loading schedule...
    </div>

    <div v-else class="grid gap-6">
      <div
        v-for="session in sortedSessions"
        :key="session.id"
        class="rounded-3xl border border-neutral-ivory bg-white shadow-soft overflow-hidden hover:shadow-premium transition-all duration-300"
      >
        <div class="p-6 md:p-8 space-y-6">
          <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
              <h3 class="text-xl font-bold text-neutral-black tracking-tight leading-snug">{{ session.title }}</h3>
              <p class="text-xs font-semibold text-neutral-muted font-mono uppercase">
                {{ session.date }} · {{ session.time }}
              </p>
            </div>
            
            <button
              v-if="canEdit"
              @click="removeSession(session.id)"
              class="self-start md:self-auto p-2 rounded-xl text-neutral-400 hover:bg-neutral-background hover:text-red-650 transition-colors cursor-pointer border border-transparent hover:border-neutral-ivory"
              title="Delete Session"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </div>

          <div v-if="canEdit" class="grid gap-5 md:grid-cols-2 pt-4 border-t border-dashed border-neutral-ivory/55">
            <!-- Form Input Fields for Mentors/Admins -->
            <label class="flex flex-col gap-2">
              <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted font-mono">Session Title</span>
              <input
                type="text"
                :value="session.title"
                @input="updateSession(session.id, 'title', ($event.target as HTMLInputElement).value)"
                class="w-full rounded-xl border border-neutral-ivory bg-[#fffdfa] px-4 py-2.5 text-xs font-medium outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
              />
            </label>

            <label class="flex flex-col gap-2">
              <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted font-mono">Date</span>
              <input
                type="date"
                :value="session.date"
                @input="updateSession(session.id, 'date', ($event.target as HTMLInputElement).value)"
                class="w-full rounded-xl border border-neutral-ivory bg-[#fffdfa] px-4 py-2.5 text-xs font-medium outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
              />
            </label>

            <label class="flex flex-col gap-2">
              <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted font-mono">Time range</span>
              <input
                type="text"
                :value="session.time"
                @input="updateSession(session.id, 'time', ($event.target as HTMLInputElement).value)"
                class="w-full rounded-xl border border-neutral-ivory bg-[#fffdfa] px-4 py-2.5 text-xs font-medium outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
              />
            </label>

            <label class="flex flex-col gap-2">
              <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted font-mono">Campus Location</span>
              <input
                type="text"
                :value="session.location"
                @input="updateSession(session.id, 'location', ($event.target as HTMLInputElement).value)"
                class="w-full rounded-xl border border-neutral-ivory bg-[#fffdfa] px-4 py-2.5 text-xs font-medium outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
              />
            </label>

            <label class="flex flex-col gap-2">
              <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted font-mono">Leader / Mentors</span>
              <input
                type="text"
                :value="session.lead"
                @input="updateSession(session.id, 'lead', ($event.target as HTMLInputElement).value)"
                class="w-full rounded-xl border border-neutral-ivory bg-[#fffdfa] px-4 py-2.5 text-xs font-medium outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
              />
            </label>

            <label class="flex flex-col gap-2">
              <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted font-mono">Dawah Focus</span>
              <input
                type="text"
                :value="session.focus"
                @input="updateSession(session.id, 'focus', ($event.target as HTMLInputElement).value)"
                class="w-full rounded-xl border border-neutral-ivory bg-[#fffdfa] px-4 py-2.5 text-xs font-medium outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
              />
            </label>

            <label class="flex flex-col gap-2 md:col-span-2">
              <span class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted font-mono">Session Notes / Description</span>
              <textarea
                :value="session.notes"
                rows="3"
                @input="updateSession(session.id, 'notes', ($event.target as HTMLTextAreaElement).value)"
                class="w-full resize-none rounded-xl border border-neutral-ivory bg-[#fffdfa] p-4 text-xs font-medium outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
              />
            </label>
          </div>

          <div v-else class="grid gap-6 text-sm md:grid-cols-2 pt-4 border-t border-dashed border-neutral-ivory/55">
            <!-- View-Only details for Standard Volunteers -->
            <div class="space-y-1">
              <span class="text-[10px] font-bold uppercase tracking-widest text-[#640c0e] font-mono">Location</span>
              <p class="text-xs text-neutral-black font-semibold">{{ session.location }}</p>
            </div>
            <div class="space-y-1">
              <span class="text-[10px] font-bold uppercase tracking-widest text-[#640c0e] font-mono">Lead Organizer</span>
              <p class="text-xs text-neutral-black font-semibold">{{ session.lead }}</p>
            </div>
            <div class="space-y-1">
              <span class="text-[10px] font-bold uppercase tracking-widest text-[#640c0e] font-mono">Dawah Focus</span>
              <p class="text-xs text-neutral-black font-semibold">{{ session.focus }}</p>
            </div>
            <div class="space-y-1 md:col-span-2">
              <span class="text-[10px] font-bold uppercase tracking-widest text-[#640c0e] font-mono">Additional Notes</span>
              <p class="text-xs text-neutral-muted leading-relaxed font-light">
                {{ session.notes || 'No additional details noted.' }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
