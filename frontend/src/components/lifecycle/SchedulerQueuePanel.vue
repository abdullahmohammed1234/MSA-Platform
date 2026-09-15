<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-neutral-ivory">
      <div>
        <h3 class="text-lg font-display font-semibold text-primary">Scheduler & Queue Operations Inventory</h3>
        <p class="text-xs text-neutral-muted">Defined tasks, execution semantics & queue worker backlog telemetry</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-xs font-mono font-bold px-3 py-1 rounded-full bg-neutral-background border border-neutral-ivory text-neutral-black">
          Cron: {{ schedulerQueue.scheduler.status }}
        </span>
        <span class="text-xs font-mono font-bold px-3 py-1 rounded-full bg-neutral-background border border-neutral-ivory text-neutral-black">
          Queue: {{ schedulerQueue.queues.verification_state }}
        </span>
      </div>
    </div>

    <!-- 2 Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Scheduler Column -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h4 class="text-sm font-semibold text-neutral-black flex items-center gap-2">
            <span>Laravel Schedule Kernel</span>
            <span class="text-xs font-normal text-neutral-muted">({{ schedulerQueue.scheduler.defined_tasks_count }} defined)</span>
          </h4>
          <span class="text-[11px] text-neutral-muted">Driver: {{ schedulerQueue.scheduler.driver }}</span>
        </div>

        <div class="space-y-2.5">
          <div
            v-for="task in schedulerQueue.scheduler.tasks"
            :key="task.command"
            class="bg-neutral-background/70 border border-neutral-ivory rounded-xl p-3"
          >
            <div class="flex items-center justify-between mb-1">
              <span class="font-mono text-xs font-bold text-primary">{{ task.command }}</span>
              <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded border" :class="taskTagClass(task.status)">
                {{ task.status }}
              </span>
            </div>
            <p class="text-xs text-neutral-muted mb-1.5">{{ task.description }}</p>
            <div class="text-[10px] font-mono text-neutral-muted">Cron expression: {{ task.expression }}</div>
          </div>
        </div>

        <div class="bg-neutral-background p-3 rounded-xl text-[11px] text-neutral-muted border border-neutral-ivory">
          <div class="font-bold text-neutral-black mb-0.5">Execution Semantics:</div>
          <p>Shared-hosting cPanel cron execution is verified via periodic runtime cache heartbeats.</p>
        </div>
      </div>

      <!-- Queue Column -->
      <div class="space-y-4">
        <h4 class="text-sm font-semibold text-neutral-black">Queue & Background Worker Telemetry</h4>

        <div class="grid grid-cols-2 gap-3">
          <div class="bg-neutral-background/70 border border-neutral-ivory rounded-xl p-3.5 text-center">
            <div class="text-xs text-neutral-muted font-medium">Queue Driver</div>
            <div class="text-lg font-mono font-bold text-neutral-black mt-1">{{ schedulerQueue.queues.driver }}</div>
          </div>

          <div class="bg-neutral-background/70 border border-neutral-ivory rounded-xl p-3.5 text-center">
            <div class="text-xs text-neutral-muted font-medium">Failed Jobs Backlog</div>
            <div
              class="text-lg font-mono font-bold mt-1"
              :class="schedulerQueue.queues.failed_jobs_count > 0 ? 'text-amber-700' : 'text-emerald-700'"
            >
              {{ schedulerQueue.queues.failed_jobs_count }}
            </div>
          </div>
        </div>

        <div class="bg-neutral-background/70 border border-neutral-ivory rounded-xl p-4 space-y-3">
          <div>
            <div class="text-xs text-neutral-muted font-medium mb-1">Configured Queue Names</div>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="q in schedulerQueue.queues.configured_queues"
                :key="q"
                class="font-mono text-xs font-bold px-2.5 py-0.5 rounded-lg bg-white border border-neutral-ivory text-neutral-black"
              >
                {{ q }}
              </span>
            </div>
          </div>

          <div class="pt-2 border-t border-neutral-ivory text-xs text-neutral-muted">
            <span class="font-bold text-neutral-black">Worker Expectation:</span>
            <p class="mt-0.5 leading-relaxed">{{ schedulerQueue.queues.worker_expectation }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { SchedulerQueueInventory } from '../../services/lifecycleService'

defineProps<{
  schedulerQueue: SchedulerQueueInventory
}>()

function taskTagClass(status: string) {
  switch (status) {
    case 'VERIFIED':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200'
    case 'EXPECTED':
      return 'bg-amber-100 text-amber-800 border-amber-200'
    case 'DEFINED':
      return 'bg-blue-100 text-blue-800 border-blue-200'
    default:
      return 'bg-neutral-100 text-neutral-800 border-neutral-200'
  }
}
</script>
