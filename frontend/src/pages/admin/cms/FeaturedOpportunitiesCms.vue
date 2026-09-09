<script setup lang="ts">
import { ref, onMounted } from 'vue';
import cmsService from '@/services/cms/cmsService';
import type { FeaturedOpportunity, CmsRevision } from '@/types/cms';
import ImageInput from '@/components/admin/ImageInput.vue';
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
  ExternalLink,
  Sparkles
} from 'lucide-vue-next';

// State variables
const opportunities = ref<FeaturedOpportunity[]>([]);
const pagination = ref({ page: 1, lastPage: 1, total: 0 });
const searchQuery = ref('');
const statusFilter = ref('');
const isLoading = ref(false);
const error = ref<string | null>(null);

// Modal state
const isFormOpen = ref(false);
const isRevisionsOpen = ref(false);
const activeOpportunity = ref<FeaturedOpportunity | null>(null);
const revisionsList = ref<CmsRevision[]>([]);

// Form state
const form = ref({
  title: '',
  slug: '',
  eyebrow: '',
  short_description: '',
  description: '',
  featured_image: '',
  external_url: '',
  features: [''] as string[],
  is_published: true,
  sort_order: 1,
});

onMounted(() => {
  fetchOpportunities();
});

const fetchOpportunities = async (page = 1) => {
  isLoading.value = true;
  error.value = null;
  try {
    const res = await cmsService.getFeaturedOpportunities({
      search: searchQuery.value,
      status: statusFilter.value,
      page
    });
    opportunities.value = res.data;
    pagination.value = {
      page: res.current_page,
      lastPage: res.last_page,
      total: res.total
    };
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to retrieve featured opportunities.';
  } finally {
    isLoading.value = false;
  }
};

const openCreateForm = () => {
  activeOpportunity.value = null;
  form.value = {
    title: '',
    slug: '',
    eyebrow: 'Featured Educational Resource',
    short_description: '',
    description: '',
    featured_image: '',
    external_url: '',
    features: [''],
    is_published: true,
    sort_order: opportunities.value.length + 1,
  };
  isFormOpen.value = true;
};

const openEditForm = (opp: FeaturedOpportunity) => {
  activeOpportunity.value = opp;
  form.value = {
    title: opp.title,
    slug: opp.slug,
    eyebrow: opp.eyebrow || '',
    short_description: opp.short_description || '',
    description: opp.description || '',
    featured_image: opp.featured_image || '',
    external_url: opp.external_url || '',
    features: opp.features && opp.features.length > 0 ? [...opp.features] : [''],
    is_published: opp.is_published,
    sort_order: opp.sort_order || 1,
  };
  isFormOpen.value = true;
};

const addFeatureInput = () => {
  form.value.features.push('');
};

const removeFeatureInput = (index: number) => {
  form.value.features.splice(index, 1);
  if (form.value.features.length === 0) {
    form.value.features.push('');
  }
};

const handleSubmit = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    const cleanedFeatures = form.value.features.filter(f => f.trim() !== '');
    const payload = {
      ...form.value,
      features: cleanedFeatures,
    };

    if (activeOpportunity.value) {
      await cmsService.updateFeaturedOpportunity(activeOpportunity.value.uuid, payload);
    } else {
      await cmsService.createFeaturedOpportunity(payload);
    }
    isFormOpen.value = false;
    fetchOpportunities(pagination.value.page);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to save featured opportunity.';
  } finally {
    isLoading.value = false;
  }
};

const handleDelete = async (opp: FeaturedOpportunity) => {
  if (!confirm(`Are you sure you want to delete "${opp.title}"?`)) return;
  isLoading.value = true;
  error.value = null;
  try {
    await cmsService.deleteFeaturedOpportunity(opp.uuid);
    fetchOpportunities(pagination.value.page);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to delete opportunity.';
  } finally {
    isLoading.value = false;
  }
};

const openRevisions = async (opp: FeaturedOpportunity) => {
  activeOpportunity.value = opp;
  isRevisionsOpen.value = true;
  revisionsList.value = [];
  try {
    revisionsList.value = await cmsService.getFeaturedOpportunityRevisions(opp.uuid);
  } catch (err) {
    console.error('Failed to load version history:', err);
  }
};

