<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
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
const authStore = useAuthStore();

const dashboardData = ref<DashboardData | null>(null);
const dashboardAnalytics = ref<DashboardAnalytics | null>(null);
const isLoading = ref(true);
const showCreateModal = ref(false);
const activeTaskUuid = ref<string | null>(null);
const activeProjectUuid = ref<string | null>(null);

const loadDashboard = async () => {
  isLoading.value = true;
  try {
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
  <div class="space-y-8 animate-in fade-in duration-200">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-[#640c0e] font-display tracking-tight">
          Assalamu Alaikum, {{ authStore.user?.name }}
        </h1>
        <p class="text-xs text-[#6b7280] mt-1">
          Welcome to the official SFU MSA Project Management System. Here is your operational overview.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <router-link
          to="/calendar"
          class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white border border-[#ebe8de] text-[#1e1e1e] hover:border-[#640c0e]/30 shadow-xs transition-colors"
        >
          <Calendar class="w-4 h-4 text-[#640c0e]" />
          <span>Calendar</span>
        </router-link>

        <button
          type="button"
          @click="showCreateModal = true"
          class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] shadow-xs hover:shadow-brand transition-all"
        >
          <Plus class="w-4 h-4" />
          <span>New Project</span>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-24 flex items-center justify-center text-[#6b7280]">
      <Loader2 class="w-8 h-8 animate-spin text-[#640c0e]" />
    </div>

    <div v-else-if="dashboardData" class="space-y-8">
      <!-- 5 KPI Summary Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5 sm:gap-4">
        <!-- Total Projects -->
        <div class="pm-card p-4 flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#6b7280] mb-2">
            <span class="text-xs font-bold uppercase tracking-wider">Projects</span>
            <FolderKanban class="w-4 h-4 text-[#640c0e]" />
          </div>
          <div class="text-2xl font-extrabold text-[#1e1e1e] font-display">
            {{ dashboardData.kpi.total_projects }}
          </div>
          <p class="text-[11px] text-[#6b7280] mt-1">Accessible initiatives</p>
        </div>

        <!-- Active Projects -->
        <div class="pm-card p-4 flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#6b7280] mb-2">
            <span class="text-xs font-bold uppercase tracking-wider">Active</span>
            <Clock class="w-4 h-4 text-blue-600" />
          </div>
          <div class="text-2xl font-extrabold text-blue-700 font-display">
            {{ dashboardData.kpi.active_projects }}
          </div>
          <p class="text-[11px] text-[#6b7280] mt-1">In progress & planning</p>
        </div>

        <!-- Completed Projects -->
        <div class="pm-card p-4 flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#6b7280] mb-2">
            <span class="text-xs font-bold uppercase tracking-wider">Completed</span>
            <CheckSquare class="w-4 h-4 text-emerald-600" />
          </div>
          <div class="text-2xl font-extrabold text-emerald-700 font-display">
            {{ dashboardData.kpi.completed_projects }}
          </div>
          <p class="text-[11px] text-[#6b7280] mt-1">Delivered deliverables</p>
        </div>

        <!-- My Open Tasks -->
        <div class="pm-card p-4 flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#6b7280] mb-2">
            <span class="text-xs font-bold uppercase tracking-wider">My Tasks</span>
            <CheckSquare class="w-4 h-4 text-[#640c0e]" />
          </div>
          <div class="text-2xl font-extrabold text-[#640c0e] font-display">
            {{ dashboardData.kpi.my_open_tasks }}
          </div>
          <p class="text-[11px] text-[#6b7280] mt-1">Assigned deliverables</p>
        </div>

        <!-- Overdue Tasks -->
        <div class="pm-card p-4 flex flex-col justify-between col-span-2 lg:col-span-1">
          <div class="flex items-center justify-between text-[#6b7280] mb-2">
            <span class="text-xs font-bold uppercase tracking-wider">Overdue</span>
            <AlertTriangle class="w-4 h-4 text-[#ea2128]" />
          </div>
          <div
            class="text-2xl font-extrabold font-display"
            :class="dashboardData.kpi.my_overdue_tasks > 0 ? 'text-[#ea2128]' : 'text-emerald-700'"
          >
            {{ dashboardData.kpi.my_overdue_tasks }}
          </div>
          <p class="text-[11px] text-[#6b7280] mt-1">Past target deadline</p>
        </div>
      </div>

      <!-- Visual Analytics Overview (Phase 14) -->
      <div v-if="dashboardAnalytics" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Status Distribution Donut Card -->
        <div class="bg-white p-5 rounded-2xl border border-[#ebe8de] shadow-xs space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-[#1e1e1e] flex items-center gap-2">
              <BarChart3 class="w-4 h-4 text-[#640c0e]" />
              <span>Overall Task Breakdown</span>
            </h3>
            <span class="text-xs font-semibold text-[#6b7280]">
              {{ dashboardAnalytics.completion_rate }}% completed
            </span>
          </div>
          <StatusDonutChart
            :counts="{
              total: dashboardAnalytics.total_tasks,
              completed: dashboardAnalytics.completed_tasks,
              in_progress: dashboardAnalytics.in_progress_tasks,
              review: 0,
              todo: Math.max(0, dashboardAnalytics.total_tasks - dashboardAnalytics.completed_tasks - dashboardAnalytics.in_progress_tasks),
              cancelled: 0,
            }"
            :size="150"
          />
        </div>

        <!-- Weekly Velocity Bar Chart -->
        <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-[#ebe8de] shadow-xs space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-[#1e1e1e] flex items-center gap-2">
              <TrendingUp class="w-4 h-4 text-[#640c0e]" />
              <span>Cross-Project Velocity (8 Weeks)</span>
            </h3>
            <span class="text-xs font-semibold text-[#6b7280]">
              {{ dashboardAnalytics.accessible_projects_count }} Accessible Projects
            </span>
          </div>
          <VelocityBarChart :weeks="dashboardAnalytics.velocity_trend" />
        </div>
      </div>

      <!-- Main Split: My Tasks & Recent Projects -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- My Tasks (Col 1-2) -->
        <div class="lg:col-span-2 space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <CheckSquare class="w-4 h-4 text-[#640c0e]" />
              <h2 class="text-base font-bold text-[#1e1e1e] font-display">My Upcoming Tasks</h2>
            </div>
            <router-link
              to="/my-tasks"
              class="text-xs font-semibold text-[#640c0e] hover:underline flex items-center gap-1"
            >
              <span>View all</span>
              <ArrowRight class="w-3.5 h-3.5" />
            </router-link>
          </div>

          <div class="bg-white rounded-2xl border border-[#ebe8de] shadow-xs divide-y divide-[#ebe8de]">
            <div
              v-for="task in dashboardData.my_tasks"
              :key="task.uuid"
              @click="openTask(task)"
              class="p-4 flex items-center justify-between hover:bg-[#fbfbf9] transition-colors cursor-pointer group"
            >
              <div class="flex items-start gap-3">
                <div class="mt-0.5">
                  <span
                    class="w-2.5 h-2.5 rounded-full block"
                    :class="[
                      task.priority === 'urgent' ? 'bg-rose-500' :
                      task.priority === 'high' ? 'bg-amber-500' :
                      task.priority === 'medium' ? 'bg-blue-500' : 'bg-emerald-500'
                    ]"
                  ></span>
                </div>
                <div>
                  <h4 class="text-xs font-bold text-[#1e1e1e] group-hover:text-[#640c0e] transition-colors">
                    {{ task.title }}
                  </h4>
                  <div class="flex items-center gap-2 mt-1 text-[11px] text-[#6b7280]">
                    <span v-if="task.project" class="font-medium text-[#640c0e]">
                      {{ task.project.name }}
                    </span>
                    <span v-if="task.milestone">
                      &bull; 🎯 {{ task.milestone.title }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-3">
                <span
                  v-if="task.due_date"
                  class="text-[11px] font-medium text-[#6b7280] hidden sm:block"
                >
                  Due {{ task.due_date }}
                </span>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-[#fbfbf9] text-[#6b7280] border border-[#ebe8de]">
                  {{ task.status.replace('_', ' ') }}
                </span>
              </div>
            </div>

            <div
              v-if="dashboardData.my_tasks.length === 0"
              class="py-12 text-center text-xs text-[#6b7280]"
            >
              You have no pending tasks assigned at this moment.
            </div>
          </div>
        </div>

        <!-- Activity Stream (Col 3) -->
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Activity class="w-4 h-4 text-[#640c0e]" />
              <h2 class="text-base font-bold text-[#1e1e1e] font-display">Recent Activity</h2>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-[#ebe8de] shadow-xs p-4 space-y-3 max-h-[380px] overflow-y-auto">
            <div
              v-for="act in dashboardData.activity_stream"
              :key="act.id"
              class="text-xs space-y-0.5 pb-2 border-b border-[#f0eee6] last:border-0 last:pb-0"
            >
              <div class="flex items-center justify-between text-[11px] text-[#6b7280]">
                <span class="font-bold text-[#1e1e1e]">{{ act.user?.name || 'MSA Team' }}</span>
                <span>{{ new Date(act.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
              </div>
              <p class="text-[#6b7280] text-[11px]">
                <span class="text-[#640c0e] font-semibold">{{ act.action.replace(/_/g, ' ') }}</span>
                <span v-if="act.project"> on {{ act.project.name }}</span>
              </p>
            </div>

            <div
              v-if="dashboardData.activity_stream.length === 0"
              class="py-12 text-center text-xs text-[#6b7280]"
            >
              No recent project activity logged yet.
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Projects Section -->
      <div class="space-y-4 pt-2">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <FolderKanban class="w-4 h-4 text-[#640c0e]" />
            <h2 class="text-base font-bold text-[#1e1e1e] font-display">Recent Projects</h2>
          </div>
          <router-link
            to="/projects"
            class="text-xs font-semibold text-[#640c0e] hover:underline flex items-center gap-1"
          >
            <span>Browse all projects</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </router-link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <ProjectCard
            v-for="proj in dashboardData.recent_projects"
            :key="proj.uuid"
            :project="proj"
          />
        </div>
      </div>
    </div>

    <!-- Modals -->
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
