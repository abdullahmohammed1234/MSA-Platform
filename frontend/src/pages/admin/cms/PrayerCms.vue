<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import cmsService from '@/services/cms/cmsService';
import type { CmsPrayer, CmsPrayerTiming } from '@/types/cms';
import { useToastStore } from '@/components/feedback/toast';
import { 
  Plus, 
  Edit, 
  Trash2, 
  X, 
  Save, 
  Clock, 
  MapPin, 
  Sparkles,
  CheckCircle2,
  XCircle,
  AlertCircle
} from 'lucide-vue-next';

const toast = useToastStore();

const prayers = ref<CmsPrayer[]>([]);
const isLoading = ref(false);
const isSaving = ref(false);
const error = ref<string | null>(null);

const activeTab = ref<'jumuah' | 'daily'>('jumuah');

// Modal State
const isFormOpen = ref(false);
const activePrayer = ref<CmsPrayer | null>(null);

// Form State
const form = ref<{
  type: 'daily' | 'jumuah';
  title: string;
  campus: string;
  location: string;
  is_enabled: boolean;
  khutbah_time: string;
  prayer_time: string;
  notes: string;
  address: string;
  timings: CmsPrayerTiming[];
}>({
  type: 'jumuah',
  title: '',
  campus: 'Burnaby',
  location: '',
  is_enabled: true,
  khutbah_time: '1:30 PM',
  prayer_time: '2:00 PM',
  notes: '',
  address: '',
  timings: [
    { label: 'Setup', time: '1:00 PM' },
    { label: 'Khutbah', time: '1:30 PM' },
    { label: 'Iqamah', time: '2:00 PM' }
  ]
});

onMounted(() => {
  fetchPrayers();
});

const fetchPrayers = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    prayers.value = await cmsService.getPrayers();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to fetch prayer schedule from CMS.';
  } finally {
    isLoading.value = false;
  }
};

const filteredPrayers = computed(() => {
  return prayers.value.filter(p => p.type === activeTab.value);
});

const openCreateForm = (type: 'jumuah' | 'daily' = activeTab.value) => {
  activePrayer.value = null;
  if (type === 'jumuah') {
    form.value = {
      type: 'jumuah',
      title: "Burnaby Jumu'ah",
      campus: 'Burnaby',
      location: 'Educational Building Gym',
      is_enabled: true,
      khutbah_time: '1:30 PM',
      prayer_time: '2:00 PM',
      notes: '',
      address: '',
      timings: [
        { label: 'Setup', time: '1:00 PM' },
        { label: 'Khutbah', time: '1:30 PM' },
        { label: 'Iqamah', time: '2:00 PM' }
      ]
    };
  } else {
    form.value = {
      type: 'daily',
      title: 'Burnaby Campus Musalla',
      campus: 'Burnaby',
      location: 'AQ 3200',
      is_enabled: true,
      khutbah_time: '',
      prayer_time: '',
      notes: 'Wudu facilities located nearby.',
      address: '8888 University Dr, Burnaby, BC',
      timings: []
    };
  }
  isFormOpen.value = true;
};

const openEditForm = (prayer: CmsPrayer) => {
  activePrayer.value = prayer;
  let timings: CmsPrayerTiming[] = [];
  if (Array.isArray(prayer.timings_json) && prayer.timings_json.length > 0) {
    timings = prayer.timings_json.map(t => ({ ...t }));
  } else if (prayer.type === 'jumuah') {
    timings = [
      { label: 'Khutbah', time: prayer.khutbah_time || '1:30 PM' },
      { label: 'Iqamah', time: prayer.prayer_time || '2:00 PM' }
    ];
  }

  form.value = {
    type: prayer.type,
    title: prayer.title,
    campus: prayer.campus || 'Burnaby',
    location: prayer.location || '',
    is_enabled: prayer.is_enabled,
    khutbah_time: prayer.khutbah_time || '',
    prayer_time: prayer.prayer_time || '',
    notes: prayer.notes || '',
    address: prayer.address || '',
    timings
  };
  isFormOpen.value = true;
};

const addTimingRow = () => {
  form.value.timings.push({ label: 'Phase', time: '12:00 PM' });
};

const removeTimingRow = (index: number) => {
  form.value.timings.splice(index, 1);
};

