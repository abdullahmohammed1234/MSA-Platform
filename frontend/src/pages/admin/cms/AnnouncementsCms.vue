<script setup lang="ts">
import { ref, onMounted } from 'vue';
import cmsService from '@/services/cms/cmsService';
import type { Announcement, CmsRevision } from '@/types/cms';
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
  RotateCcw
} from 'lucide-vue-next';

// State variables
const announcements = ref<Announcement[]>([]);
const pagination = ref({ page: 1, lastPage: 1, total: 0 });
const searchQuery = ref('');
const statusFilter = ref('');
const isLoading = ref(false);
const error = ref<string | null>(null);

// Modal state
const isFormOpen = ref(false);
const isRevisionsOpen = ref(false);
const activeAnnouncement = ref<Announcement | null>(null);
const revisionsList = ref<CmsRevision[]>([]);

// Form state
const form = ref({
  title: '',
  content: '',
  summary: '',
  featured_image: '',
  status: 'draft' as 'draft' | 'published' | 'archived',
});

onMounted(() => {
  fetchAnnouncements();
});

const fetchAnnouncements = async (page = 1) => {
  isLoading.value = true;
  error.value = null;
  try {
    const res = await cmsService.getAnnouncements({
      search: searchQuery.value,
      status: statusFilter.value,
      page
    });
    announcements.value = res.data;
    pagination.value = {
      page: res.current_page,
      lastPage: res.last_page,
      total: res.total
    };
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to retrieve announcements.';
  } finally {
    isLoading.value = false;
  }
};

const openCreateForm = () => {
  activeAnnouncement.value = null;
  form.value = {
    title: '',
    content: '',
    summary: '',
    featured_image: '',
    status: 'draft',
  };
  isFormOpen.value = true;
};

const openEditForm = (ann: Announcement) => {
  activeAnnouncement.value = ann;
  form.value = {
    title: ann.title,
    content: ann.content,
    summary: ann.summary || '',
    featured_image: ann.featured_image || '',
    status: ann.status,
  };
  isFormOpen.value = true;
};

const handleSubmit = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    if (activeAnnouncement.value) {
      await cmsService.updateAnnouncement(activeAnnouncement.value.uuid, form.value);
    } else {
      await cmsService.createAnnouncement(form.value);
    }
    isFormOpen.value = false;
    fetchAnnouncements(pagination.value.page);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to save announcement details.';
  } finally {
    isLoading.value = false;
  }
};

const handleDelete = async (ann: Announcement) => {
  if (!confirm(`Are you sure you want to delete "${ann.title}"?`)) return;
  isLoading.value = true;
  error.value = null;
  try {
    await cmsService.deleteAnnouncement(ann.uuid);
    fetchAnnouncements(pagination.value.page);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to delete announcement.';
  } finally {
    isLoading.value = false;
  }
};

const openRevisions = async (ann: Announcement) => {
  activeAnnouncement.value = ann;
  isRevisionsOpen.value = true;
  revisionsList.value = [];
  try {
    revisionsList.value = await cmsService.getAnnouncementRevisions(ann.uuid);
  } catch (err) {
    console.error('Failed to load version history:', err);
  }
};

const handleRollback = async (version: number) => {
  if (!activeAnnouncement.value) return;
  if (!confirm(`Rollback "${activeAnnouncement.value.title}" to version ${version}?`)) return;
  
  isLoading.value = true;
  try {
    await cmsService.rollbackAnnouncement(activeAnnouncement.value.uuid, version);
    isRevisionsOpen.value = false;
    fetchAnnouncements(pagination.value.page);
  } catch (err: any) {
    alert(err.response?.data?.message || 'Rollback failed.');
  } finally {
    isLoading.value = false;
  }
};

const formatDate = (dateStr?: string | null) => {
  if (!dateStr) return 'Not published';
  return new Date(dateStr).toLocaleDateString();
};
</script>

