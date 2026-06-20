<script setup lang="ts">
import type { MilestoneTrackerProps } from './types';

const props = defineProps<MilestoneTrackerProps>();
</script>

<template>
  <div class="relative pl-6 space-y-6">
    <!-- Connecting central vertical line -->
    <div class="absolute left-2.5 top-2 bottom-2 w-0.5 bg-neutral-ivory z-0"></div>

    <div
      v-for="(milestone, idx) in milestones"
      :key="milestone.id"
      class="relative flex items-start gap-4 z-10"
    >
      <!-- Connecting overlay segment highlights (done path) -->
      <div
        v-if="idx < milestones.length - 1 && milestone.status === 'completed'"
        class="absolute left-[3px] top-5 h-8 w-0.5 bg-primary z-0"
      ></div>

      <!-- Node Shape Indicator -->
      <div class="flex-shrink-0">
        <!-- 1. Completed state -->
        <div
          v-if="milestone.status === 'completed'"
          class="h-5.5 w-5.5 rounded-full bg-primary text-white flex items-center justify-center shadow-soft"
        >
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
          </svg>
        </div>

        <!-- 2. Active state -->
        <div
          v-else-if="milestone.status === 'active'"
          class="h-5.5 w-5.5 rounded-full border-2 border-primary bg-white flex items-center justify-center animate-pulse shadow-glow"
        >
          <div class="h-2 w-2 rounded-full bg-primary"></div>
        </div>

        <!-- 3. Locked state -->
        <div
          v-else
          class="h-5.5 w-5.5 rounded-full bg-neutral-ivory text-neutral-muted flex items-center justify-center"
        >
          <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
      </div>

      <!-- Content text -->
      <div class="flex-1 min-w-0">
        <h4
          :class="[
            'text-sm font-semibold truncate leading-tight',
            milestone.status === 'active' ? 'text-primary' : '',
            milestone.status === 'locked' ? 'text-neutral-muted' : 'text-neutral-black'
          ]"
        >
          {{ milestone.label }}
        </h4>
        <p
          v-if="milestone.description"
          :class="[
            'text-xs mt-0.5 leading-relaxed',
            milestone.status === 'locked' ? 'text-neutral-muted/70' : 'text-neutral-muted'
          ]"
        >
          {{ milestone.description }}
        </p>
      </div>

    </div>
  </div>
</template>
