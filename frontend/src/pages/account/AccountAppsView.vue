<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { accountService, type AccountSummaryResponse } from '@/services/account/accountService';
import { LayoutGrid, ExternalLink, ShieldCheck, Sparkles } from 'lucide-vue-next';

const summary = ref<AccountSummaryResponse | null>(null);
const isLoading = ref<boolean>(true);
const errorMessage = ref<string | null>(null);

const loadSummary = async () => {
  isLoading.value = true;
  errorMessage.value = null;
  try {
    summary.value = await accountService.getSummary();
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load accessible applications.';
  } finally {
    isLoading.value = false;
  }
};

const memberApps = computed(() => {
  if (!summary.value) return [];
  return summary.value.applications.filter(app => !app.is_admin);
});

const adminApps = computed(() => {
  if (!summary.value) return [];
  return summary.value.applications.filter(app => app.is_admin);
});

onMounted(() => {
  loadSummary();
});
</script>

<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
      <div class="flex items-center gap-3">
        <LayoutGrid class="h-6 w-6 text-primary" />
        <div>
          <h2 class="text-lg font-display font-black text-primary">My Platform Applications</h2>
          <p class="text-xs text-neutral-black/60 font-medium">
            Launch and access all SFU MSA applications granted to your account.
          </p>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-16 flex flex-col items-center justify-center space-y-4">
      <div class="h-10 w-10 border-4 border-primary/20 border-t-primary rounded-full animate-spin" />
      <p class="text-xs font-bold text-neutral-black/60 uppercase tracking-wider">Loading your application access...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="bg-red-50 border border-red-200 rounded-2xl p-6 text-center text-red-700">
      <p class="font-bold text-sm">{{ errorMessage }}</p>
      <button @click="loadSummary" class="mt-3 px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-secondary transition-all">
        Retry
      </button>
    </div>

    <div v-else class="space-y-8">
      <!-- Member Applications Section -->
      <div class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft">
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-6">
          <div class="flex items-center gap-2">
            <Sparkles class="h-5 w-5 text-primary" />
            <h3 class="text-sm font-extrabold uppercase tracking-wider text-primary">Member Applications</h3>
          </div>
          <span class="text-xs font-bold text-neutral-black/50">{{ memberApps.length }} Available</span>
        </div>

        <div v-if="memberApps.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div 
            v-for="app in memberApps" 
            :key="app.slug"
            class="p-5 rounded-2xl border border-neutral-ivory bg-neutral-background/40 hover:bg-white hover:border-primary/40 hover:shadow-premium transition-all duration-300 flex flex-col justify-between group"
          >
            <div>
              <div class="flex items-center justify-between mb-2">
                <h4 class="font-black text-sm text-neutral-black group-hover:text-primary transition-colors">
                  {{ app.title }}
                </h4>
                <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                  Access Granted
                </span>
              </div>
              <p class="text-xs text-neutral-black/60 leading-relaxed font-medium">
                {{ app.description }}
              </p>
            </div>

            <div class="mt-6 pt-3 border-t border-neutral-ivory/60 flex items-center justify-between">
              <span class="text-[10px] text-neutral-black/40 font-bold uppercase tracking-wider">
                Source: {{ app.source }}
              </span>
              <router-link 
                :to="app.path" 
                class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-extrabold uppercase tracking-wider hover:bg-secondary transition-all inline-flex items-center gap-1.5 shadow-soft hover:shadow-premium"
              >
                Launch
                <ExternalLink class="h-3.5 w-3.5" />
              </router-link>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8 text-neutral-black/50">
          <p class="text-xs font-bold">No member applications currently assigned.</p>
        </div>
      </div>

      <!-- Administrative Applications Section -->
      <div v-if="adminApps.length > 0" class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft">
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-6">
          <div class="flex items-center gap-2">
            <ShieldCheck class="h-5 w-5 text-primary" />
            <h3 class="text-sm font-extrabold uppercase tracking-wider text-primary">Platform Administration</h3>
          </div>
          <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase bg-purple-50 text-purple-700 border border-purple-200">
            Staff & Administrative Portals
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div 
            v-for="app in adminApps" 
            :key="app.slug"
            class="p-5 rounded-2xl border border-purple-100 bg-purple-50/20 hover:bg-white hover:border-purple-300 hover:shadow-premium transition-all duration-300 flex flex-col justify-between group"
          >
            <div>
              <div class="flex items-center justify-between mb-2">
                <h4 class="font-black text-sm text-neutral-black group-hover:text-primary transition-colors">
                  {{ app.title }}
                </h4>
                <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase bg-purple-100 text-purple-800 border border-purple-200">
                  Admin
                </span>
              </div>
              <p class="text-xs text-neutral-black/60 leading-relaxed font-medium">
                {{ app.description }}
              </p>
            </div>

            <div class="mt-6 pt-3 border-t border-neutral-ivory/60 flex items-center justify-between">
              <span class="text-[10px] text-neutral-black/40 font-bold uppercase tracking-wider">
                Source: {{ app.source }}
              </span>
              <router-link 
                :to="app.path" 
                class="px-4 py-2 bg-neutral-black text-white hover:bg-primary rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all inline-flex items-center gap-1.5 shadow-soft"
              >
                Open Portal
                <ExternalLink class="h-3.5 w-3.5" />
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
