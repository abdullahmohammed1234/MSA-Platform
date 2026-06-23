<script setup lang="ts">
import { ref, onMounted } from 'vue';
import cmsService from '@/services/cms/cmsService';
import type { Event, CmsRevision } from '@/types/cms';
import { resolvePublicImagePath } from '@/constants/publicAssets';
import { lazyLoadComponent } from '@/utils/LazyLoad';
const RichEditor = lazyLoadComponent(() => import('@/components/admin/RichEditor.vue'));
import { 
  Plus, 
  Search, 
  Edit, 
  Trash2, 
  History, 
  X, 
  Save, 
  RotateCcw,
  Calendar,
  MapPin,
  Clock,
  Star
} from 'lucide-vue-next';

// State variables
const events = ref<Event[]>([]);
const pagination = ref({ page: 1, lastPage: 1, total: 0 });
const searchQuery = ref('');
const statusFilter = ref('');
const categoryFilter = ref('');
const isLoading = ref(false);
const error = ref<string | null>(null);

// Modal state
const isFormOpen = ref(false);
const isRevisionsOpen = ref(false);
const activeEvent = ref<Event | null>(null);
const revisionsList = ref<CmsRevision[]>([]);

// Form state
const form = ref({
  title: '',
  description: '',
  location: '',
  date: '', // Display date string
  time: '', // Display time string
  start_date: '', // Datetime local
  end_date: '', // Datetime local
  image: '',
  category: 'Social',
  status: 'draft' as 'draft' | 'published' | 'archived',
  featured: false,
});

const CATEGORIES = ['Jummah', 'Social', 'Lecture', 'Workshop', 'Charity', 'Dinner'];

onMounted(() => {
  fetchEvents();
});

const fetchEvents = async (page = 1) => {
  isLoading.value = true;
  error.value = null;
  try {
    const res = await cmsService.getEvents({
      search: searchQuery.value,
      status: statusFilter.value,
      category: categoryFilter.value,
      page
    });
    events.value = res.data;
    pagination.value = {
      page: res.current_page,
      lastPage: res.last_page,
      total: res.total
    };
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to retrieve events.';
  } finally {
    isLoading.value = false;
  }
};

const openCreateForm = () => {
  activeEvent.value = null;
  form.value = {
    title: '',
    description: '',
    location: '',
    date: '',
    time: '',
    start_date: '',
    end_date: '',
    image: '',
    category: 'Social',
    status: 'draft',
    featured: false,
  };
  isFormOpen.value = true;
};

const formatDatetimeLocal = (dateStr?: string | null) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const openEditForm = (ev: Event) => {
  activeEvent.value = ev;
  form.value = {
    title: ev.title,
    description: ev.description,
    location: ev.location,
    date: ev.date,
    time: ev.time,
    start_date: formatDatetimeLocal(ev.start_date),
    end_date: formatDatetimeLocal(ev.end_date),
    image: ev.image || '',
    category: ev.category,
    status: ev.status,
    featured: ev.featured,
  };
  isFormOpen.value = true;
};

const handleSubmit = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    const payload = {
      ...form.value,
      spots_left: 0,
      registration_url: null,
      registration_deadline: null,
    };
    // Format timezone offsets nicely for PHP backend
    payload.start_date = payload.start_date.replace('T', ' ');
    if (payload.end_date) {
      payload.end_date = payload.end_date.replace('T', ' ');
    }

    if (activeEvent.value) {
      await cmsService.updateEvent(activeEvent.value.uuid, payload);
    } else {
      await cmsService.createEvent(payload);
    }
    isFormOpen.value = false;
    fetchEvents(pagination.value.page);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to save event details.';
  } finally {
    isLoading.value = false;
  }
};

const handleDelete = async (ev: Event) => {
  if (!confirm(`Are you sure you want to delete "${ev.title}"?`)) return;
  isLoading.value = true;
  error.value = null;
  try {
    await cmsService.deleteEvent(ev.uuid);
    fetchEvents(pagination.value.page);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to delete event.';
  } finally {
    isLoading.value = false;
  }
};

const openRevisions = async (ev: Event) => {
  activeEvent.value = ev;
  isRevisionsOpen.value = true;
  revisionsList.value = [];
  try {
    revisionsList.value = await cmsService.getEventRevisions(ev.uuid);
  } catch (err) {
    console.error('Failed to load version history:', err);
  }
};

