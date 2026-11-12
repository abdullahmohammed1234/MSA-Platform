<script setup lang="ts">
import { computed } from 'vue';
import type { Task, TaskStatus } from '../../types/pm';
import { Calendar, MessageSquare, Paperclip, Link2, Check } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    task: Task;
    selectable?: boolean;
    selected?: boolean;
  }>(),
  {
    selectable: false,
    selected: false,
  }
);

const emit = defineEmits<{
  (e: 'click'): void;
  (e: 'status-change', newStatus: TaskStatus): void;
  (e: 'toggle-select', task: Task): void;
}>();

const priorityColor = computed(() => {
  switch (props.task.priority) {
    case 'urgent': return 'bg-rose-500';
    case 'high': return 'bg-amber-500';
    case 'medium': return 'bg-blue-500';
    default: return 'bg-emerald-500';
  }
});

const assigneesList = computed(() => {
  if (props.task.assignees && props.task.assignees.length > 0) {
    return props.task.assignees;
  }
  if (props.task.assignee) {
    return [props.task.assignee];
  }
  return [];
});

const visibleAssignees = computed(() => assigneesList.value.slice(0, 3));
const extraAssigneesCount = computed(() => Math.max(0, assigneesList.value.length - 3));

const onCardClick = () => {
  if (props.selectable) {
    emit('toggle-select', props.task);
  } else {
    emit('click');
  }
};

const onStatusSelect = (e: Event) => {
  const value = (e.target as HTMLSelectElement).value as TaskStatus;
  if (value && value !== props.task.status) {
    emit('status-change', value);
  }
};
</script>

<template>
  <div
    @click="onCardClick"
    class="bg-white rounded-xl p-3.5 border transition-all cursor-pointer group flex flex-col gap-2.5 relative"
    :class="[
      selected ? 'border-[#640c0e] ring-2 ring-[#640c0e]/20 bg-[#640c0e]/5 shadow-sm' : 'border-[#ebe8de] shadow-xs hover:shadow-soft hover:border-[#640c0e]/30'
    ]"
  >
    <!-- Priority bar, Select Checkbox, & Milestone -->
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-1.5">
        <!-- Selection Checkbox in Select Mode -->
        <button
          v-if="selectable"
          type="button"
          @click.stop="$emit('toggle-select', task)"
          class="w-4 h-4 rounded border flex items-center justify-center transition-colors"
          :class="[selected ? 'bg-[#640c0e] border-[#640c0e] text-white' : 'border-[#d0ccc0] bg-white hover:border-[#640c0e]']"
        >
          <Check v-if="selected" class="w-3 h-3 stroke-[3]" />
        </button>

        <span class="w-2 h-2 rounded-full" :class="priorityColor" :title="`Priority: ${task.priority}`"></span>
        <span class="text-[10px] font-bold uppercase tracking-wider text-[#6b7280]">
          {{ task.priority }}
        </span>
      </div>

      <div class="flex items-center gap-1">
        <span
          v-if="task.task_list"
          class="text-[9px] font-semibold px-2 py-0.5 rounded-full bg-[#f4f2ea] text-[#4b5563] border border-[#e5e2d6] truncate max-w-[100px]"
          :title="`List: ${task.task_list.name}`"
        >
          📂 {{ task.task_list.name }}
        </span>

        <span
          v-if="task.milestone"
          class="text-[9px] font-medium px-2 py-0.5 rounded-full bg-[#fbfbf9] text-[#6b7280] border border-[#ebe8de] truncate max-w-[110px]"
          :title="`Milestone: ${task.milestone.title}`"
        >
          🎯 {{ task.milestone.title }}
        </span>
      </div>
    </div>

    <!-- Title -->
    <h4 class="text-xs font-bold text-[#1e1e1e] group-hover:text-[#640c0e] transition-colors line-clamp-2 leading-snug">
      {{ task.title }}
    </h4>

    <!-- Accessible status control (keyboard / touch alternative to drag-and-drop) -->
    <label class="sr-only" :for="`task-status-${task.uuid}`">Move {{ task.title }}</label>
    <select
      :id="`task-status-${task.uuid}`"
      class="w-full text-[10px] font-semibold rounded-lg border border-[#ebe8de] bg-[#fffbf4] text-[#1e1e1e] px-2 py-1.5 focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
      :value="task.status"
      @click.stop
      @change.stop="onStatusSelect"
    >
      <option value="todo">To Do</option>
      <option value="in_progress">In Progress</option>
      <option value="review">In Review</option>
      <option value="completed">Completed</option>
      <option value="cancelled">Cancelled</option>
    </select>

    <!-- Metadata footer: Due Date, Dependencies, Attachments, Comments, Assignees -->
    <div class="flex items-center justify-between pt-2 border-t border-[#f0eee6] text-[11px] text-[#6b7280]">
      <div class="flex items-center gap-2">
        <span
          v-if="task.due_date"
          class="flex items-center gap-1"
          :class="[task.status === 'completed' ? 'text-[#6b7280]' : 'text-[#1e1e1e] font-medium']"
          :title="task.start_date ? `Timeline: ${task.start_date} to ${task.due_date}` : `Due: ${task.due_date}`"
        >
          <Calendar class="w-3 h-3 text-[#6b7280]" />
          <span>{{ task.due_date }}</span>
        </span>

        <span
          v-if="(task.dependencies_count ?? 0) > 0"
          class="flex items-center gap-0.5 text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded text-[10px] font-medium"
          :title="`Depends on ${task.dependencies_count} tasks`"
        >
          <Link2 class="w-2.5 h-2.5" />
          <span>{{ task.dependencies_count }}</span>
        </span>

        <span
          v-if="(task.attachments_count ?? 0) > 0"
          class="flex items-center gap-0.5 text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded text-[10px] font-medium"
          :title="`${task.attachments_count} attachments`"
        >
          <Paperclip class="w-2.5 h-2.5" />
          <span>{{ task.attachments_count }}</span>
        </span>

        <span v-if="(task.comments_count ?? 0) > 0" class="flex items-center gap-1">
          <MessageSquare class="w-3 h-3 text-[#6b7280]" />
          <span>{{ task.comments_count }}</span>
        </span>
      </div>

      <!-- Multiple Assignee Avatars Stack -->
      <div v-if="assigneesList.length > 0" class="flex items-center -space-x-1.5 overflow-hidden">
        <div
          v-for="u in visibleAssignees"
          :key="u.id"
          class="w-5 h-5 rounded-full bg-gradient-to-br from-[#640c0e] to-[#b02e32] text-white flex items-center justify-center text-[9px] font-bold ring-1 ring-white"
          :title="`Assigned to: ${u.name}`"
        >
          {{ u.name.slice(0, 1) }}
        </div>
        <div
          v-if="extraAssigneesCount > 0"
          class="w-5 h-5 rounded-full bg-[#ebe8de] text-[#4b5563] flex items-center justify-center text-[8px] font-bold ring-1 ring-white"
          :title="`+${extraAssigneesCount} more assignees`"
        >
          +{{ extraAssigneesCount }}
        </div>
      </div>
      <div v-else class="text-[10px] text-[#9ca3af] italic">
        Unassigned
      </div>
    </div>
  </div>
</template>
