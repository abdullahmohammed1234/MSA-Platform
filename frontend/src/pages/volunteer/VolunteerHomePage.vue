<template>
  <div class="volunteer-home-page min-h-screen bg-slate-900 text-slate-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-10">
      
      <!-- Hero Header -->
      <div class="text-center space-y-4 max-w-3xl mx-auto">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
          SFU MSA Volunteering
        </span>
        <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight bg-gradient-to-r from-white via-slate-200 to-emerald-400 bg-clip-text text-transparent">
          Serve Your Community
        </h1>
        <p class="text-lg text-slate-400">
          Join our dedicated team of volunteers. Gain valuable experience, build sisterhood and brotherhood, and earn reward by helping run SFU MSA events and programs.
        </p>
        <div class="flex flex-wrap justify-center gap-4 pt-2">
          <router-link
            to="/volunteer/my-history"
            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-emerald-300 bg-emerald-900/30 border border-emerald-500/30 hover:bg-emerald-800/40 transition"
          >
            My Volunteer History &rarr;
          </router-link>
        </div>
      </div>

      <!-- Search & Filters Bar -->
      <div class="bg-slate-800/80 rounded-2xl border border-slate-700/60 p-4 sm:p-6 backdrop-blur-md shadow-xl flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="relative w-full md:w-96">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search opportunities or locations..."
            class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
            @input="fetchOpportunities"
          />
          <svg class="w-5 h-5 absolute left-3 top-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto justify-end">
          <span class="text-sm text-slate-400">
            Showing <strong class="text-slate-200">{{ opportunities.length }}</strong> opportunities
          </span>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="i in 6" :key="i" class="h-64 rounded-2xl bg-slate-800/50 animate-pulse border border-slate-800"></div>
      </div>

      <!-- Empty State -->
      <div v-else-if="opportunities.length === 0" class="text-center py-16 bg-slate-800/40 rounded-3xl border border-slate-800">
        <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
        <h3 class="text-xl font-semibold text-slate-300">No Volunteer Opportunities Found</h3>
        <p class="text-slate-500 mt-1 max-w-md mx-auto">There are currently no active volunteer positions matching your search. Please check back soon!</p>
      </div>

      <!-- Opportunity Cards Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="opp in opportunities"
          :key="opp.id"
          class="group bg-slate-800/70 border border-slate-700/60 hover:border-emerald-500/40 rounded-2xl p-6 transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-950/30 flex flex-col justify-between"
        >
          <div class="space-y-4">
            <!-- Event Link Badge if any -->
            <div class="flex items-center justify-between">
              <span v-if="opp.event" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-800/50">
                Event: {{ opp.event.name }}
              </span>
              <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-slate-700/50 text-slate-300">
                Standalone Opportunity
              </span>
              <span class="text-xs text-slate-400 font-mono">
                {{ opp.shifts?.length || 0 }} shifts
              </span>
            </div>

            <!-- Title & Description -->
            <div>
              <h3 class="text-xl font-bold text-slate-100 group-hover:text-emerald-400 transition">
                {{ opp.title }}
              </h3>
              <p class="text-slate-400 text-sm mt-2 line-clamp-3">
                {{ opp.description || 'No description provided for this opportunity.' }}
              </p>
            </div>

            <!-- Meta Details -->
            <div class="space-y-2 pt-2 border-t border-slate-700/40 text-xs text-slate-300">
              <div v-if="opp.location" class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>{{ opp.location }}</span>
              </div>
              <div v-if="opp.start_at" class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>{{ formatDate(opp.start_at) }}</span>
              </div>
            </div>
          </div>

          <div class="pt-6">
            <router-link
              :to="`/volunteer/${opp.slug}`"
              class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-xl font-semibold text-sm text-slate-900 bg-emerald-400 hover:bg-emerald-300 transition shadow-lg shadow-emerald-950/20"
            >
              View & Sign Up
            </router-link>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { volunteeringService, type VolunteerOpportunity } from '../../services/volunteeringService';

const opportunities = ref<VolunteerOpportunity[]>([]);
const searchQuery = ref('');
const loading = ref(true);

const fetchOpportunities = async () => {
  loading.value = true;
  try {
    const res = await volunteeringService.getPublicOpportunities({
      search: searchQuery.value,
    });
    opportunities.value = res.data || [];
  } catch (err) {
    console.error('Failed to load volunteer opportunities:', err);
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

onMounted(() => {
  fetchOpportunities();
});
</script>
