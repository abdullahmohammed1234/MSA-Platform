<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Mail,
  User,
  Phone,
  BookOpen,
  Calendar,
  Clock,
  Save,
  Trash2,
  AlertCircle,
  FileText,
  Shield,
  Tag,
  ArrowLeft,
} from 'lucide-vue-next';
import { useToastStore } from '@/components/feedback/toast';
import volunteeringRegistrarsService from '@/services/admin/volunteeringRegistrarsService';
import type { VolunteerRegistration, VolunteerRegistrationStatus } from '@/types/ems/volunteers';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const registration = ref<VolunteerRegistration | null>(null);
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');

const selectedStatus = ref<VolunteerRegistrationStatus>('new');
const adminNotes = ref('');

const statusOptions: { value: VolunteerRegistrationStatus; label: string }[] = [
  { value: 'new', label: 'New' },
  { value: 'contacted', label: 'Contacted' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'archived', label: 'Archived' },
];

const fetchRegistration = async () => {
  const uuid = route.params.uuid as string;
  if (!uuid) return;

  isLoading.value = true;
  errorMessage.value = '';

  try {
    const data = await volunteeringRegistrarsService.getRegistration(uuid);
    registration.value = data;
    selectedStatus.value = data.status;
    adminNotes.value = data.admin_notes || '';
  } catch (err: any) {
    errorMessage.value = err.message || 'Failed to load volunteer registration details.';
    toast.error('Failed to load volunteer registration.');
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  void fetchRegistration();
});

const handleSave = async () => {
  if (!registration.value) return;

  isSaving.value = true;

  try {
    const updated = await volunteeringRegistrarsService.updateRegistration(registration.value.uuid, {
      status: selectedStatus.value,
      admin_notes: adminNotes.value,
    });

    registration.value = updated;
    selectedStatus.value = updated.status;
    adminNotes.value = updated.admin_notes || '';
    toast.success('Volunteer registration updated successfully.');
  } catch (err: any) {
    toast.error(err.message || 'Failed to update volunteer registration.');
  } finally {
    isSaving.value = false;
  }
};

const handleDelete = async () => {
  if (!registration.value) return;

  if (!confirm(`Are you sure you want to archive the application for ${registration.value.name}?`)) {
    return;
  }

  try {
    await volunteeringRegistrarsService.deleteRegistration(registration.value.uuid);
    toast.success('Volunteer application archived.');
    void router.push({ name: 'admin-volunteering-registrars' });
  } catch (err: any) {
    toast.error(err.message || 'Failed to archive volunteer registration.');
  }
};

const getStatusBadgeClass = (status: VolunteerRegistrationStatus) => {
  switch (status) {
    case 'new':
      return 'bg-amber-50 text-amber-700 border-amber-200';
    case 'contacted':
      return 'bg-blue-50 text-blue-700 border-blue-200';
    case 'accepted':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    case 'rejected':
      return 'bg-red-50 text-red-700 border-red-200';
    case 'archived':
      return 'bg-neutral-100 text-neutral-600 border-neutral-200';
    default:
      return 'bg-neutral-50 text-neutral-700 border-neutral-200';
  }
};

const formatDate = (dateStr?: string | null) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
};
</script>

