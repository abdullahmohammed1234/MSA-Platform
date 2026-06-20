<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Platform Analytics</h1>
        <p class="text-sm text-neutral-muted mt-1">Central dashboard for SFU MSA web traffic, LMS engagement, and certification tracking.</p>
      </div>

      <!-- Filters & Export -->
      <AnalyticsFilters 
        :start="store.startDate"
        :end="store.endDate"
        @update:dates="handleDateUpdate"
        @export="handleExport"
      />
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-neutral-ivory flex gap-6 text-sm">
      <button 
        v-for="tab in ['Overview', 'Website', 'Academy', 'Scheduled Reports']" 
        :key="tab"
        @click="activeTab = tab"
        class="pb-3 font-semibold transition-colors relative cursor-pointer"
        :class="activeTab === tab ? 'text-primary' : 'text-neutral-muted hover:text-neutral-black'"
      >
        {{ tab }}
        <span 
          v-if="activeTab === tab" 
          class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary"
        ></span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
      {{ error }}
    </div>

    <!-- Tabs Content -->
    <div v-else class="space-y-8">
      <!-- 1. Overview Tab -->
      <div v-if="activeTab === 'Overview'" class="space-y-6">
        <!-- Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <AnalyticsCard 
            title="Total Unique Visitors"
            :value="store.overview?.kpis?.visitors?.value ?? 0"
            :change="store.overview?.kpis?.visitors?.change"
            description="Total unique visitor sessions logged."
          />
          <AnalyticsCard 
            title="Platform Page Views"
            :value="store.overview?.kpis?.page_views?.value ?? 0"
            :change="store.overview?.kpis?.page_views?.change"
            description="Raw page loads across all services."
          />
          <AnalyticsCard 
            title="Active Academy Learners"
            :value="store.overview?.kpis?.active_learners?.value ?? 0"
            :change="store.overview?.kpis?.active_learners?.change"
            description="Volunteers completing modules or quizzes."
          />
          <AnalyticsCard 
            title="Certificates Awarded"
            :value="store.overview?.kpis?.certificates?.value ?? 0"
            :change="store.overview?.kpis?.certificates?.change"
            description="LMS course certificates successfully issued."
          />
        </div>

        <!-- Recent Activity Feed -->
        <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6">
          <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black mb-4">Recent Activity Feed</h3>
          <div v-if="!store.overview?.recent_activity?.length" class="text-xs text-neutral-muted italic py-4 text-center">
            No recent activity tracked.
          </div>
          <div v-else class="divide-y divide-neutral-ivory">
            <div 
              v-for="(act, idx) in store.overview.recent_activity" 
              :key="idx"
              class="py-3.5 flex items-center justify-between text-sm"
            >
              <div class="flex items-center gap-3">
                <span 
                  class="h-2 w-2 rounded-full"
                  :class="{
                    'bg-secondary': act.type === 'completion',
                    'bg-gold-500': act.type === 'certificate',
                    'bg-sky-500': act.type === 'registration'
                  }"
                ></span>
                <span class="font-medium text-neutral-black">{{ act.user }}</span>
                <span class="text-neutral-muted">
                  {{ act.type === 'completion' ? 'completed course' : act.type === 'certificate' ? 'earned certificate' : 'registered for' }}
                </span>
                <span class="font-semibold text-primary">{{ act.detail }}</span>
              </div>
              <span class="text-xs text-neutral-muted">{{ act.time }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Website Analytics Tab -->
      <div v-else-if="activeTab === 'Website'" class="space-y-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Visitors Chart -->
          <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6 lg:col-span-2">
            <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black mb-4">Visitors Over Time</h3>
            <AnalyticsChart 
              type="area"
              :data="websiteChartData.values"
              :labels="websiteChartData.labels"
              :height="220"
            />
          </div>

          <!-- Traffic Sources -->
          <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6">
            <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black mb-4">Traffic Sources</h3>
            <AnalyticsChart 
              type="donut"
              :data="sourcesChartData.values"
              :labels="sourcesChartData.labels"
              :height="200"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Popular Pages -->
          <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6">
            <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black mb-4">Popular Pages</h3>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-neutral-ivory text-left text-xs font-bold uppercase tracking-wider text-neutral-muted">
                    <th class="pb-3">Page Route</th>
                    <th class="pb-3 text-right">Views</th>
                    <th class="pb-3 text-right">Unique Views</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-neutral-ivory/50">
                  <tr v-for="page in store.website?.popular_pages" :key="page.url">
                    <td class="py-3 font-medium text-neutral-black truncate max-w-[200px]" :title="page.url">
                      {{ page.url }}
                    </td>
                    <td class="py-3 text-right font-mono text-neutral-black">{{ page.views }}</td>
                    <td class="py-3 text-right font-mono text-neutral-muted">{{ page.unique_views }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- CTA Conversions -->
          <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6">
            <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black mb-4">CTA Clicks Performance</h3>
            <div class="space-y-4">
              <div v-for="cta in store.website?.cta_performance" :key="cta.name" class="space-y-2">
                <div class="flex justify-between text-xs font-semibold">
                  <span class="text-neutral-black">{{ cta.name }}</span>
                  <span class="text-neutral-muted">{{ cta.clicks }} Clicks</span>
                </div>
                <div class="w-full bg-neutral-background rounded-full h-2">
                  <div class="bg-primary h-2 rounded-full" :style="`width: ${Math.min(cta.clicks * 2, 100)}%`"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Academy Analytics Tab -->
      <div v-else-if="activeTab === 'Academy'" class="space-y-8">
        <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6">
          <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black mb-4">Course Completions</h3>
          <div class="space-y-5">
            <div v-for="c in store.academy?.summary?.course_performance" :key="c.course_id" class="space-y-2">
              <div class="flex justify-between text-xs font-semibold">
                <span class="text-neutral-black">{{ c.title }}</span>
                <span class="text-neutral-muted">{{ c.completed }} / {{ c.total }} Completed ({{ c.completion_rate }}%)</span>
              </div>
              <div class="w-full bg-neutral-background rounded-full h-2.5">
                <div class="bg-secondary h-2.5 rounded-full" :style="`width: ${c.completion_rate}%`"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Quiz performance -->
          <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6">
            <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black mb-4">Quiz Average Scores</h3>
            <AnalyticsChart 
              type="bar"
              :data="quizChartData.values"
              :labels="quizChartData.labels"
              :height="200"
            />
          </div>

          <!-- Top Volunteers -->
          <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6">
            <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black mb-4">Top Volunteers (Certificates)</h3>
            <div class="space-y-4">
              <div 
                v-for="v in store.academy?.top_volunteers" 
                :key="v.name"
                class="flex items-center justify-between py-2 border-b border-neutral-ivory/50"
              >
                <div class="flex items-center gap-3">
                  <div class="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-xs">
                    {{ v.name.charAt(0) }}
                  </div>
                  <span class="text-sm font-medium text-neutral-black">{{ v.name }}</span>
                </div>
                <span class="text-xs font-bold text-secondary bg-secondary/10 px-2 py-0.5 rounded-full">
                  {{ v.certificates }} Certificates
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 4. Scheduled Reports Tab -->
      <div v-else-if="activeTab === 'Scheduled Reports'" class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6">
        <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black mb-4">Generated Scheduled Reports</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-neutral-ivory text-left text-xs font-bold uppercase tracking-wider text-neutral-muted">
                <th class="pb-3">Report Title</th>
                <th class="pb-3">Period Type</th>
                <th class="pb-3">Generated At</th>
                <th class="pb-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory/50">
              <tr v-for="r in store.reports" :key="r.uuid">
                <td class="py-3.5 font-medium text-neutral-black">{{ r.title }}</td>
                <td class="py-3.5">
                  <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-primary/10 text-primary uppercase">
                    {{ r.type }}
                  </span>
                </td>
                <td class="py-3.5 text-neutral-muted">
                  {{ new Date(r.generated_at).toLocaleString() }}
                </td>
                <td class="py-3.5 text-right">
                  <button 
                    @click="downloadReportFile(r)"
                    class="text-xs text-primary font-semibold hover:underline cursor-pointer"
                  >
                    Download PDF
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useAnalyticsStore } from '@/stores/analytics';
import AnalyticsCard from '@/components/analytics/AnalyticsCard.vue';
import AnalyticsChart from '@/components/analytics/AnalyticsChart.vue';
import AnalyticsFilters from '@/components/analytics/AnalyticsFilters.vue';

const store = useAnalyticsStore();
const activeTab = ref('Overview');
const loading = computed(() => store.loading);
const error = computed(() => store.error);

const loadTab = () => {
  if (activeTab.value === 'Overview') {
    store.fetchOverview();
  } else if (activeTab.value === 'Website') {
    store.fetchWebsite();
  } else if (activeTab.value === 'Academy') {
    store.fetchAcademy();
  } else if (activeTab.value === 'Scheduled Reports') {
    store.fetchReports();
  }
};

onMounted(() => {
  loadTab();
});

watch(activeTab, () => {
  loadTab();
});

const handleDateUpdate = (dates: { start: string; end: string }) => {
  store.setDates(dates.start, dates.end);
  loadTab();
};

const handleExport = (format: 'csv' | 'pdf') => {
  const type = activeTab.value === 'Website' ? 'website' : activeTab.value === 'Academy' ? 'academy' : 'overview';
  store.exportData(format, type);
};

const downloadReportFile = (report: any) => {
  // Use the standard download helper or URL path
  const format = 'pdf';
  store.exportData(format, report.type);
};

// Formatter computed structures
const websiteChartData = computed(() => {
  const values = store.website?.visitors_over_time?.map((x: any) => x.count) || [];
  const labels = store.website?.visitors_over_time?.map((x: any) => x.date) || [];
  return { values, labels };
});

const sourcesChartData = computed(() => {
  const values = store.website?.traffic_sources?.map((x: any) => x.count) || [];
  const labels = store.website?.traffic_sources?.map((x: any) => x.source) || [];
  return { values, labels };
});

const quizChartData = computed(() => {
  const values = store.academy?.summary?.quiz_performance?.map((x: any) => x.average_score) || [];
  const labels = store.academy?.summary?.quiz_performance?.map((x: any) => x.title) || [];
  return { values, labels };
});
</script>
