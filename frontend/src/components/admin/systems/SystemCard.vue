<script setup lang="ts">
import type { SystemApplication } from '@/types/systems'
import SystemStatusBadge from './SystemStatusBadge.vue'
import SystemLastChecked from './SystemLastChecked.vue'
import { ExternalLink, ChevronRight } from 'lucide-vue-next'

defineProps<{
  app: SystemApplication
}>()
</script>

<template>
  <article class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft flex flex-col gap-4">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h3 class="text-lg font-display font-bold text-neutral-black">{{ app.name }}</h3>
        <p class="mt-1 text-sm text-neutral-muted leading-relaxed">{{ app.description }}</p>
      </div>
      <SystemStatusBadge :status="app.status" />
    </div>

    <p v-if="app.status_reason && app.status !== 'operational'" class="text-xs text-neutral-black rounded-xl bg-neutral-background px-3 py-2">
      {{ app.status_reason }}
    </p>

    <dl class="grid grid-cols-2 gap-3 text-sm">
      <div>
        <dt class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Version</dt>
        <dd class="mt-1 font-semibold text-neutral-black">{{ app.version || 'unknown' }}</dd>
      </div>
      <div>
        <dt class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">URL</dt>
        <dd class="mt-1 font-semibold text-primary break-all">{{ app.url || '—' }}</dd>
      </div>
    </dl>

    <SystemLastChecked :checked-at="app.last_checked_at" />

    <div v-if="app.dependency_details?.length" class="flex flex-wrap gap-1.5">
      <span
        v-for="dep in app.dependency_details"
        :key="dep.id"
        class="rounded-full bg-neutral-background px-2.5 py-1 text-[11px] font-semibold text-neutral-black"
        :title="dep.message || dep.status"
      >
        {{ dep.label }} · {{ dep.status }}
      </span>
    </div>

    <div class="mt-auto flex flex-wrap gap-2 pt-1">
      <router-link
        v-if="app.url"
        :to="app.url"
        class="inline-flex items-center gap-2 rounded-xl bg-primary px-3 py-2 text-sm font-semibold text-white hover:opacity-90"
      >
        Open application
        <ExternalLink class="h-4 w-4" />
      </router-link>
      <router-link
        v-if="app.admin_path"
        :to="app.admin_path"
        class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory px-3 py-2 text-sm font-semibold text-neutral-black hover:bg-neutral-background"
      >
        Investigate
        <ChevronRight class="h-4 w-4" />
      </router-link>
    </div>
  </article>
</template>
