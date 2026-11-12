<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { projectsApi } from '../api/projects';
import { useAuthStore } from '../stores/auth';
import type { DashboardAnalytics, DashboardData, Task } from '../types/pm';
import ProjectCard from '../components/projects/ProjectCard.vue';
import ProjectCreateModal from '../components/projects/ProjectCreateModal.vue';
import TaskDetailModal from '../components/tasks/TaskDetailModal.vue';
import StatusDonutChart from '../components/analytics/StatusDonutChart.vue';
import VelocityBarChart from '../components/analytics/VelocityBarChart.vue';
import {
  FolderKanban,
  CheckSquare,
  AlertTriangle,
  Clock,
  Plus,
  ArrowRight,
  Activity,
  Calendar,
  Loader2,
  TrendingUp,
  BarChart3,
} from 'lucide-vue-next';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const dashboardData = ref<DashboardData | null>(null);
const dashboardAnalytics = ref<DashboardAnalytics | null>(null);
const isLoading = ref(true);
const showCreateModal = ref(false);
const activeTaskUuid = ref<string | null>(null);
const activeProjectUuid = ref<string | null>(null);

const isLayoutPreview = computed(
  () => import.meta.env.DEV && route.query.preview === 'layout'
);

const donutSize = computed(() => {
  if (typeof window === 'undefined') return 132;
  const w = window.innerWidth;
  if (w < 380) return 112;
  if (w < 640) return 124;
  return 140;
});

const loadPreviewFixtures = (empty = false) => {
  dashboardData.value = {
    user: authStore.user as any,
    departments: authStore.departments as any,
    kpi: {
      total_projects: empty ? 0 : 12,
      active_projects: empty ? 0 : 7,
      completed_projects: empty ? 0 : 4,
      my_open_tasks: empty ? 0 : 9,
      my_overdue_tasks: empty ? 0 : 2,
    },
    my_tasks: empty
      ? []
      : [
          {
            uuid: 't1',
            title: 'Finalize Jummah announcement draft',
            status: 'in_progress',
            priority: 'high',
            due_date: '2026-11-14',
            project: { uuid: 'p1', name: 'Communications' },
            milestone: { title: 'Week 2 publish' },
          } as any,
          {
            uuid: 't2',
            title: 'Update volunteer shift roster',
            status: 'todo',
            priority: 'medium',
            due_date: '2026-11-16',
            project: { uuid: 'p2', name: 'Events Ops' },
          } as any,
          {
            uuid: 't3',
            title: 'Review PMS access invites',
            status: 'review',
            priority: 'urgent',
            due_date: '2026-11-13',
            project: { uuid: 'p3', name: 'IT Platform' },
          } as any,
        ],
    activity_stream: empty
      ? []
      : [
          {
            id: 1,
            action: 'task_completed',
            created_at: new Date().toISOString(),
            user: { name: 'Fatima A.' },
            project: { name: 'Events Ops' },
          } as any,
          {
            id: 2,
            action: 'comment_added',
            created_at: new Date().toISOString(),
            user: { name: 'Omar K.' },
            project: { name: 'IT Platform' },
          } as any,
        ],
    recent_projects: empty ? [] : [],
  } as DashboardData;

  dashboardAnalytics.value = {
    total_tasks: empty ? 0 : 48,
    completed_tasks: empty ? 0 : 19,
    in_progress_tasks: empty ? 0 : 14,
    completion_rate: empty ? 0 : 40,
    accessible_projects_count: empty ? 0 : 12,
    velocity_trend: empty
      ? Array.from({ length: 8 }, (_, i) => ({
          week_number: i + 1,
          week_label: `W${i + 1}`,
          tasks_created: 0,
          tasks_completed: 0,
        }))
      : [
          { week_number: 1, week_label: 'W1', tasks_created: 4, tasks_completed: 2 },
          { week_number: 2, week_label: 'W2', tasks_created: 6, tasks_completed: 5 },
          { week_number: 3, week_label: 'W3', tasks_created: 3, tasks_completed: 4 },
          { week_number: 4, week_label: 'W4', tasks_created: 7, tasks_completed: 3 },
          { week_number: 5, week_label: 'W5', tasks_created: 5, tasks_completed: 6 },
          { week_number: 6, week_label: 'W6', tasks_created: 8, tasks_completed: 5 },
          { week_number: 7, week_label: 'W7', tasks_created: 4, tasks_completed: 7 },
          { week_number: 8, week_label: 'W8', tasks_created: 6, tasks_completed: 4 },
        ],
  } as DashboardAnalytics;
};