<template>
  <div class="space-y-8 text-neutral-black">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
      <div>
        <h1 class="text-4xl font-display font-extrabold text-primary">Announcements CMS</h1>
        <p class="text-neutral-black/45 text-sm">Manage public alerts and announcements displayed on the landing page.</p>
      </div>
      <button 
        @click="openCreateForm"
        class="w-fit flex items-center gap-2 px-5 py-3 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer"
      >
        <Plus :size="14" /> Create Announcement
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-neutral-gray/10 p-4 rounded-3xl shadow-soft flex flex-col md:flex-row gap-4 items-center">
      <div class="relative flex-grow w-full">
        <Search class="absolute left-4.5 top-1/2 -translate-y-1/2 text-neutral-black/25" :size="16" />
        <input 
          type="text" 
          placeholder="Search announcements..." 
          v-model="searchQuery"
          @input="fetchAnnouncements(1)"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 pl-12 pr-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
        />
      </div>
      
      <div class="w-full md:w-48">
        <select 
          v-model="statusFilter" 
          @change="fetchAnnouncements(1)"
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

    <!-- Announcements List -->
    <div class="bg-white border border-neutral-gray/10 rounded-[2rem] shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-xs text-neutral-black">
          <thead class="bg-neutral-background/50 border-b border-neutral-gray/10 uppercase font-black text-neutral-black/40 tracking-wider">
            <tr>
              <th class="p-6">Title</th>
              <th class="p-6">Category / Summary</th>
              <th class="p-6">Status</th>
              <th class="p-6">Published Date</th>
              <th class="p-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-gray/10">
            <tr v-if="announcements.length === 0">
              <td colspan="5" class="p-12 text-center text-neutral-black/30">
                No announcements found. Click "Create Announcement" to add one.
              </td>
            </tr>
            <tr 
              v-for="ann in announcements" 
              :key="ann.uuid"
              class="hover:bg-neutral-background/20 transition-colors"
            >
              <td class="p-6 font-bold text-primary max-w-xs truncate">
                {{ ann.title }}
              </td>
              <td class="p-6 text-neutral-black/60 max-w-xs truncate">
                {{ ann.summary || 'General' }}
              </td>
              <td class="p-6">
                <span 
                  :class="[
                    'px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider',
                    ann.status === 'published' ? 'bg-secondary/10 text-secondary border border-secondary/20' : '',
                    ann.status === 'draft' ? 'bg-neutral-gray/20 text-neutral-black/60 border border-neutral-gray/30' : '',
                    ann.status === 'archived' ? 'bg-secondary/10 text-secondary border border-secondary/20' : ''
                  ]"
                >
                  {{ ann.status }}
                </span>
              </td>
              <td class="p-6 text-neutral-black/55">
                {{ formatDate(ann.published_at) }}
              </td>
              <td class="p-6 text-right space-x-2">
                <button @click="openEditForm(ann)" class="p-2.5 bg-primary/5 hover:bg-primary hover:text-white rounded-xl text-primary transition-all duration-300 cursor-pointer" title="Edit">
                  <Edit :size="14" />
                </button>
                <button @click="openRevisions(ann)" class="p-2.5 bg-accent-gold/5 hover:bg-accent-gold hover:text-white rounded-xl text-accent-gold transition-all duration-300 cursor-pointer" title="Revisions">
                  <History :size="14" />
                </button>
                <button @click="handleDelete(ann)" class="p-2.5 bg-secondary/5 hover:bg-secondary hover:text-white rounded-xl text-secondary transition-all duration-300 cursor-pointer" title="Delete">
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
            @click="fetchAnnouncements(pagination.page - 1)"
            class="px-4 py-2 border border-neutral-gray/20 rounded-xl text-[10px] font-bold uppercase cursor-pointer disabled:opacity-40"
          >
            Prev
          </button>
          <button 
            :disabled="pagination.page === pagination.lastPage"
            @click="fetchAnnouncements(pagination.page + 1)"
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
          {{ activeAnnouncement ? 'Edit Announcement' : 'Create Announcement' }}
        </h3>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Announcement Title</label>
            <input type="text" required v-model="form.title" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="Weekly Friday Prayer Location Update" />
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Category / Short Summary</label>
            <input type="text" v-model="form.summary" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="Prayer (displayed as tag / preview text)" />
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Featured Image URL (Optional)</label>
            <input type="text" v-model="form.featured_image" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="Paste URL from Media Library" />
            <div v-if="form.featured_image" class="pt-2">
              <img
                :src="resolvePublicImagePath(form.featured_image)"
                alt="Announcement image preview"
                class="w-full max-h-40 object-cover rounded-2xl border border-neutral-gray/20"
              />
            </div>
          </div>

          <!-- Rich Text Content Editor -->
          <RichEditor 
            v-model="form.content"
            label="Full Content Body"
            placeholder="Write details of the announcement..."
          />

          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Publishing Lifecycle State</label>
            <div class="flex gap-4">
              <label 
                v-for="st in (['draft', 'published', 'archived'] as const)" 
                :key="st"
                :class="[
                  'flex-1 border rounded-2xl p-4 text-center cursor-pointer transition-all duration-300 font-bold uppercase tracking-wider text-[10px]',
                  form.status === st ? 'border-primary bg-primary/5 text-primary' : 'border-neutral-gray/20 text-neutral-black/40 hover:bg-neutral-background/50'
                ]"
              >
                <input type="radio" v-model="form.status" :value="st" class="hidden" />
                {{ st }}
              </label>
            </div>
          </div>

          <div class="pt-6 border-t border-neutral-gray/10 flex justify-end gap-4">
            <button type="button" @click="isFormOpen = false" class="px-6 py-4 border border-neutral-gray/20 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-neutral-background cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="isLoading" class="flex items-center gap-2 px-8 py-4 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer">
              <Save :size="14" /> {{ isLoading ? 'Saving...' : 'Save Announcement' }}
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
        
        <p class="text-neutral-black/40 text-xs mb-6" v-if="activeAnnouncement">
          Version history tracking for <b>{{ activeAnnouncement.title }}</b>.
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
              title="Rollback to this state"
            >
              <RotateCcw :size="10" /> Rollback
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
