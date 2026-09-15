<script setup lang="ts">
import { useRouter } from 'vue-router';
import type { ApplicationMatrixItem } from '@/services/commandCenterService';

const props = defineProps<{
  applications: ApplicationMatrixItem[];
}>();

const router = useRouter();

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'operational':
    case 'healthy':
      return { text: 'Healthy', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
    case 'degraded':
      return { text: 'Degraded', class: 'bg-amber-100 text-amber-800 border-amber-200' };
    case 'unavailable':
      return { text: 'Unavailable', class: 'bg-red-100 text-red-800 border-red-200' };
    default:
      return { text: 'Signal Unavailable', class: 'bg-neutral-100 text-neutral-600 border-neutral-200' };
  }
};

const handleLaunch = (url: string) => {
  if (url.startsWith('http')) {
    window.open(url, '_blank');
  } else {
    router.push(url);
  }
};
</script>

<template>
  <div class="bg-white rounded-xl border border-neutral-ivory p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-bold text-neutral-black">
          Application Health Matrix
        </h3>
        <p class="text-xs text-neutral-muted">
          Cross-platform operational matrix monitoring health, active alerts, and access boundaries.
        </p>
      </div>

      <span class="text-xs font-mono font-bold text-neutral-muted">
        {{ applications.length }} System(s) Registered
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-neutral-background uppercase text-[10px] font-bold text-neutral-muted tracking-wider border-y border-neutral-ivory">
          <tr>
            <th class="py-3 px-4">Application</th>
            <th class="py-3 px-4">Health Status</th>
            <th class="py-3 px-4">Open Alerts</th>
            <th class="py-3 px-4">Status Reason</th>
            <th class="py-3 px-4">Access Boundary</th>
            <th class="py-3 px-4 text-right">Subsystem Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-ivory">
          <tr
            v-for="app in applications"
            :key="app.id"
            class="hover:bg-neutral-background/50 transition-colors"
          >
            <td class="py-3 px-4 font-bold text-neutral-black">
              {{ app.name }}
            </td>

            <td class="py-3 px-4">
              <span :class="['px-2.5 py-0.5 text-[10px] font-bold uppercase rounded border inline-block', getStatusBadge(app.status).class]">
                {{ getStatusBadge(app.status).text }}
              </span>
            </td>

            <td class="py-3 px-4 font-mono font-bold">
              <span v-if="app.open_issues_count > 0" class="text-amber-600">
                {{ app.open_issues_count }} Issue(s)
              </span>
              <span v-else class="text-neutral-muted">
                0
              </span>
            </td>

            <td class="py-3 px-4 text-neutral-muted max-w-xs truncate">
              {{ app.status_reason || 'Reachable and responding' }}
            </td>

            <td class="py-3 px-4">
              <span v-if="app.access_granted" class="text-[10px] font-bold uppercase text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                Granted
              </span>
              <span v-else class="text-[10px] font-bold uppercase text-neutral-500 bg-neutral-100 px-2 py-0.5 rounded border border-neutral-200">
                Restricted
              </span>
            </td>

            <td class="py-3 px-4 text-right">
              <button
                @click="handleLaunch(app.launch_url)"
                class="px-2.5 py-1 text-xs font-bold text-primary hover:text-primary-dark bg-primary/10 hover:bg-primary/20 rounded-lg transition-colors cursor-pointer"
              >
                Open Subsystem
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