const loadDashboard = async () => {
  isLoading.value = true;
  try {
    if (isLayoutPreview.value) {
      loadPreviewFixtures(route.query.empty === '1');
      return;
    }
    const [dash, analytics] = await Promise.all([
      projectsApi.getDashboard(),
      projectsApi.getDashboardAnalytics().catch(() => null),
    ]);
    dashboardData.value = dash;
    dashboardAnalytics.value = analytics;
  } catch (err) {
    console.error('Failed to load dashboard:', err);
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  loadDashboard();
});

const openTask = (task: Task) => {
  activeTaskUuid.value = task.uuid;
  activeProjectUuid.value = task.project?.uuid || '';
};

const onTaskUpdated = () => {
  loadDashboard();
};

const onProjectCreated = (uuid: string) => {
  router.push(`/projects/${uuid}`);
};
</script>

<template>
  <div class="space-y-0">
    <header class="pm-page-header mb-[var(--spacing-section)]">
      <div class="min-w-0">
        <h1 class="pm-page-title">
          Assalamu Alaikum, {{ authStore.user?.name }}
        </h1>
        <p class="pm-page-subtitle">
          Welcome to the official SFU MSA Project Management System. Here is your operational overview.
        </p>
      </div>

      <div class="pm-actions shrink-0">
        <router-link to="/calendar" class="pm-btn pm-btn-secondary">
          <Calendar class="w-4 h-4 text-[#640c0e]" />
          <span>Calendar</span>
        </router-link>
        <button type="button" class="pm-btn pm-btn-primary" @click="showCreateModal = true">
          <Plus class="w-4 h-4" />
          <span>New Project</span>
        </button>
      </div>
    </header>

    <div v-if="isLoading" class="py-20 flex items-center justify-center text-[#6b7280]">
      <Loader2 class="w-7 h-7 animate-spin text-[#640c0e]" />
    </div>

    <div v-else-if="dashboardData" class="flex flex-col gap-[var(--spacing-section)]">
      <!-- KPI cards -->
      <section class="pm-kpi-grid" aria-label="Key metrics">
        <div class="pm-card pm-kpi-card">
          <div class="flex items-center justify-between gap-2">
            <span class="pm-kpi-label">Projects</span>
            <FolderKanban class="w-4 h-4 text-[#640c0e] shrink-0" />
          </div>
          <div class="pm-kpi-value">{{ dashboardData.kpi.total_projects }}</div>
          <p class="pm-kpi-meta">Accessible initiatives</p>
        </div>

        <div class="pm-card pm-kpi-card">
          <div class="flex items-center justify-between gap-2">
            <span class="pm-kpi-label">Active</span>
            <Clock class="w-4 h-4 text-blue-600 shrink-0" />
          </div>
          <div class="pm-kpi-value text-blue-700">{{ dashboardData.kpi.active_projects }}</div>
          <p class="pm-kpi-meta">In progress & planning</p>
        </div>

        <div class="pm-card pm-kpi-card">
          <div class="flex items-center justify-between gap-2">
            <span class="pm-kpi-label">Completed</span>
            <CheckSquare class="w-4 h-4 text-emerald-600 shrink-0" />
          </div>
          <div class="pm-kpi-value text-emerald-700">{{ dashboardData.kpi.completed_projects }}</div>
          <p class="pm-kpi-meta">Delivered deliverables</p>
        </div>

        <div class="pm-card pm-kpi-card">
          <div class="flex items-center justify-between gap-2">
            <span class="pm-kpi-label">My Tasks</span>
            <CheckSquare class="w-4 h-4 text-[#640c0e] shrink-0" />
          </div>
          <div class="pm-kpi-value text-[#640c0e]">{{ dashboardData.kpi.my_open_tasks }}</div>
          <p class="pm-kpi-meta">Assigned deliverables</p>
        </div>

        <div class="pm-card pm-kpi-card">
          <div class="flex items-center justify-between gap-2">
            <span class="pm-kpi-label">Overdue</span>
            <AlertTriangle class="w-4 h-4 text-[#ea2128] shrink-0" />
          </div>
          <div
            class="pm-kpi-value"
            :class="dashboardData.kpi.my_overdue_tasks > 0 ? 'text-[#ea2128]' : 'text-emerald-700'"
          >
            {{ dashboardData.kpi.my_overdue_tasks }}
          </div>
          <p class="pm-kpi-meta">Past target deadline</p>
        </div>
      </section>

      <!-- Charts -->
      <section v-if="dashboardAnalytics" class="pm-charts-grid" aria-label="Analytics">
        <div class="pm-panel pm-chart-panel">
          <div class="flex items-start justify-between gap-3">
            <h2 class="pm-section-title flex items-center gap-2 text-[15px] sm:text-base">
              <BarChart3 class="w-4 h-4 text-[#640c0e] shrink-0" />
              <span>Task Breakdown</span>
            </h2>
            <span class="text-[11px] font-semibold text-[#6b7280] shrink-0 pt-0.5">
              {{ dashboardAnalytics.completion_rate }}% done
            </span>
          </div>
          <div class="pm-chart-body">
            <StatusDonutChart
              :counts="{
                total: dashboardAnalytics.total_tasks,
                completed: dashboardAnalytics.completed_tasks,
                in_progress: dashboardAnalytics.in_progress_tasks,
                review: 0,
                todo: Math.max(0, dashboardAnalytics.total_tasks - dashboardAnalytics.completed_tasks - dashboardAnalytics.in_progress_tasks),
                cancelled: 0,
              }"
              :size="donutSize"
            />
          </div>
        </div>

        <div class="pm-panel pm-chart-panel">
          <div class="flex items-start justify-between gap-3">
            <h2 class="pm-section-title flex items-center gap-2 text-[15px] sm:text-base">
              <TrendingUp class="w-4 h-4 text-[#640c0e] shrink-0" />
              <span>Velocity (8 Weeks)</span>
            </h2>
            <span class="text-[11px] font-semibold text-[#6b7280] shrink-0 pt-0.5">
              {{ dashboardAnalytics.accessible_projects_count }} projects
            </span>
          </div>
          <div class="pm-chart-body">
            <VelocityBarChart :weeks="dashboardAnalytics.velocity_trend" />
          </div>
        </div>
      </section>

      <!-- Tasks + Activity -->
      <section class="pm-split-2">
        <div class="flex flex-col gap-3 min-h-0">
          <div class="flex items-center justify-between gap-3">
            <h2 class="pm-section-title flex items-center gap-2">
              <CheckSquare class="w-4 h-4 text-[#640c0e]" />
              <span>My Upcoming Tasks</span>
            </h2>
            <router-link
              to="/my-tasks"
              class="text-xs font-semibold text-[#640c0e] hover:underline flex items-center gap-1 shrink-0"
            >
              <span>View all</span>
              <ArrowRight class="w-3.5 h-3.5" />
            </router-link>
          </div>

          <div class="pm-panel flex-1">
            <button
              v-for="task in dashboardData.my_tasks"
              :key="task.uuid"
              type="button"
              class="w-full text-left px-4 py-3.5 flex items-center justify-between gap-3 border-b border-[#ebe8de] last:border-0 hover:bg-[#fffbf4] transition-colors"
              @click="openTask(task)"
            >
              <div class="flex items-start gap-3 min-w-0">
                <span
                  class="w-2 h-2 rounded-full mt-1.5 shrink-0"
                  :class="[
                    task.priority === 'urgent' ? 'bg-rose-500' :
                    task.priority === 'high' ? 'bg-amber-500' :
                    task.priority === 'medium' ? 'bg-blue-500' : 'bg-emerald-500'
                  ]"
                />
                <div class="min-w-0">
                  <h3 class="text-[13px] font-bold text-[#1e1e1e] truncate">{{ task.title }}</h3>
                  <p class="text-[11px] text-[#6b7280] mt-0.5 truncate">
                    <span v-if="task.project" class="font-medium text-[#640c0e]">{{ task.project.name }}</span>
                    <span v-if="task.milestone"> · {{ task.milestone.title }}</span>
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span v-if="task.due_date" class="text-[11px] font-medium text-[#6b7280] hidden sm:inline">
                  {{ task.due_date }}
                </span>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-[#fffbf4] text-[#6b7280] border border-[#ebe8de]">
                  {{ task.status.replace('_', ' ') }}
                </span>
              </div>
            </button>

            <div v-if="dashboardData.my_tasks.length === 0" class="pm-empty">
              <p class="pm-empty-title">No upcoming tasks</p>
              <p>You have no pending tasks assigned right now.</p>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-3 min-h-0">
          <div class="flex items-center justify-between gap-3">
            <h2 class="pm-section-title flex items-center gap-2">
              <Activity class="w-4 h-4 text-[#640c0e]" />
              <span>Recent Activity</span>
            </h2>
          </div>

          <div class="pm-panel flex-1 max-h-[22rem] overflow-y-auto">
            <div
              v-for="act in dashboardData.activity_stream"
              :key="act.id"
              class="px-4 py-3 border-b border-[#ebe8de] last:border-0"
            >
              <div class="flex items-center justify-between gap-2 text-[11px] text-[#6b7280]">
                <span class="font-bold text-[#1e1e1e] truncate">{{ act.user?.name || 'MSA Team' }}</span>
                <span class="shrink-0">{{ new Date(act.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
              </div>
              <p class="text-[12px] text-[#6b7280] mt-1 leading-snug">
                <span class="text-[#640c0e] font-semibold">{{ act.action.replace(/_/g, ' ') }}</span>
                <span v-if="act.project"> on {{ act.project.name }}</span>
              </p>
            </div>

            <div v-if="dashboardData.activity_stream.length === 0" class="pm-empty">
              <p class="pm-empty-title">No recent activity</p>
              <p>Project activity will appear here as work progresses.</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Recent projects -->
      <section class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-3">
          <h2 class="pm-section-title flex items-center gap-2">
            <FolderKanban class="w-4 h-4 text-[#640c0e]" />
            <span>Recent Projects</span>
          </h2>
          <router-link
            to="/projects"
            class="text-xs font-semibold text-[#640c0e] hover:underline flex items-center gap-1 shrink-0"
          >
            <span>Browse all</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </router-link>
        </div>

        <div
          v-if="dashboardData.recent_projects.length"
          class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-[var(--spacing-grid)]"
        >
          <ProjectCard
            v-for="proj in dashboardData.recent_projects"
            :key="proj.uuid"
            :project="proj"
          />
        </div>
        <div v-else class="pm-panel pm-empty">
          <p class="pm-empty-title">No projects yet</p>
          <p>Create a project to start coordinating MSA work.</p>
        </div>
      </section>
    </div>

    <ProjectCreateModal
      :is-open="showCreateModal"
      @close="showCreateModal = false"
      @created="onProjectCreated"
    />

    <TaskDetailModal
      v-if="activeTaskUuid && activeProjectUuid"
      :is-open="!!activeTaskUuid"
      :task-uuid="activeTaskUuid"
      :project-uuid="activeProjectUuid"
      @close="activeTaskUuid = null"
      @task-updated="onTaskUpdated"
      @task-deleted="onTaskUpdated"
    />
  </div>
</template>
