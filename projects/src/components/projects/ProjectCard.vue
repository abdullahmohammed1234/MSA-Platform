<script setup lang="ts">
import { computed } from 'vue';
import type { Project } from '../../types/pm';
import { Lock, Globe, Calendar, CheckSquare, Flag, ArrowRight } from 'lucide-vue-next';

const props = defineProps<{
  project: Project;
}>();

const statusBadgeClass = computed(() => {
  switch (props.project.status) {
    case 'completed':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    case 'in_progress':
      return 'bg-blue-50 text-blue-700 border-blue-200';
    case 'on_hold':
      return 'bg-amber-50 text-amber-700 border-amber-200';
    case 'planning':
      return 'bg-purple-50 text-purple-700 border-purple-200';
    default:
      return 'bg-gray-50 text-gray-700 border-gray-200';
  }
});
</script>

<template>
  <router-link
    :to="`/projects/${project.uuid}`"
    class="pm-card p-5 flex flex-col justify-between group block relative overflow-hidden"
  >
    <div>
      <!-- Top Badges: Department, Visibility & Status -->
      <div class="flex items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-1.5 flex-wrap">
          <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-[#640c0e]/10 text-[#640c0e]">
            {{ project.department?.name || project.owning_department?.name || 'MSA' }}
          </span>
          <span
            v-if="project.participating_departments && project.participating_departments.length > 0"
            class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 border border-gray-200"
            :title="project.participating_departments.map(d => d.name).join(', ')"
          >
            +{{ project.participating_departments.length }} collaborating
          </span>
        </div>

        <div class="flex items-center gap-1.5">
          <!-- Visibility Badge -->
          <span
            v-if="project.visibility === 'department_private'"
            class="text-[10px] font-semibold flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#fbfbf9] text-[#6b7280] border border-[#ebe8de]"
            title="Department Private"
          >
            <Lock class="w-3 h-3 text-[#640c0e]" /> Private
          </span>
          <span
            v-else
            class="text-[10px] font-semibold flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#fbfbf9] text-[#6b7280] border border-[#ebe8de]"
            title="Shared MSA"
          >
            <Globe class="w-3 h-3 text-blue-600" /> Shared
          </span>

          <!-- Status Badge -->
          <span
            class="text-[10px] font-bold capitalize px-2 py-0.5 rounded-full border"
            :class="statusBadgeClass"
          >
            {{ project.status.replace('_', ' ') }}
          </span>
        </div>
      </div>

      <!-- Project Title & Description -->
      <h3 class="text-base font-bold text-[#1e1e1e] group-hover:text-[#640c0e] transition-colors mb-1.5 font-display line-clamp-1">
        {{ project.name }}
      </h3>
      <p class="text-xs text-[#6b7280] line-clamp-2 leading-relaxed mb-4 min-h-[2.5rem]">
        {{ project.description || 'No detailed scope provided.' }}
      </p>
    </div>

    <!-- Bottom Metadata: Due Date, Task Counts & Owner -->
    <div class="pt-3 border-t border-[#ebe8de] flex items-center justify-between text-xs text-[#6b7280]">
      <div class="flex items-center gap-3">
        <span class="flex items-center gap-1" title="Tasks count">
          <CheckSquare class="w-3.5 h-3.5 text-[#6b7280]" />
          <span>{{ project.tasks_count ?? 0 }}</span>
        </span>
        <span class="flex items-center gap-1" title="Milestones count">
          <Flag class="w-3.5 h-3.5 text-[#6b7280]" />
          <span>{{ project.milestones_count ?? 0 }}</span>
        </span>
        <span
          v-if="project.due_date"
          class="flex items-center gap-1"
          title="Target Due Date"
        >
          <Calendar class="w-3.5 h-3.5 text-[#6b7280]" />
          <span>{{ project.due_date }}</span>
        </span>
      </div>

      <div class="flex items-center gap-1 text-[#640c0e] font-semibold group-hover:translate-x-1 transition-transform">
        <span>Workspace</span>
        <ArrowRight class="w-3.5 h-3.5" />
      </div>
    </div>
  </router-link>
</template>
