<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
  HeartHandshake,
  Search,
  ChevronRight,
  Trash2,
  AlertCircle,
  RefreshCw,
} from 'lucide-vue-next';
import EmsPageHeader from '@/components/ems/EmsPageHeader.vue';
import { useToastStore } from '@/components/feedback/toast';
import volunteeringRegistrarsService from '@/services/ems/volunteeringRegistrarsService';
import type { VolunteerRegistration, VolunteerRegistrationStatus } from '@/types/ems/volunteers';

const router = useRouter();
const toast = useToastStore();

const registrations = ref<VolunteerRegistration[]>([]);
const isLoading = ref(true);
const errorMessage = ref('');

const search = ref('');
const activeStatus = ref<string>('all');
const currentPage = ref(1);
const totalItems = ref(0);
const lastPage = ref(1);

const statusTabs = [
  { id: 'all', label: 'All' },
  { id: 'new', label: 'New' },
  { id: 'contacted', label: 'Contacted' },
  { id: 'accepted', label: 'Accepted' },
  { id: 'rejected', label: 'Rejected' },
  { id: 'archived', label: 'Archived' },
];

const fetchRegistrations = async () => {
  isLoading.value = true;
  errorMessage.value = '';

  try {
    const response = await volunteeringRegistrarsService.listRegistrations({
      search: search.value,
      status: activeStatus.value,
      page: currentPage.value,
      per_page: 15,
      sort_by: 'created_at',
      sort_order: 'desc',
    });

    registrations.value = response.items;
    totalItems.value = response.pagination.total;
    lastPage.value = response.pagination.last_page;
    currentPage.value = response.pagination.current_page;
  } catch (err: any) {
    errorMessage.value = err.message || 'Failed to load volunteer registrations.';
    toast.error('Failed to load volunteer registrations.');
  } finally {
    isLoading.value = false;
  }
};

let searchTimeout: any = null;
watch(search, () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    currentPage.value = 1;
    void fetchRegistrations();
  }, 300);
});

watch(activeStatus, () => {
  currentPage.value = 1;
  void fetchRegistrations();
});

onMounted(() => {
  void fetchRegistrations();
});

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
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const navigateToDetail = (uuid: string) => {
  void router.push({ name: 'ems-volunteering-registrar-detail', params: { uuid } });
};

const handleDelete = async (registration: VolunteerRegistration) => {
  if (!confirm(`Are you sure you want to archive the application for ${registration.name}?`)) {
    return;
  }

  try {
    await volunteeringRegistrarsService.deleteRegistration(registration.uuid);
    toast.success(`Archived application for ${registration.name}.`);
    void fetchRegistrations();
  } catch (err: any) {
    toast.error(err.message || 'Failed to archive volunteer registration.');
  }
};
</script>