const handleSave = async () => {
  if (!form.value.title.trim()) {
    toast.error('Title is required');
    return;
  }

  isSaving.value = true;
  try {
    const payload: Partial<CmsPrayer> = {
      type: form.value.type,
      title: form.value.title.trim(),
      campus: form.value.campus,
      location: form.value.location.trim(),
      is_enabled: form.value.is_enabled,
      khutbah_time: form.value.khutbah_time || (form.value.timings.find(t => t.label.toLowerCase().includes('khutbah'))?.time || null),
      prayer_time: form.value.prayer_time || (form.value.timings.find(t => t.label.toLowerCase().includes('iqamah') || t.label.toLowerCase().includes('prayer'))?.time || null),
      notes: form.value.notes.trim() || null,
      address: form.value.address.trim() || null,
      timings_json: form.value.type === 'jumuah' ? form.value.timings : null,
    };

    if (activePrayer.value) {
      const id = activePrayer.value.id || activePrayer.value.uuid;
      await cmsService.updatePrayer(id, payload);
      toast.success('Prayer schedule updated successfully');
    } else {
      await cmsService.createPrayer(payload);
      toast.success('New prayer session created successfully');
    }
    isFormOpen.value = false;
    fetchPrayers();
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to save prayer details.');
  } finally {
    isSaving.value = false;
  }
};

const toggleStatus = async (prayer: CmsPrayer) => {
  const id = prayer.id || prayer.uuid;
  try {
    await cmsService.updatePrayer(id, { is_enabled: !prayer.is_enabled });
    prayer.is_enabled = !prayer.is_enabled;
    toast.success(`Prayer session ${prayer.is_enabled ? 'enabled' : 'disabled'}.`);
  } catch {
    toast.error('Failed to update status.');
  }
};

const handleDelete = async (prayer: CmsPrayer) => {
  if (!confirm(`Are you sure you want to delete "${prayer.title}"?`)) return;
  const id = prayer.id || prayer.uuid;
  try {
    await cmsService.deletePrayer(id);
    toast.success('Prayer session deleted');
    fetchPrayers();
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to delete prayer session.');
  }
};
</script>