const handleRollback = async (version: number) => {
  if (!activeEvent.value) return;
  if (!confirm(`Rollback "${activeEvent.value.title}" to version ${version}?`)) return;
  
  isLoading.value = true;
  try {
    await cmsService.rollbackEvent(activeEvent.value.uuid, version);
    isRevisionsOpen.value = false;
    fetchEvents(pagination.value.page);
  } catch (err: any) {
    alert(err.response?.data?.message || 'Rollback failed.');
  } finally {
    isLoading.value = false;
  }
};

</script>

<template>
  <div class="space-y-8 text-neutral-black">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
      <div>
        <h1 class="text-4xl font-display font-extrabold text-primary">Events CMS</h1>
        <p class="text-neutral-black/45 text-sm">Schedule gatherings, Jumu'ah times, and community dinners.</p>
      </div>
      <button 
        @click="openCreateForm"
        class="w-fit flex items-center gap-2 px-5 py-3 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer"
      >
        <Plus :size="14" /> Add Event
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-neutral-gray/10 p-4 rounded-3xl shadow-soft flex flex-col md:flex-row gap-4 items-center">
      <div class="relative flex-grow w-full">
        <Search class="absolute left-4.5 top-1/2 -translate-y-1/2 text-neutral-black/25" :size="16" />
        <input 
          type="text" 
          placeholder="Search events..." 
          v-model="searchQuery"
          @input="fetchEvents(1)"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 pl-12 pr-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
        />
      </div>
      
      <div class="w-full md:w-48">
        <select 
          v-model="categoryFilter" 
          @change="fetchEvents(1)"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold"
        >
          <option value="">All Categories</option>
          <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
        </select>
      </div>

      <div class="w-full md:w-48">
        <select 
          v-model="statusFilter" 
          @change="fetchEvents(1)"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold"
        >
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
      </div>
    </div>

    <!-- Alert status -->
    <div v-if="error" class="p-4 bg-secondary/10 border border-secondary/20 text-secondary rounded-2xl text-xs font-bold uppercase tracking-wider">
      {{ error }}
    </div>

    <!-- Events List -->
    <div class="bg-white border border-neutral-gray/10 rounded-[2rem] shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-xs text-neutral-black">
          <thead class="bg-neutral-background/50 border-b border-neutral-gray/10 uppercase font-black text-neutral-black/40 tracking-wider">
            <tr>
              <th class="p-6">Event Details</th>
              <th class="p-6">Logistics</th>
              <th class="p-6">Category</th>
              <th class="p-6">Status</th>
              <th class="p-6">Featured</th>
              <th class="p-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-gray/10">
            <tr v-if="events.length === 0">
              <td colspan="6" class="p-12 text-center text-neutral-black/30">
                No events scheduled. Click "Add Event" to insert one.
              </td>
            </tr>
            <tr 
              v-for="ev in events" 
              :key="ev.uuid"
              class="hover:bg-neutral-background/20 transition-colors"
            >
              <td class="p-6">
                <div class="space-y-1">
                  <div class="font-bold text-primary max-w-xs truncate">{{ ev.title }}</div>
                  <div class="text-[10px] text-neutral-black/35 font-bold flex items-center gap-1">
                    <Calendar :size="10" /> {{ ev.date }}
                  </div>
                </div>
              </td>
              <td class="p-6 text-neutral-black/60 space-y-1">
                <div class="flex items-center gap-1 text-[10px]"><MapPin :size="10" class="text-primary-light" /> {{ ev.location }}</div>
                <div class="flex items-center gap-1 text-[10px]"><Clock :size="10" class="text-secondary" /> {{ ev.time }}</div>
              </td>
              <td class="p-6 font-bold text-neutral-black/60">
                {{ ev.category }}
              </td>
              <td class="p-6">
                <span 
                  :class="[
                    'px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider',
                    ev.status === 'published' ? 'bg-secondary/10 text-secondary border border-secondary/20' : '',
                    ev.status === 'draft' ? 'bg-neutral-gray/20 text-neutral-black/60 border border-neutral-gray/30' : '',
                    ev.status === 'archived' ? 'bg-secondary/10 text-secondary border border-secondary/20' : ''
                  ]"
                >
                  {{ ev.status }}
                </span>
              </td>
              <td class="p-6">
                <span v-if="ev.featured" class="text-accent-gold flex items-center gap-0.5 font-bold"><Star :size="12" class="fill-accent-gold" /> Featured</span>
                <span v-else class="text-neutral-black/30 font-medium">—</span>
              </td>
              <td class="p-6 text-right space-x-2">
                <button @click="openEditForm(ev)" class="p-2.5 bg-primary/5 hover:bg-primary hover:text-white rounded-xl text-primary transition-all duration-300 cursor-pointer" title="Edit">
                  <Edit :size="14" />
                </button>
                <button @click="openRevisions(ev)" class="p-2.5 bg-accent-gold/5 hover:bg-accent-gold hover:text-white rounded-xl text-accent-gold transition-all duration-300 cursor-pointer" title="Revisions">
                  <History :size="14" />
                </button>
                <button @click="handleDelete(ev)" class="p-2.5 bg-secondary/5 hover:bg-secondary hover:text-white rounded-xl text-secondary transition-all duration-300 cursor-pointer" title="Delete">
                  <Trash2 :size="14" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.lastPage > 1" class="p-6 bg-neutral-background/30 border-t border-neutral-gray/10 flex justify-between items-center">
        <span class="text-[10px] text-neutral-black/40 font-bold uppercase">Total: {{ pagination.total }} items</span>
        <div class="flex gap-2">
          <button 
            :disabled="pagination.page === 1"
            @click="fetchEvents(pagination.page - 1)"
            class="px-4 py-2 border border-neutral-gray/20 rounded-xl text-[10px] font-bold uppercase cursor-pointer disabled:opacity-40"
          >
            Prev
          </button>
          <button 
            :disabled="pagination.page === pagination.lastPage"
            @click="fetchEvents(pagination.page + 1)"
            class="px-4 py-2 border border-neutral-gray/20 rounded-xl text-[10px] font-bold uppercase cursor-pointer disabled:opacity-40"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- FORM MODAL -->
    <div v-if="isFormOpen" class="fixed inset-0 z-50 flex items-center justify-center p-5">
      <div class="absolute inset-0 bg-primary/20 backdrop-blur-md" @click="isFormOpen = false"></div>
      <div class="bg-white rounded-[2.5rem] border border-neutral-gray/20 p-8 sm:p-10 shadow-premium max-w-3xl w-full relative z-10 max-h-[90vh] overflow-y-auto">
        <button @click="isFormOpen = false" class="absolute top-6 right-6 p-2 text-neutral-black/35 hover:text-primary transition-colors cursor-pointer">
          <X :size="20" />
        </button>

        <h3 class="text-2xl font-display font-extrabold text-primary uppercase mb-6">
          {{ activeEvent ? 'Edit Scheduled Event' : 'Schedule New Event' }}
        </h3>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Event Title</label>
              <input type="text" required v-model="form.title" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" />
            </div>
            
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Event Category</label>
              <select v-model="form.category" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold">
                <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Display Date Label</label>
              <input type="text" required v-model="form.date" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="e.g. Every Friday OR 2026-06-15" />
            </div>
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Display Time Label</label>
              <input type="text" required v-model="form.time" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="e.g. 1:30 PM OR 6:00 PM - 8:30 PM" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Sorting Start Time (Calendar order)</label>
              <input type="datetime-local" required v-model="form.start_date" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium" />
            </div>
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Sorting End Time (Optional)</label>
              <input type="datetime-local" v-model="form.end_date" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2 md:col-span-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Physical / Online Location</label>
              <input type="text" required v-model="form.location" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="SFU Burnaby, WMC 3260" />
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Banner Image URL</label>
            <input type="text" v-model="form.image" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="Paste URL from Media Library or /Hero/..." />
            <div v-if="form.image" class="pt-2">
              <img
                :src="resolvePublicImagePath(form.image)"
                alt="Event banner preview"
                class="w-full max-h-40 object-cover rounded-2xl border border-neutral-gray/20"
              />
            </div>
          </div>

          <!-- Rich Text Description Editor -->
          <RichEditor 
            v-model="form.description"
            label="Event Details & Description"
            placeholder="Write information about topics, speakers, catering details, etc."
          />

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-neutral-gray/10">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Status</label>
              <div class="flex gap-2">
                <label 
                  v-for="st in (['draft', 'published', 'archived'] as const)" 
                  :key="st"
                  :class="[
                    'flex-1 border rounded-2xl py-3 text-center cursor-pointer transition-all duration-300 font-bold uppercase tracking-wider text-[9px]',
                    form.status === st ? 'border-primary bg-primary/5 text-primary' : 'border-neutral-gray/20 text-neutral-black/45'
                  ]"
                >
                  <input type="radio" v-model="form.status" :value="st" class="hidden" />
                  {{ st }}
                </label>
              </div>
            </div>

            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Highlight Feature</label>
              <label 
                :class="[
                  'w-full flex items-center justify-center gap-2 border rounded-2xl py-3.5 cursor-pointer transition-all duration-300 font-bold uppercase tracking-wider text-[9px]',
                  form.featured ? 'border-accent-gold bg-accent-gold/5 text-accent-gold' : 'border-neutral-gray/20 text-neutral-black/45 hover:bg-neutral-background/50'
                ]"
              >
                <input type="checkbox" v-model="form.featured" class="hidden" />
                <Star :size="12" :class="form.featured ? 'fill-accent-gold' : ''" /> Featured Event Banner (Hero Countdown)
              </label>
            </div>
          </div>

          <div class="pt-6 border-t border-neutral-gray/10 flex justify-end gap-4">
            <button type="button" @click="isFormOpen = false" class="px-6 py-4 border border-neutral-gray/20 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-neutral-background cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="isLoading" class="flex items-center gap-2 px-8 py-4 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer">
              <Save :size="14" /> {{ isLoading ? 'Saving...' : 'Save Scheduled Event' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- REVISIONS MODAL -->
    <div v-if="isRevisionsOpen" class="fixed inset-0 z-50 flex items-center justify-center p-5">
      <div class="absolute inset-0 bg-primary/20 backdrop-blur-md" @click="isRevisionsOpen = false"></div>
      <div class="bg-white rounded-[2.5rem] border border-neutral-gray/20 p-8 sm:p-10 shadow-premium max-w-xl w-full relative z-10 max-h-[80vh] overflow-y-auto">
        <button @click="isRevisionsOpen = false" class="absolute top-6 right-6 p-2 text-neutral-black/35 hover:text-primary transition-colors cursor-pointer">
          <X :size="20" />
        </button>

        <h3 class="text-2xl font-display font-extrabold text-primary uppercase mb-6 flex items-center gap-2">
          <History :size="24" /> Revisions Log
        </h3>
        
        <p class="text-neutral-black/40 text-xs mb-6" v-if="activeEvent">
          Version history tracking for <b>{{ activeEvent.title }}</b>.
        </p>

        <div v-if="revisionsList.length === 0" class="py-12 text-center text-neutral-black/30 text-xs">
          No revision history found. Make changes to create versions.
        </div>

        <div v-else class="space-y-4">
          <div 
            v-for="rev in revisionsList" 
            :key="rev.id"
            class="p-5 border border-neutral-gray/10 bg-neutral-background/30 rounded-3xl flex items-center justify-between hover:border-accent-gold/45 hover:bg-white transition-all duration-300 group"
          >
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="text-xs font-black text-primary">Version {{ rev.version }}</span>
                <span class="text-[9px] font-bold text-neutral-black/30">{{ new Date(rev.created_at).toLocaleString() }}</span>
              </div>
              <p class="text-[10px] text-neutral-black/55 font-medium leading-relaxed">
                By: <b class="text-secondary">{{ rev.user?.name || 'Admin Seeder' }}</b>
              </p>
            </div>
            
            <button 
              @click="handleRollback(rev.version)"
              class="px-4 py-2.5 bg-accent-gold/5 border border-accent-gold/10 text-accent-gold rounded-xl text-[9px] font-black uppercase tracking-wider hover:bg-accent-gold hover:text-white transition-all duration-300 flex items-center gap-1.5 cursor-pointer shadow-sm"
            >
              <RotateCcw :size="10" /> Rollback
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
