<template>
  <div class="volunteer-home-page min-h-screen bg-neutral-background text-neutral-black pt-24 sm:pt-32 pb-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-12 sm:space-y-16">
      
      <!-- HERO SECTION -->
      <section class="relative rounded-3xl bg-white border border-neutral-ivory p-6 sm:p-12 shadow-premium overflow-hidden text-center sm:text-left">
        <!-- Background Decorative Elements -->
        <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-accent-gold/20 blur-3xl pointer-events-none" />
        <div class="absolute -left-16 -bottom-16 w-64 h-64 rounded-full bg-secondary/10 blur-3xl pointer-events-none" />

        <div class="relative z-10 max-w-3xl space-y-6">
          <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-widest bg-primary/10 text-primary border border-primary/20">
            <Heart class="w-3.5 h-3.5 text-secondary" />
            <span>SFU MSA Volunteering</span>
          </div>

          <h1 class="text-3xl sm:text-5xl font-display font-extrabold text-primary leading-tight tracking-tight">
            Serve. Connect. <br class="hidden sm:inline" />
            <span class="text-secondary">Make an Impact.</span>
          </h1>

          <p class="text-base sm:text-lg text-neutral-black/70 leading-relaxed font-sans">
            Join our dedicated team of volunteers. Gain valuable experience, build sisterhood and brotherhood, and earn rewards by supporting SFU MSA events and community programs.
          </p>

          <div class="flex flex-wrap items-center gap-4 pt-2 justify-center sm:justify-start">
            <a
              href="#opportunities"
              class="inline-flex items-center gap-2 px-6 py-3.5 rounded-full text-xs font-extrabold uppercase tracking-wider text-white bg-primary hover:bg-secondary transition-all shadow-brand hover:shadow-premium"
            >
              <span>Explore Opportunities</span>
              <ArrowDown class="w-4 h-4" />
            </a>

            <router-link
              to="/volunteer/my-history"
              class="inline-flex items-center gap-2 px-6 py-3.5 rounded-full text-xs font-extrabold uppercase tracking-wider text-primary border border-primary/30 hover:bg-primary/5 transition-all"
            >
              <Clock class="w-4 h-4 text-primary" />
              <span>My Volunteer History</span>
            </router-link>
          </div>
        </div>
      </section>

      <!-- HOW IT WORKS SECTION -->
      <section class="space-y-8">
        <div class="text-center max-w-2xl mx-auto space-y-3">
          <span class="text-xs font-extrabold uppercase tracking-widest text-secondary">Simple 4-Step Process</span>
          <h2 class="text-2xl sm:text-3xl font-display font-extrabold text-primary">How Volunteering Works</h2>
          <p class="text-sm text-neutral-black/70">Getting involved with SFU MSA takes only a couple of minutes.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div
            v-for="(step, idx) in steps"
            :key="idx"
            class="bg-white rounded-3xl border border-neutral-ivory p-6 shadow-soft hover:shadow-premium transition-all space-y-3 relative"
          >
            <div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary font-mono font-extrabold text-sm flex items-center justify-center">
              0{{ idx + 1 }}
            </div>
            <h3 class="text-lg font-bold text-primary">{{ step.title }}</h3>
            <p class="text-xs text-neutral-black/70 leading-relaxed">{{ step.description }}</p>
          </div>
        </div>
      </section>

      <!-- DISCOVERY / OPPORTUNITIES SECTION -->
      <section id="opportunities" class="space-y-6 scroll-mt-24">
        <div class="flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
          <div>
            <h2 class="text-2xl sm:text-3xl font-display font-extrabold text-primary">Current Opportunities</h2>
            <p class="text-xs sm:text-sm text-neutral-black/70 mt-1">Browse active volunteer roles for upcoming events and platform activities.</p>
          </div>

          <!-- Search & Filter Controls -->
          <div class="w-full md:w-auto flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
            <div class="relative w-full sm:w-80">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search opportunities or locations..."
                class="w-full pl-10 pr-4 py-2.5 rounded-full bg-white border border-neutral-ivory/80 text-sm text-neutral-black placeholder-neutral-muted focus:outline-none focus:border-primary shadow-soft transition-all"
                @input="fetchOpportunities"
              />
              <Search class="w-4 h-4 absolute left-3.5 top-3 text-neutral-muted" />
            </div>

            <span class="text-xs font-extrabold text-neutral-muted uppercase tracking-wider self-center whitespace-nowrap">
              Showing <strong class="text-primary font-bold">{{ opportunities.length }}</strong> positions
            </span>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="i in 6" :key="i" class="h-64 rounded-3xl bg-white/60 border border-neutral-ivory animate-pulse" />
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-3xl p-8 text-center space-y-4 max-w-md mx-auto">
          <AlertCircle class="w-12 h-12 text-red-600 mx-auto" />
          <h3 class="text-lg font-bold text-red-900">Couldn't Load Opportunities</h3>
          <p class="text-xs text-red-700">{{ error }}</p>
          <button
            @click="fetchOpportunities"
            class="px-5 py-2.5 rounded-full bg-red-600 text-white font-extrabold text-xs uppercase tracking-wider hover:bg-red-700 transition"
          >
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="opportunities.length === 0" class="bg-white rounded-3xl border border-neutral-ivory p-12 text-center space-y-4 max-w-lg mx-auto shadow-soft">
          <Layers class="w-12 h-12 text-neutral-muted mx-auto" />
          <h3 class="text-xl font-bold text-primary">No Opportunities Found</h3>
          <p class="text-xs text-neutral-black/70">There are currently no active volunteer positions matching your search query. Please check back soon or clear filters.</p>
          <button
            v-if="searchQuery"
            @click="searchQuery = ''; fetchOpportunities();"
            class="px-5 py-2.5 rounded-full bg-primary/10 text-primary font-extrabold text-xs uppercase tracking-wider hover:bg-primary/20 transition"
          >
            Clear Search
          </button>
        </div>

        <!-- Opportunities Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <VolunteerOpportunityCard
            v-for="opp in opportunities"
            :key="opp.id"
            :opportunity="opp"
          />
        </div>
      </section>

      <!-- COMMUNITY CTA BANNER -->
      <section class="bg-gradient-to-r from-primary to-secondary rounded-3xl p-8 sm:p-12 text-white shadow-premium text-center space-y-6 relative overflow-hidden">
        <div class="relative z-10 max-w-2xl mx-auto space-y-4">
          <h2 class="text-2xl sm:text-4xl font-display font-extrabold text-white">Ready to make a difference?</h2>
          <p class="text-sm sm:text-base text-white/90">
            Sign up for shifts, connect with fellow student leaders, and help build a vibrant community at Simon Fraser University.
          </p>
          <div class="pt-2">
            <a
              href="#opportunities"
              class="inline-flex items-center gap-2 px-8 py-3.5 rounded-full text-xs font-extrabold uppercase tracking-wider bg-accent-gold text-neutral-black hover:bg-amber-300 transition-all shadow-lg"
            >
              Get Started Now
            </a>
          </div>
        </div>
      </section>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Heart, Search, ArrowDown, Clock, Layers, AlertCircle } from 'lucide-vue-next';
import { volunteeringService, type VolunteerOpportunity } from '@/services/volunteeringService';
import VolunteerOpportunityCard from '@/components/volunteering/VolunteerOpportunityCard.vue';

const opportunities = ref<VolunteerOpportunity[]>([]);
const searchQuery = ref('');
const loading = ref(true);
const error = ref<string | null>(null);

const steps = [
  { title: 'Discover', description: 'Explore open volunteer opportunities across SFU MSA events and programs.' },
  { title: 'Choose Team & Shift', description: 'Select the specific team role and shift timing that fits your schedule.' },
  { title: 'Sign Up', description: 'Fill out quick contact details to instantly confirm your volunteer spot.' },
  { title: 'Serve & Connect', description: 'Attend the shift, support your brothers and sisters, and earn community credit.' },
];

const fetchOpportunities = async () => {
  loading.value = true;
  error.value = null;
  try {
    const res = await volunteeringService.getPublicOpportunities({
      search: searchQuery.value,
    });
    opportunities.value = res.data || [];
  } catch (err: any) {
    console.error('Failed to load volunteer opportunities:', err);
    error.value = err?.response?.data?.message || 'Failed to fetch active volunteer opportunities.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchOpportunities();
});
</script>