const handleRollback = async (version: number) => {
  if (!activeOpportunity.value) return;
  if (!confirm(`Rollback "${activeOpportunity.value.title}" to version ${version}?`)) return;
  
  isLoading.value = true;
  try {
    await cmsService.rollbackFeaturedOpportunity(activeOpportunity.value.uuid, version);
    isRevisionsOpen.value = false;
    fetchOpportunities(pagination.value.page);
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
        <h1 class="text-4xl font-display font-extrabold text-primary flex items-center gap-3">
          <Sparkles class="w-8 h-8 text-accent-gold" /> Learning Resources CMS
        </h1>
        <p class="text-neutral-black/45 text-sm">Manage public educational programs, initiatives, and partner learning resources highlighted across the platform.</p>
      </div>
      <button 
        @click="openCreateForm"
        class="w-fit flex items-center gap-2 px-5 py-3 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer"
      >
        <Plus :size="14" /> Create Opportunity
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-neutral-gray/10 p-4 rounded-3xl shadow-soft flex flex-col md:flex-row gap-4 items-center">
      <div class="relative flex-grow w-full">
        <Search class="absolute left-4.5 top-1/2 -translate-y-1/2 text-neutral-black/25" :size="16" />
        <input 
          type="text" 
          placeholder="Search opportunities by title or eyebrow..." 
          v-model="searchQuery"
          @input="fetchOpportunities(1)"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 pl-12 pr-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
        />
      </div>
      
      <div class="w-full md:w-48">
        <select 
          v-model="statusFilter" 
          @change="fetchOpportunities(1)"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold"
        >
          <option value="">All Statuses</option>
          <option value="published">Published</option>
          <option value="draft">Draft</option>
        </select>
      </div>
    </div>

    <!-- Alert status -->
    <div v-if="error" class="p-4 bg-secondary/10 border border-secondary/20 text-secondary rounded-2xl text-xs font-bold uppercase tracking-wider">
      {{ error }}
    </div>

    <!-- Opportunities Table -->
    <div class="bg-white border border-neutral-gray/10 rounded-[2rem] shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-xs text-neutral-black">
          <thead class="bg-neutral-background/50 border-b border-neutral-gray/10 uppercase font-black text-neutral-black/40 tracking-wider">
            <tr>
              <th class="p-6">Order</th>
              <th class="p-6">Opportunity Title</th>
              <th class="p-6">Eyebrow / Category</th>
              <th class="p-6">Status</th>
              <th class="p-6">External Link</th>
              <th class="p-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-gray/10">
            <tr v-if="opportunities.length === 0">
              <td colspan="6" class="p-12 text-center text-neutral-black/30">
                No featured opportunities found. Click "Create Opportunity" to add one.
              </td>
            </tr>
            <tr 
              v-for="opp in opportunities" 
              :key="opp.uuid"
              class="hover:bg-neutral-background/20 transition-colors"
            >
              <td class="p-6 font-bold text-neutral-black/40">
                #{{ opp.sort_order }}
              </td>
              <td class="p-6 font-bold text-primary max-w-xs truncate">
                {{ opp.title }}
              </td>
              <td class="p-6 text-neutral-black/60 max-w-xs truncate">
                <span class="inline-block px-2.5 py-1 bg-primary/5 text-primary rounded-lg text-[10px] font-bold">
                  {{ opp.eyebrow || 'Featured' }}
                </span>
              </td>
              <td class="p-6">
                <span 
                  :class="[
                    'px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider',
                    opp.is_published ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-neutral-gray/20 text-neutral-black/60 border border-neutral-gray/30'
                  ]"
                >
                  {{ opp.is_published ? 'Published' : 'Draft' }}
                </span>
              </td>
              <td class="p-6 text-neutral-black/55 max-w-xs truncate">
                <a 
                  v-if="opp.external_url" 
                  :href="opp.external_url" 
                  target="_blank" 
                  class="text-primary hover:underline inline-flex items-center gap-1 font-mono text-[11px]"
                >
                  <span>Link</span> <ExternalLink :size="10" />
                </a>
                <span v-else class="text-neutral-black/30 italic">None</span>
              </td>
              <td class="p-6 text-right space-x-2">
                <button @click="openEditForm(opp)" class="p-2.5 bg-primary/5 hover:bg-primary hover:text-white rounded-xl text-primary transition-all duration-300 cursor-pointer" title="Edit">
                  <Edit :size="14" />
                </button>
                <button @click="openRevisions(opp)" class="p-2.5 bg-accent-gold/5 hover:bg-accent-gold hover:text-white rounded-xl text-accent-gold transition-all duration-300 cursor-pointer" title="Revisions">
                  <History :size="14" />
                </button>
                <button @click="handleDelete(opp)" class="p-2.5 bg-secondary/5 hover:bg-secondary hover:text-white rounded-xl text-secondary transition-all duration-300 cursor-pointer" title="Delete">
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
            @click="fetchOpportunities(pagination.page - 1)"
            class="px-4 py-2 border border-neutral-gray/20 rounded-xl text-[10px] font-bold uppercase cursor-pointer disabled:opacity-40"
          >
            Prev
          </button>
          <button 
            :disabled="pagination.page === pagination.lastPage"
            @click="fetchOpportunities(pagination.page + 1)"
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
          {{ activeOpportunity ? 'Edit Featured Opportunity' : 'Create Featured Opportunity' }}
        </h3>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Opportunity Title *</label>
              <input type="text" required v-model="form.title" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="e.g. The Blessed Tree — Year One Program" />
            </div>

            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Eyebrow / Badge Label</label>
              <input type="text" v-model="form.eyebrow" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="e.g. Featured Educational Resource" />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">URL Slug (Optional)</label>
              <input type="text" v-model="form.slug" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="blessed-tree-year-one (auto-generated if empty)" />
            </div>

            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">External Destination URL</label>
              <input type="url" v-model="form.external_url" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="https://theblessedtree.org/programs/year-one" />
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Short Summary</label>
            <input type="text" v-model="form.short_description" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="Brief 1-sentence summary for card previews..." />
          </div>

          <ImageInput
            v-model="form.featured_image"
            label="Featured Image Asset"
            hint="Upload or select an image asset (e.g. /Hero/blessed_tree.webp)"
          />

          <!-- Rich Text Content Editor -->
          <RichEditor 
            v-model="form.description"
            label="Full Description"
            placeholder="Detailed description of the program, curriculum, and goals..."
          />

          <!-- Highlighted Features Builder -->
          <div class="space-y-3 p-5 bg-neutral-background/40 border border-neutral-gray/10 rounded-2xl">
            <div class="flex justify-between items-center">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Highlighted Key Features</label>
              <button 
                type="button" 
                @click="addFeatureInput"
                class="px-3 py-1.5 bg-primary/10 text-primary hover:bg-primary hover:text-white rounded-xl text-[10px] font-bold uppercase transition-all flex items-center gap-1 cursor-pointer"
              >
                <Plus :size="12" /> Add Feature
              </button>
            </div>
            <p class="text-[11px] text-neutral-black/40">Add key bullet points highlighted on the opportunity card.</p>
            
            <div class="space-y-2">
              <div v-for="(_feat, idx) in form.features" :key="idx" class="flex items-center gap-2">
                <input 
                  type="text" 
                  v-model="form.features[idx]" 
                  placeholder="e.g. Structured Year-Long Curriculum"
                  class="flex-1 bg-white border border-neutral-gray/20 rounded-xl py-2.5 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
                />
                <button 
                  type="button" 
                  @click="removeFeatureInput(idx)"
                  class="p-2.5 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-xl transition-all cursor-pointer"
                  title="Remove feature"
                >
                  <X :size="14" />
                </button>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Display Sort Order</label>
              <input type="number" v-model.number="form.sort_order" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="1" />
            </div>

            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Publication Status</label>
              <div class="flex gap-4 pt-1">
                <label 
                  :class="[
                    'flex-1 border rounded-2xl p-3 text-center cursor-pointer transition-all duration-300 font-bold uppercase tracking-wider text-[10px]',
                    form.is_published ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-neutral-gray/20 text-neutral-black/40 hover:bg-neutral-background/50'
                  ]"
                >
                  <input type="radio" :value="true" v-model="form.is_published" class="hidden" />
                  Published
                </label>
                <label 
                  :class="[
                    'flex-1 border rounded-2xl p-3 text-center cursor-pointer transition-all duration-300 font-bold uppercase tracking-wider text-[10px]',
                    !form.is_published ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-neutral-gray/20 text-neutral-black/40 hover:bg-neutral-background/50'
                  ]"
                >
                  <input type="radio" :value="false" v-model="form.is_published" class="hidden" />
                  Draft
                </label>
              </div>
            </div>
          </div>

          <div class="pt-6 border-t border-neutral-gray/10 flex justify-end gap-4">
            <button type="button" @click="isFormOpen = false" class="px-6 py-4 border border-neutral-gray/20 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-neutral-background cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="isLoading" class="flex items-center gap-2 px-8 py-4 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer">
              <Save :size="14" /> {{ isLoading ? 'Saving...' : 'Save Opportunity' }}
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
        
        <p class="text-neutral-black/40 text-xs mb-6" v-if="activeOpportunity">
          Version history tracking for <b>{{ activeOpportunity.title }}</b>.
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
                By: <b class="text-secondary">{{ rev.user?.name || 'CMS Admin' }}</b>
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