<template>
  <div class="space-y-8 pb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
      <div>
        <div class="flex items-center gap-2">
          <Clock class="w-6 h-6 text-primary" />
          <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-primary">Prayer & Jumu'ah CMS</h1>
        </div>
        <p class="text-sm text-neutral-black/60 mt-1">
          Easily modify Friday Jumu'ah schedules, setup/khutbah/iqamah times, and daily campus musalla locations.
        </p>
      </div>

      <button
        @click="openCreateForm()"
        class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-primary text-white font-bold text-xs uppercase tracking-wider hover:bg-primary-dark transition shadow-soft cursor-pointer shrink-0"
      >
        <Plus class="w-4 h-4" />
        Add Prayer Session
      </button>
    </div>

    <!-- Error Alert -->
    <div v-if="error" class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-3">
      <AlertCircle class="w-5 h-5 shrink-0" />
      <span>{{ error }}</span>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center justify-between border-b border-neutral-ivory pb-4">
      <div class="flex items-center gap-2">
        <button
          @click="activeTab = 'jumuah'"
          :class="[
            'px-5 py-2.5 rounded-full text-xs font-extrabold uppercase tracking-wider transition cursor-pointer',
            activeTab === 'jumuah'
              ? 'bg-primary text-white shadow-soft'
              : 'bg-neutral-background text-neutral-black/60 hover:text-primary'
          ]"
        >
          Friday Jumu'ah Schedule
        </button>
        <button
          @click="activeTab = 'daily'"
          :class="[
            'px-5 py-2.5 rounded-full text-xs font-extrabold uppercase tracking-wider transition cursor-pointer',
            activeTab === 'daily'
              ? 'bg-primary text-white shadow-soft'
              : 'bg-neutral-background text-neutral-black/60 hover:text-primary'
          ]"
        >
          Daily Campus Musallas
        </button>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="isLoading" class="text-center py-16">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary border-t-transparent" />
      <p class="text-xs uppercase tracking-widest font-bold text-neutral-black/40 mt-3">Loading Prayer Records...</p>
    </div>

    <!-- Content Grid -->
    <div v-else-if="filteredPrayers.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="prayer in filteredPrayers"
        :key="prayer.id || prayer.uuid"
        class="bg-white rounded-3xl p-6 border border-neutral-ivory shadow-soft hover:shadow-premium transition-all duration-300 flex flex-col justify-between group"
      >
        <div class="space-y-4">
          <!-- Top Row Badges -->
          <div class="flex items-center justify-between gap-2">
            <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-primary/10 text-primary">
              {{ prayer.campus || 'Burnaby' }}
            </span>
            <button
              @click="toggleStatus(prayer)"
              :class="[
                'px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest transition cursor-pointer flex items-center gap-1.5',
                prayer.is_enabled
                  ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                  : 'bg-neutral-100 text-neutral-500 border border-neutral-200'
              ]"
            >
              <CheckCircle2 v-if="prayer.is_enabled" class="w-3 h-3" />
              <XCircle v-else class="w-3 h-3" />
              {{ prayer.is_enabled ? 'Active' : 'Hidden' }}
            </button>
          </div>

          <!-- Title & Location -->
          <div>
            <h3 class="text-xl font-display font-extrabold text-primary group-hover:text-secondary transition-colors">
              {{ prayer.title }}
            </h3>
            <p class="text-xs font-bold text-secondary uppercase tracking-wider flex items-center gap-1.5 mt-1">
              <MapPin class="w-3.5 h-3.5 shrink-0" />
              {{ prayer.location || 'Location TBA' }}
            </p>
          </div>

          <!-- Timings list for Jumuah -->
          <div v-if="prayer.type === 'jumuah'" class="space-y-2 pt-2 border-t border-neutral-ivory">
            <p class="text-[10px] font-extrabold uppercase tracking-widest text-neutral-black/40">Timings Schedule</p>
            <div class="flex flex-wrap gap-2">
              <template v-if="Array.isArray(prayer.timings_json) && prayer.timings_json.length > 0">
                <div
                  v-for="t in prayer.timings_json"
                  :key="t.label"
                  class="px-3 py-1.5 rounded-xl bg-neutral-background border border-neutral-ivory text-xs"
                >
                  <span class="font-extrabold text-neutral-black/50 text-[10px] uppercase tracking-wider block">{{ t.label }}</span>
                  <span class="font-display font-bold text-primary">{{ t.time }}</span>
                </div>
              </template>
              <template v-else>
                <div v-if="prayer.khutbah_time" class="px-3 py-1.5 rounded-xl bg-neutral-background border border-neutral-ivory text-xs">
                  <span class="font-extrabold text-neutral-black/50 text-[10px] uppercase tracking-wider block">Khutbah</span>
                  <span class="font-display font-bold text-primary">{{ prayer.khutbah_time }}</span>
                </div>
                <div v-if="prayer.prayer_time" class="px-3 py-1.5 rounded-xl bg-neutral-background border border-neutral-ivory text-xs">
                  <span class="font-extrabold text-neutral-black/50 text-[10px] uppercase tracking-wider block">Iqamah</span>
                  <span class="font-display font-bold text-primary">{{ prayer.prayer_time }}</span>
                </div>
              </template>
            </div>
          </div>

          <!-- Notes for Daily -->
          <div v-else-if="prayer.notes" class="pt-2 border-t border-neutral-ivory">
            <p class="text-[10px] font-extrabold uppercase tracking-widest text-neutral-black/40">Details & Wudu</p>
            <p class="text-xs text-neutral-black/70 mt-1 leading-relaxed">{{ prayer.notes }}</p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-2 pt-4 mt-4 border-t border-neutral-ivory">
          <button
            @click="openEditForm(prayer)"
            class="p-2.5 rounded-xl bg-neutral-background text-neutral-black/70 hover:text-primary hover:bg-primary/10 transition cursor-pointer"
            title="Edit Session"
          >
            <Edit class="w-4 h-4" />
          </button>
          <button
            @click="handleDelete(prayer)"
            class="p-2.5 rounded-xl bg-neutral-background text-neutral-black/70 hover:text-red-600 hover:bg-red-50 transition cursor-pointer"
            title="Delete Session"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-16 bg-white rounded-3xl border border-neutral-ivory p-8">
      <Sparkles class="w-10 h-10 text-neutral-black/20 mx-auto mb-3" />
      <h3 class="text-lg font-bold text-primary">No {{ activeTab === 'jumuah' ? 'Friday Jumu\'ah' : 'Daily Musalla' }} records found</h3>
      <p class="text-xs text-neutral-black/50 mt-1 max-w-sm mx-auto">
        Click "Add Prayer Session" to create your first prayer schedule record.
      </p>
      <button
        @click="openCreateForm()"
        class="mt-4 px-4 py-2 rounded-xl bg-primary text-white font-bold text-xs uppercase tracking-wider hover:bg-primary-dark transition cursor-pointer"
      >
        Add First Record
      </button>
    </div>

    <!-- Create / Edit Modal -->
    <div
      v-if="isFormOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-black/50 backdrop-blur-xs overflow-y-auto"
    >
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-neutral-ivory relative my-8">
        <button
          @click="isFormOpen = false"
          class="absolute top-6 right-6 p-2 rounded-xl text-neutral-black/40 hover:text-neutral-black hover:bg-neutral-background transition cursor-pointer"
        >
          <X class="w-5 h-5" />
        </button>

        <h2 class="text-2xl font-display font-extrabold text-primary mb-6">
          {{ activePrayer ? 'Edit Prayer Entry' : 'Add New Prayer Entry' }}
        </h2>

        <form @submit.prevent="handleSave" class="space-y-5">
          <!-- Type & Campus -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black/60 mb-1.5">Type</label>
              <select
                v-model="form.type"
                class="w-full px-4 py-2.5 rounded-xl border border-neutral-ivory text-sm font-semibold focus:outline-hidden focus:ring-2 focus:ring-primary/20"
              >
                <option value="jumuah">Friday Jumu'ah</option>
                <option value="daily">Daily Campus Musalla</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black/60 mb-1.5">Campus</label>
              <select
                v-model="form.campus"
                class="w-full px-4 py-2.5 rounded-xl border border-neutral-ivory text-sm font-semibold focus:outline-hidden focus:ring-2 focus:ring-primary/20"
              >
                <option value="Burnaby">Burnaby</option>
                <option value="Surrey">Surrey</option>
                <option value="Vancouver">Vancouver</option>
              </select>
            </div>
          </div>

          <!-- Title -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black/60 mb-1.5">Title</label>
            <input
              v-model="form.title"
              type="text"
              placeholder="e.g. Burnaby 1st Jumu'ah"
              class="w-full px-4 py-2.5 rounded-xl border border-neutral-ivory text-sm font-semibold focus:outline-hidden focus:ring-2 focus:ring-primary/20"
              required
            />
          </div>

          <!-- Location -->
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black/60 mb-1.5">Location / Room</label>
            <input
              v-model="form.location"
              type="text"
              placeholder="e.g. Educational Building Gym or SRYE 1005"
              class="w-full px-4 py-2.5 rounded-xl border border-neutral-ivory text-sm font-semibold focus:outline-hidden focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <!-- Dynamic Timings for Jumu'ah -->
          <div v-if="form.type === 'jumuah'" class="space-y-3 pt-2 border-t border-neutral-ivory">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black/60">Schedule Timings</label>
              <button
                type="button"
                @click="addTimingRow"
                class="text-xs font-bold uppercase tracking-wider text-primary hover:underline cursor-pointer flex items-center gap-1"
              >
                <Plus class="w-3.5 h-3.5" />
                Add Timing
              </button>
            </div>

            <div v-for="(timing, idx) in form.timings" :key="idx" class="flex items-center gap-2">
              <input
                v-model="timing.label"
                type="text"
                placeholder="Label (Setup, Khutbah, Iqamah)"
                class="flex-1 px-3 py-2 rounded-xl border border-neutral-ivory text-xs font-semibold"
              />
              <input
                v-model="timing.time"
                type="text"
                placeholder="Time (e.g. 1:30 PM)"
                class="w-32 px-3 py-2 rounded-xl border border-neutral-ivory text-xs font-semibold"
              />
              <button
                type="button"
                @click="removeTimingRow(idx)"
                class="p-2 text-neutral-black/40 hover:text-red-600 transition cursor-pointer"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Daily Notes -->
          <div v-else>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black/60 mb-1.5">Wudu & Facility Details</label>
            <textarea
              v-model="form.notes"
              rows="3"
              placeholder="Provide information on wudu access, room numbers, etc."
              class="w-full px-4 py-2.5 rounded-xl border border-neutral-ivory text-sm font-semibold focus:outline-hidden focus:ring-2 focus:ring-primary/20"
            ></textarea>
          </div>

          <!-- Active status toggle -->
          <div class="flex items-center gap-3 pt-2">
            <input
              v-model="form.is_enabled"
              id="is_enabled"
              type="checkbox"
              class="w-4 h-4 text-primary rounded border-neutral-ivory focus:ring-primary"
            />
            <label for="is_enabled" class="text-xs font-bold uppercase tracking-wider text-neutral-black/80 cursor-pointer">
              Visible on Public Website
            </label>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3 pt-4 border-t border-neutral-ivory">
            <button
              type="button"
              @click="isFormOpen = false"
              class="px-5 py-2.5 rounded-xl bg-neutral-background text-neutral-black/70 font-bold text-xs uppercase tracking-wider hover:bg-neutral-ivory transition cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="px-5 py-2.5 rounded-xl bg-primary text-white font-bold text-xs uppercase tracking-wider hover:bg-primary-dark transition shadow-soft cursor-pointer flex items-center gap-2"
            >
              <Save class="w-4 h-4" />
              {{ isSaving ? 'Saving...' : 'Save Entry' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
