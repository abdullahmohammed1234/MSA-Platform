<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useProjectsStore } from '../stores/projects';
import { useAuthStore } from '../stores/auth';
import ProjectCard from '../components/projects/ProjectCard.vue';
import ProjectCreateModal from '../components/projects/ProjectCreateModal.vue';
import {
  FolderKanban,
  Search,
  Plus,
  Loader2,
} from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();
const projectsStore = useProjectsStore();

const showCreateModal = ref(false);

onMounted(() => {
  projectsStore.fetchProjects(1);
});

const onFilterChange = () => {
  projectsStore.fetchProjects(1);
};

const clearFilters = () => {
  projectsStore.resetFilters();
};

const onProjectCreated = (uuid: string) => {
  router.push(`/projects/${uuid}`);
};
</script>

<template>
  <div class="flex flex-col gap-[var(--spacing-section)]">
    <header class="pm-page-header">
      <div class="min-w-0">
        <h1 class="pm-page-title">Project Initiatives</h1>
        <p class="pm-page-subtitle">
          Explore and manage department-private and shared MSA projects.
        </p>
      </div>

      <button
        type="button"
        class="pm-btn pm-btn-primary"
        @click="showCreateModal = true"
      >
        <Plus class="w-4 h-4" />
        <span>New Project</span>
      </button>
    </header>

    <!-- Filters & Search Toolbar -->
    <div class="pm-panel p-4 space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Search Input -->
        <div class="relative">
          <Search class="w-4 h-4 text-[#6b7280] absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            v-model="projectsStore.filters.search"
            @input="onFilterChange"
            type="text"
            placeholder="Search projects..."
            class="w-full pl-9 pr-3.5 py-2 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
          />
        </div>

        <!-- Visibility Filter -->
        <div>
          <select
            v-model="projectsStore.filters.visibility"
            @change="onFilterChange"
            class="w-full px-3 py-2 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
          >
            <option value="">All Visibility Levels</option>
            <option value="department_private">🔒 Department Private Only</option>
            <option value="shared_msa">🌐 Shared MSA Only</option>
          </select>
        </div>

        <!-- Status Filter -->
        <div>
          <select
            v-model="projectsStore.filters.status"
            @change="onFilterChange"
            class="w-full px-3 py-2 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
          >
            <option value="">All Project Statuses</option>
            <option value="planning">Planning</option>
            <option value="in_progress">In Progress</option>
            <option value="on_hold">On Hold</option>
            <option value="completed">Completed</option>
          </select>
        </div>

        <!-- Priority Filter -->
        <div>
          <select
            v-model="projectsStore.filters.priority"
            @change="onFilterChange"
            class="w-full px-3 py-2 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
          >
            <option value="">All Priorities</option>
            <option value="urgent">Urgent</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
          </select>
        </div>
      </div>

      <!-- Department Filter Pills -->
      <div class="flex items-center gap-1.5 flex-wrap pt-2 border-t border-[#ebe8de]">
        <button
          type="button"
          @click="projectsStore.filters.department_id = null; onFilterChange()"
          class="px-3 py-1 rounded-full text-xs font-semibold transition-all"
          :class="[
            projectsStore.filters.department_id === null
              ? 'bg-[#640c0e] text-white shadow-xs'
              : 'bg-[#fbfbf9] text-[#6b7280] hover:bg-[#ebe8de] border border-[#ebe8de]',
          ]"
        >
          All Departments
        </button>

        <button
          v-for="dept in authStore.departments"
          :key="dept.id"
          type="button"
          @click="projectsStore.filters.department_id = dept.id; onFilterChange()"
          class="px-3 py-1 rounded-full text-xs font-semibold transition-all"
          :class="[
            projectsStore.filters.department_id === dept.id
              ? 'bg-[#640c0e] text-white shadow-xs'
              : 'bg-[#fbfbf9] text-[#6b7280] hover:bg-[#ebe8de] border border-[#ebe8de]',
          ]"
        >
          {{ dept.name }}
        </button>
      </div>
    </div>

    <!-- Projects Grid -->
    <div v-if="projectsStore.isLoading" class="py-24 flex items-center justify-center text-[#6b7280]">
      <Loader2 class="w-8 h-8 animate-spin text-[#640c0e]" />
    </div>

    <div v-else-if="projectsStore.projects.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <ProjectCard
        v-for="project in projectsStore.projects"
        :key="project.uuid"
        :project="project"
      />
    </div>

    <div
      v-else
      class="py-20 text-center bg-white rounded-2xl border border-dashed border-[#ded9cb] p-8 max-w-lg mx-auto space-y-3"
    >
      <div class="w-12 h-12 rounded-2xl bg-[#640c0e]/10 text-[#640c0e] flex items-center justify-center mx-auto">
        <FolderKanban class="w-6 h-6" />
      </div>
      <h3 class="text-base font-bold text-[#1e1e1e] font-display">No projects found</h3>
      <p class="text-xs text-[#6b7280] leading-relaxed">
        No projects match your current filters, or no projects have been initiated for this department yet.
      </p>
      <button
        type="button"
        @click="clearFilters"
        class="text-xs font-semibold text-[#640c0e] hover:underline"
      >
        Clear filters
      </button>
    </div>

    <!-- Modal -->
    <ProjectCreateModal
      :is-open="showCreateModal"
      @close="showCreateModal = false"
      @created="onProjectCreated"
    />
  </div>
</template>
