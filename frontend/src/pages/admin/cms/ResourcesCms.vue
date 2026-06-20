<script setup lang="ts">
import { ref, onMounted } from 'vue';
import cmsService from '@/services/cms/cmsService';
import type { Resource, CmsRevision } from '@/types/cms';
import { 
  Plus, 
  Search, 
  Edit, 
  Trash2, 
  History, 
  X, 
  Save, 
  RotateCcw,
  BookMarked,
  ExternalLink
} from 'lucide-vue-next';

// State variables
const resources = ref<Resource[]>([]);
const pagination = ref({ page: 1, lastPage: 1, total: 0 });
const searchQuery = ref('');
const statusFilter = ref('');
const categoryFilter = ref('');
const isLoading = ref(false);
const error = ref<string | null>(null);

// Modal state
const isFormOpen = ref(false);
const isRevisionsOpen = ref(false);
const activeResource = ref<Resource | null>(null);
const revisionsList = ref<CmsRevision[]>([]);

// Form state
const form = ref({
  title: '',
  description: '',
  category: 'Student Guides',
  icon_name: 'BookMarked',
  link: '',
  is_external: false,
  tagsInput: '', // Local string to split by commas
  status: 'published' as 'draft' | 'published' | 'archived',
});

const CATEGORIES = [
  'New Muslim',
  'Student Guides',
  'Prayer',
  'Mental Health',
  'Learning',
  'Campus Survival',
  'Chaplaincy',
  'Community'
];

const ICONS = [
  'Sparkles',
  'Users',
  'GraduationCap',
  'BookMarked',
  'MapPin',
  'Compass',
  'Stethoscope',
  'Heart',
  'Coffee',
  'MessageSquare'
];

onMounted(() => {
  fetchResources();
});

const fetchResources = async (page = 1) => {
  isLoading.value = true;
  error.value = null;
  try {
    const res = await cmsService.getResources({
      search: searchQuery.value,
      category: categoryFilter.value,
      status: statusFilter.value,
      page
    });
    resources.value = res.data;
    pagination.value = {
      page: res.current_page,
      lastPage: res.last_page,
      total: res.total
    };
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to retrieve library resources.';
  } finally {
    isLoading.value = false;
  }
};

const openCreateForm = () => {
  activeResource.value = null;
  form.value = {
    title: '',
    description: '',
    category: 'Student Guides',
    icon_name: 'BookMarked',
    link: '',
    is_external: false,
    tagsInput: '',
    status: 'published',
  };
  isFormOpen.value = true;
};

const openEditForm = (res: Resource) => {
  activeResource.value = res;
  form.value = {
    title: res.title,
    description: res.description,
    category: res.category,
    icon_name: res.icon_name,
    link: res.link,
    is_external: res.is_external,
    tagsInput: res.tags ? res.tags.join(', ') : '',
    status: res.status,
  };
  isFormOpen.value = true;
};

const handleSubmit = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    // Convert comma tags input to JSON array
    const tags = form.value.tagsInput
      .split(',')
      .map(tag => tag.trim().toLowerCase())
      .filter(tag => tag.length > 0);

    const payload = {
      title: form.value.title,
      description: form.value.description,
      category: form.value.category,
      icon_name: form.value.icon_name,
      link: form.value.link,
      is_external: form.value.is_external,
      tags: tags,
      status: form.value.status
    };

    if (activeResource.value) {
      await cmsService.updateResource(activeResource.value.uuid, payload);
    } else {
      await cmsService.createResource(payload);
    }
    isFormOpen.value = false;
    fetchResources(pagination.value.page);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to save resource details.';
  } finally {
    isLoading.value = false;
  }
};

const handleDelete = async (res: Resource) => {
  if (!confirm(`Delete resource "${res.title}"?`)) return;
  isLoading.value = true;
  error.value = null;
  try {
    await cmsService.deleteResource(res.uuid);
    fetchResources(pagination.value.page);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to delete resource.';
  } finally {
    isLoading.value = false;
  }
};

