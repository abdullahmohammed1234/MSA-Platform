<script setup lang="ts">
import { computed } from 'vue';
import type { GanttDependency, GanttTask } from '../../types/pm';

interface Props {
  dependencies: GanttDependency[];
  tasks: GanttTask[];
  timelineStart: string; // YYYY-MM-DD
  dayPitch: number;      // px per day
  rowHeight?: number;    // px, default 40
  totalWidth: number;
  totalHeight: number;
}

const props = withDefaults(defineProps<Props>(), {
  rowHeight: 40,
});

function diffDays(a: string, b: string): number {
  const dateA = new Date(a + 'T00:00:00');
  const dateB = new Date(b + 'T00:00:00');
  return Math.round((dateA.getTime() - dateB.getTime()) / (1000 * 60 * 60 * 24));
}

interface RenderedPath {
  id: number;
  d: string;
  isConflict: boolean;
  conflictReason?: string | null;
  dependencyType: string;
}

const paths = computed<RenderedPath[]>(() => {
  const taskMap = new Map<number, { task: GanttTask; rowIndex: number }>();
  props.tasks.forEach((t, idx) => {
    taskMap.set(t.id, { task: t, rowIndex: idx });
  });

  const result: RenderedPath[] = [];

  props.dependencies.forEach((dep) => {
    const predInfo = taskMap.get(dep.depends_on_task_id);
    const succInfo = taskMap.get(dep.task_id);

    if (!predInfo || !succInfo) return;

    const pTask = predInfo.task;
    const sTask = succInfo.task;

    const pStart = pTask.start_date || pTask.due_date || props.timelineStart;
    const pDue = pTask.due_date || pTask.start_date || props.timelineStart;
    const sStart = sTask.start_date || sTask.due_date || props.timelineStart;
    const sDue = sTask.due_date || sTask.start_date || props.timelineStart;

    const pX = Math.max(0, diffDays(pStart, props.timelineStart) * props.dayPitch);
    const pW = Math.max(props.dayPitch, (Math.max(1, diffDays(pDue, pStart) + 1)) * props.dayPitch);
    const pY = predInfo.rowIndex * props.rowHeight + props.rowHeight / 2;

    const sX = Math.max(0, diffDays(sStart, props.timelineStart) * props.dayPitch);
    const sW = Math.max(props.dayPitch, (Math.max(1, diffDays(sDue, sStart) + 1)) * props.dayPitch);
    const sY = succInfo.rowIndex * props.rowHeight + props.rowHeight / 2;

    let pathD = '';

    if (dep.dependency_type === 'finish_to_start') {
      const startX = pX + pW;
      const startY = pY;
      const endX = sX;
      const endY = sY;

      if (endX >= startX + 16) {
        const midX = startX + (endX - startX) / 2;
        pathD = `M ${startX} ${startY} H ${midX} V ${endY} H ${endX}`;
      } else {
        const detourX = startX + 12;
        const midY = startY + (endY - startY) / 2;
        pathD = `M ${startX} ${startY} H ${detourX} V ${midY} H ${Math.max(0, endX - 12)} V ${endY} H ${endX}`;
      }
    } else if (dep.dependency_type === 'start_to_start') {
      const startX = pX;
      const startY = pY;
      const endX = sX;
      const endY = sY;
      const minX = Math.min(startX, endX) - 16;
      pathD = `M ${startX} ${startY} H ${minX} V ${endY} H ${endX}`;
    } else if (dep.dependency_type === 'finish_to_finish') {
      const startX = pX + pW;
      const startY = pY;
      const endX = sX + sW;
      const endY = sY;
      const maxX = Math.max(startX, endX) + 16;
      pathD = `M ${startX} ${startY} H ${maxX} V ${endY} H ${endX}`;
    }

    if (pathD) {
      result.push({
        id: dep.id,
        d: pathD,
        isConflict: dep.is_conflict,
        conflictReason: dep.conflict_reason,
        dependencyType: dep.dependency_type,
      });
    }
  });

  return result;
});
</script>

<template>
  <svg
    class="absolute inset-0 pointer-events-none z-15"
    :width="totalWidth"
    :height="totalHeight"
    xmlns="http://www.w3.org/2000/svg"
  >
    <defs>
      <!-- Normal arrow marker -->
      <marker
        id="gantt-arrow"
        viewBox="0 0 10 10"
        refX="8"
        refY="5"
        markerWidth="6"
        markerHeight="6"
        orient="auto-start-reverse"
      >
        <path d="M 0 1 L 8 5 L 0 9 z" fill="#6b7280" />
      </marker>

      <!-- Conflict arrow marker -->
      <marker
        id="gantt-arrow-conflict"
        viewBox="0 0 10 10"
        refX="8"
        refY="5"
        markerWidth="6"
        markerHeight="6"
        orient="auto-start-reverse"
      >
        <path d="M 0 1 L 8 5 L 0 9 z" fill="#be123c" />
      </marker>
    </defs>

    <g>
      <path
        v-for="p in paths"
        :key="p.id"
        :d="p.d"
        fill="none"
        :stroke="p.isConflict ? '#be123c' : '#6b7280'"
        :stroke-width="p.isConflict ? '2' : '1.5'"
        :stroke-dasharray="p.isConflict ? '4,3' : 'none'"
        :marker-end="p.isConflict ? 'url(#gantt-arrow-conflict)' : 'url(#gantt-arrow)'"
        class="pointer-events-auto transition-colors hover:stroke-[#640c0e]"
      >
        <title v-if="p.isConflict">{{ p.conflictReason || 'Dependency Conflict' }}</title>
      </path>
    </g>
  </svg>
</template>