<template>
  <div class="space-y-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Navigation Back Link & Header -->
    <div class="space-y-3">
      <router-link
        to="/admin/volunteering-registrars"
        class="inline-flex items-center gap-2 text-xs font-bold text-neutral-muted hover:text-primary transition-colors"
      >
        <ArrowLeft :size="14" /> Back to Volunteering Registrars
      </router-link>

      <div class="space-y-1">
        <h1 class="text-3xl font-display font-bold text-neutral-black tracking-tight">Volunteer Registration Detail</h1>
        <p class="text-sm text-neutral-muted">Review application information, update status, and manage private administrative notes.</p>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="isLoading" class="p-12 text-center text-neutral-muted space-y-3 bg-white rounded-3xl border border-neutral-gray/20 shadow-soft">
      <div class="w-8 h-8 border-3 border-primary/30 border-t-primary rounded-full animate-spin mx-auto" />
      <p class="text-sm font-medium">Loading application details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="p-6 bg-red-50 border border-red-500/20 text-red-600 rounded-3xl space-y-3">
      <div class="flex items-center gap-3">
        <AlertCircle :size="24" class="shrink-0" />
        <p class="font-bold text-base">{{ errorMessage }}</p>
      </div>
      <button @click="fetchRegistration" class="px-4 py-2 bg-red-600 text-white rounded-xl text-xs font-bold cursor-pointer">
        Try Again
      </button>
    </div>

    <!-- Application View -->
    <div v-else-if="registration" class="grid lg:grid-cols-3 gap-8 items-start">
      <!-- Left Column: Applicant Information -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Main Card -->
        <div class="bg-white rounded-3xl border border-neutral-gray/20 p-6 md:p-8 shadow-soft space-y-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-neutral-gray/10 pb-6">
            <div class="space-y-1">
              <h2 class="text-2xl font-bold text-neutral-black">{{ registration.name }}</h2>
              <p class="text-xs text-neutral-muted flex items-center gap-2">
                <Calendar :size="14" />
                Submitted on {{ formatDate(registration.created_at) }}
              </p>
            </div>

            <span
              :class="[
                'inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold border capitalize self-start sm:self-auto',
                getStatusBadgeClass(registration.status),
              ]"
            >
              {{ registration.status_label || registration.status }}
            </span>
          </div>

          <!-- Applicant Fields Grid -->
          <div class="grid sm:grid-cols-2 gap-6 text-sm">
            <div class="space-y-1">
              <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted flex items-center gap-1.5">
                <Mail :size="14" /> SFU Email Address
              </label>
              <p class="font-mono text-neutral-black select-all">{{ registration.email }}</p>
            </div>

            <div class="space-y-1">
              <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted flex items-center gap-1.5">
                <User :size="14" /> SFU Student Number
              </label>
              <p class="font-mono text-neutral-black select-all">{{ registration.student_number }}</p>
            </div>

            <div class="space-y-1">
              <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted flex items-center gap-1.5">
                <Phone :size="14" /> Phone Number
              </label>
              <p class="font-mono text-neutral-black select-all">{{ registration.phone || '—' }}</p>
            </div>

            <div class="space-y-1">
              <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted flex items-center gap-1.5">
                <Tag :size="14" /> Target Department
              </label>
              <p class="font-semibold text-neutral-black">{{ registration.department }}</p>
            </div>

            <div class="space-y-2 sm:col-span-2">
              <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted flex items-center gap-1.5">
                <FileText :size="14" /> Interests & Motivation
              </label>
              <div class="p-4 bg-neutral-background/50 rounded-2xl border border-neutral-gray/10 text-neutral-black leading-relaxed whitespace-pre-wrap">
                {{ registration.interests }}
              </div>
            </div>

            <div class="space-y-2 sm:col-span-2" v-if="registration.experience">
              <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted flex items-center gap-1.5">
                <BookOpen :size="14" /> Relevant Experience
              </label>
              <div class="p-4 bg-neutral-background/50 rounded-2xl border border-neutral-gray/10 text-neutral-black leading-relaxed whitespace-pre-wrap">
                {{ registration.experience }}
              </div>
            </div>
          </div>
        </div>

        <!-- Processing Audit Timeline Card -->
        <div class="bg-white rounded-3xl border border-neutral-gray/20 p-6 md:p-8 shadow-soft space-y-4">
          <h3 class="text-base font-bold text-neutral-black flex items-center gap-2">
            <Clock :size="18" class="text-primary" /> Lifecycle Audit Trail
          </h3>

          <div class="grid sm:grid-cols-2 gap-4 text-xs">
            <div class="p-4 bg-neutral-background/30 rounded-2xl space-y-1">
              <span class="text-neutral-muted font-medium">Submission Timestamp</span>
              <p class="font-semibold text-neutral-black">{{ formatDate(registration.created_at) }}</p>
            </div>

            <div class="p-4 bg-neutral-background/30 rounded-2xl space-y-1">
              <span class="text-neutral-muted font-medium">First Contacted Timestamp</span>
              <p class="font-semibold text-neutral-black">{{ formatDate(registration.contacted_at) }}</p>
            </div>

            <div class="p-4 bg-neutral-background/30 rounded-2xl space-y-1 sm:col-span-2">
              <span class="text-neutral-muted font-medium">Processed Timestamp</span>
              <p class="font-semibold text-neutral-black">{{ formatDate(registration.processed_at) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Administrative Controls -->
      <div class="space-y-6">
        <div class="bg-white rounded-3xl border border-neutral-gray/20 p-6 md:p-8 shadow-soft space-y-6">
          <h3 class="text-base font-bold text-neutral-black flex items-center gap-2 border-b border-neutral-gray/10 pb-4">
            <Shield :size="18" class="text-primary" /> Administrative Controls
          </h3>

          <!-- Status Transition Control -->
          <div class="space-y-2">
            <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted">
              Application Status
            </label>
            <select
              v-model="selectedStatus"
              class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-4 py-3 text-sm text-neutral-black focus:ring-2 focus:ring-primary/20 outline-none cursor-pointer"
            >
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>

          <!-- Private Administrative Notes -->
          <div class="space-y-2">
            <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted">
              Internal Admin Notes
            </label>
            <p class="text-[11px] text-neutral-muted">Private to administrators. Never shown to applicant.</p>
            <textarea
              v-model="adminNotes"
              rows="5"
              placeholder="Record phone notes, interview feedback, committee decisions..."
              class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl p-4 text-sm text-neutral-black focus:ring-2 focus:ring-primary/20 outline-none resize-none"
            />
          </div>

          <!-- Save Button -->
          <button
            @click="handleSave"
            :disabled="isSaving"
            class="w-full bg-primary text-white py-4 rounded-2xl font-bold uppercase tracking-[0.2em] text-xs hover:bg-secondary transition-all flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer border-none shadow-sm"
          >
            <div v-if="isSaving" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            <template v-else>
              <Save :size="16" /> Save Changes
            </template>
          </button>

          <hr class="border-neutral-gray/10" />

          <!-- Archive Action -->
          <button
            @click="handleDelete"
            class="w-full bg-red-50 text-red-600 border border-red-200 py-3 rounded-2xl font-bold text-xs hover:bg-red-100 transition-all flex items-center justify-center gap-2 cursor-pointer"
          >
            <Trash2 :size="16" /> Archive Application
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