<template>
  <div class="space-y-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <EmsPageHeader
      title="Volunteering Registrars"
      description="Manage, review, and track public volunteer registrations for SFU MSA."
    />

    <!-- Header Filters & Search -->
    <div class="flex flex-col md:flex-row gap-4 items-stretch md:items-center justify-between">
      <!-- Status Tabs -->
      <div class="flex items-center gap-1 p-1 bg-neutral-gray/10 rounded-2xl overflow-x-auto shrink-0">
        <button
          v-for="tab in statusTabs"
          :key="tab.id"
          @click="activeStatus = tab.id"
          :class="[
            'px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap',
            activeStatus === tab.id
              ? 'bg-white text-primary shadow-sm'
              : 'text-neutral-muted hover:text-neutral-black',
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Search Input -->
      <div class="relative min-w-[280px]">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-muted" :size="16" />
        <input
          type="text"
          v-model="search"
          placeholder="Search name, email, student #, dept..."
          class="w-full bg-white border border-neutral-gray/20 rounded-2xl pl-11 pr-4 py-2.5 text-sm text-neutral-black focus:ring-2 focus:ring-primary/20 outline-none transition-all"
        />
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="errorMessage"
      class="p-4 bg-red-50 border border-red-500/20 text-red-600 rounded-2xl flex items-center justify-between"
    >
      <div class="flex items-center gap-3">
        <AlertCircle :size="20" class="shrink-0" />
        <p class="text-sm font-medium">{{ errorMessage }}</p>
      </div>
      <button @click="fetchRegistrations" class="text-xs font-bold underline cursor-pointer">
        Retry
      </button>
    </div>

    <!-- Table Container -->
    <div class="bg-white rounded-3xl border border-neutral-gray/20 shadow-soft overflow-hidden">
      <!-- Loading Overlay -->
      <div v-if="isLoading" class="p-12 text-center text-neutral-muted space-y-3">
        <RefreshCw class="animate-spin mx-auto text-primary" :size="28" />
        <p class="text-sm font-medium">Loading volunteer registrations...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="registrations.length === 0" class="p-12 text-center space-y-4">
        <div class="w-16 h-16 bg-primary/5 rounded-3xl flex items-center justify-center mx-auto text-primary">
          <HeartHandshake :size="32" />
        </div>
        <div class="space-y-1">
          <h3 class="text-lg font-bold text-neutral-black">No registrations found</h3>
          <p class="text-sm text-neutral-muted max-w-sm mx-auto">
            There are no volunteer applications matching your criteria.
          </p>
        </div>
      </div>

      <!-- Responsive Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-neutral-background/50 border-b border-neutral-gray/20 text-[11px] uppercase tracking-wider text-neutral-muted font-bold">
              <th class="py-4 px-6">Applicant Name</th>
              <th class="py-4 px-6">SFU Email</th>
              <th class="py-4 px-6">Student #</th>
              <th class="py-4 px-6">Department</th>
              <th class="py-4 px-6">Status</th>
              <th class="py-4 px-6">Submitted Date</th>
              <th class="py-4 px-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-gray/10 text-sm">
            <tr
              v-for="item in registrations"
              :key="item.uuid"
              @click="navigateToDetail(item.uuid)"
              class="hover:bg-neutral-background/30 transition-colors cursor-pointer group"
            >
              <!-- Name -->
              <td class="py-4 px-6 font-semibold text-neutral-black">
                {{ item.name }}
              </td>

              <!-- Email -->
              <td class="py-4 px-6 text-neutral-muted font-mono text-xs">
                {{ item.email }}
              </td>

              <!-- Student Number -->
              <td class="py-4 px-6 text-neutral-muted font-mono text-xs">
                {{ item.student_number }}
              </td>

              <!-- Department -->
              <td class="py-4 px-6 text-neutral-black">
                <span class="inline-flex items-center px-3 py-1 bg-neutral-background rounded-full text-xs font-medium text-neutral-black">
                  {{ item.department }}
                </span>
              </td>

              <!-- Status Badge -->
              <td class="py-4 px-6">
                <span
                  :class="[
                    'inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border capitalize',
                    getStatusBadgeClass(item.status),
                  ]"
                >
                  {{ item.status_label || item.status }}
                </span>
              </td>

              <!-- Submitted Date -->
              <td class="py-4 px-6 text-neutral-muted text-xs">
                {{ formatDate(item.created_at) }}
              </td>

              <!-- Actions -->
              <td class="py-4 px-6 text-right" @click.stop>
                <div class="flex items-center justify-end gap-2">
                  <button
                    @click="navigateToDetail(item.uuid)"
                    class="p-2 text-neutral-muted hover:text-primary hover:bg-primary/5 rounded-xl transition-all cursor-pointer"
                    title="View details"
                  >
                    <ChevronRight :size="18" />
                  </button>
                  <button
                    @click="handleDelete(item)"
                    class="p-2 text-neutral-muted hover:text-red-600 hover:bg-red-50 rounded-xl transition-all cursor-pointer"
                    title="Archive application"
                  >
                    <Trash2 :size="18" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="registrations.length > 0"
        class="p-4 bg-neutral-background/30 border-t border-neutral-gray/20 flex items-center justify-between text-xs text-neutral-muted"
      >
        <span>
          Showing page {{ currentPage }} of {{ lastPage }} ({{ totalItems }} total applications)
        </span>

        <div class="flex items-center gap-2">
          <button
            :disabled="currentPage <= 1 || isLoading"
            @click="currentPage--; fetchRegistrations()"
            class="px-3 py-1.5 bg-white border border-neutral-gray/20 rounded-xl font-bold disabled:opacity-40 cursor-pointer hover:bg-neutral-background"
          >
            Previous
          </button>
          <button
            :disabled="currentPage >= lastPage || isLoading"
            @click="currentPage++; fetchRegistrations()"
            class="px-3 py-1.5 bg-white border border-neutral-gray/20 rounded-xl font-bold disabled:opacity-40 cursor-pointer hover:bg-neutral-background"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