const openRevisions = async (res: Resource) => {
  activeResource.value = res;
  isRevisionsOpen.value = true;
  revisionsList.value = [];
  try {
    revisionsList.value = await cmsService.getResourceRevisions(res.uuid);
  } catch (err) {
    console.error('Failed to load version history:', err);
  }
};

const handleRollback = async (version: number) => {
  if (!activeResource.value) return;
  if (!confirm(`Rollback "${activeResource.value.title}" to version ${version}?`)) return;
  
  isLoading.value = true;
  try {
    await cmsService.rollbackResource(activeResource.value.uuid, version);
    isRevisionsOpen.value = false;
    fetchResources(pagination.value.page);
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
        <h1 class="text-4xl font-display font-extrabold text-primary">Resources CMS</h1>
        <p class="text-neutral-black/45 text-sm">Organize student policy documents, Islamic guides, revert support files, and external references.</p>
      </div>
      <button 
        @click="openCreateForm"
        class="w-fit flex items-center gap-2 px-5 py-3 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer"
      >
        <Plus :size="14" /> Add Resource
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-neutral-gray/10 p-4 rounded-3xl shadow-soft flex flex-col md:flex-row gap-4 items-center">
      <div class="relative flex-grow w-full">
        <Search class="absolute left-4.5 top-1/2 -translate-y-1/2 text-neutral-black/25" :size="16" />
        <input 
          type="text" 
          placeholder="Search resources..." 
          v-model="searchQuery"
          @input="fetchResources(1)"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 pl-12 pr-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
        />
      </div>
      
      <div class="w-full md:w-56">
        <select 
          v-model="categoryFilter" 
          @change="fetchResources(1)"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold"
        >
          <option value="">All Categories</option>
          <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
        </select>
      </div>
    </div>

    <!-- Alert status -->
    <div v-if="error" class="p-4 bg-secondary/10 border border-secondary/20 text-secondary rounded-2xl text-xs font-bold uppercase tracking-wider">
      {{ error }}
    </div>

    <!-- Resources List -->
    <div class="bg-white border border-neutral-gray/10 rounded-[2rem] shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-xs text-neutral-black">
          <thead class="bg-neutral-background/50 border-b border-neutral-gray/10 uppercase font-black text-neutral-black/40 tracking-wider">
            <tr>
              <th class="p-6">Title</th>
              <th class="p-6">Category</th>
              <th class="p-6">Icon Key</th>
              <th class="p-6">Link Address</th>
              <th class="p-6">Status</th>
              <th class="p-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-gray/10">
            <tr v-if="resources.length === 0">
              <td colspan="6" class="p-12 text-center text-neutral-black/30">
                No resources found. Click "Add Resource" to create one.
              </td>
            </tr>
            <tr 
              v-for="res in resources" 
              :key="res.uuid"
              class="hover:bg-neutral-background/20 transition-colors"
            >
              <td class="p-6">
                <div class="space-y-1">
                  <div class="font-bold text-primary max-w-xs truncate">{{ res.title }}</div>
                  <div class="flex gap-1">
                    <span v-for="tag in res.tags" :key="tag" class="text-[8px] text-neutral-black/30 font-mono">#{{ tag }}</span>
                  </div>
                </div>
              </td>
              <td class="p-6 text-neutral-black/60 font-semibold">
                {{ res.category }}
              </td>
              <td class="p-6">
                <span class="font-mono text-[10px] bg-neutral-background border border-neutral-gray/25 px-2 py-1 rounded-lg text-primary font-bold">
                  {{ res.icon_name }}
                </span>
              </td>
              <td class="p-6 text-neutral-black/55 max-w-xs truncate">
                <a :href="res.link" target="_blank" class="hover:underline flex items-center gap-1">
                  {{ res.link }} <ExternalLink :size="10" />
                </a>
              </td>
              <td class="p-6">
                <span 
                  :class="[
                    'px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider',
                    res.status === 'published' ? 'bg-secondary/10 text-secondary border border-secondary/20' : '',
                    res.status === 'draft' ? 'bg-neutral-gray/20 text-neutral-black/60 border border-neutral-gray/30' : '',
                    res.status === 'archived' ? 'bg-secondary/10 text-secondary border border-secondary/20' : ''
                  ]"
                >
                  {{ res.status }}
                </span>
              </td>
              <td class="p-6 text-right space-x-2">
                <button @click="openEditForm(res)" class="p-2.5 bg-primary/5 hover:bg-primary hover:text-white rounded-xl text-primary transition-all duration-300 cursor-pointer" title="Edit">
                  <Edit :size="14" />
                </button>
                <button @click="openRevisions(res)" class="p-2.5 bg-accent-gold/5 hover:bg-accent-gold hover:text-white rounded-xl text-accent-gold transition-all duration-300 cursor-pointer" title="Revisions">
                  <History :size="14" />
                </button>
                <button @click="handleDelete(res)" class="p-2.5 bg-secondary/5 hover:bg-secondary hover:text-white rounded-xl text-secondary transition-all duration-300 cursor-pointer" title="Delete">
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
            @click="fetchResources(pagination.page - 1)"
            class="px-4 py-2 border border-neutral-gray/20 rounded-xl text-[10px] font-bold uppercase cursor-pointer disabled:opacity-40"
          >
            Prev
          </button>
          <button 
            :disabled="pagination.page === pagination.lastPage"
            @click="fetchResources(pagination.page + 1)"
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
      <div class="bg-white rounded-[2.5rem] border border-neutral-gray/20 p-8 sm:p-10 shadow-premium max-w-2xl w-full relative z-10 max-h-[90vh] overflow-y-auto">
        <button @click="isFormOpen = false" class="absolute top-6 right-6 p-2 text-neutral-black/35 hover:text-primary transition-colors cursor-pointer">
          <X :size="20" />
        </button>

        <h3 class="text-2xl font-display font-extrabold text-primary uppercase mb-6">
          {{ activeResource ? 'Edit Resource' : 'Add Resource' }}
        </h3>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Resource Title</label>
              <input type="text" required v-model="form.title" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="Religious Accommodation Policy" />
            </div>
            
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Resource Category</label>
              <select v-model="form.category" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold">
                <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Lucide Icon Class</label>
              <select v-model="form.icon_name" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold">
                <option v-for="icon in ICONS" :key="icon" :value="icon">{{ icon }}</option>
              </select>
            </div>
            
            <div class="space-y-2 flex items-end">
              <label 
                :class="[
                  'w-full flex items-center justify-center gap-2 border rounded-2xl py-4 cursor-pointer transition-all duration-300 font-bold uppercase tracking-wider text-[10px]',
                  form.is_external ? 'border-secondary bg-secondary/5 text-secondary' : 'border-neutral-gray/20 text-neutral-black/45 hover:bg-neutral-background/50'
                ]"
              >
                <input type="checkbox" v-model="form.is_external" class="hidden" />
                <BookMarked :size="14" /> Opens in New Tab (External Link)
              </label>
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">URL Link Address / File Link</label>
            <input type="text" required v-model="form.link" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="https://www.sfu.ca/... OR /contact" />
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Tags List (Comma separated)</label>
            <input type="text" v-model="form.tagsInput" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="sfu, policy, exams, guide" />
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Short Descriptive Summary</label>
            <textarea v-model="form.description" required rows="3" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium leading-relaxed" placeholder="Briefly describe what this resource covers..."></textarea>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Status</label>
            <div class="flex gap-4">
              <label 
                v-for="st in (['draft', 'published', 'archived'] as const)" 
                :key="st"
                :class="[
                  'flex-1 border rounded-2xl p-4 text-center cursor-pointer transition-all duration-300 font-bold uppercase tracking-wider text-[10px]',
                  form.status === st ? 'border-primary bg-primary/5 text-primary' : 'border-neutral-gray/20 text-neutral-black/45'
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
              <Save :size="14" /> {{ isLoading ? 'Saving...' : 'Save Resource' }}
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
        
        <p class="text-neutral-black/40 text-xs mb-6" v-if="activeResource">
          Version history tracking for <b>{{ activeResource.title }}</b>.
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
