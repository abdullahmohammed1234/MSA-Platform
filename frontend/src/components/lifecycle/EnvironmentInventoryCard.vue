<template>
  <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft">
    <div class="flex items-center justify-between pb-4 border-b border-neutral-ivory mb-6">
      <div>
        <h3 class="text-lg font-display font-semibold text-primary">Environment & Release Inventory</h3>
        <p class="text-xs text-neutral-muted">Runtime versions, framework metadata & safe configuration state</p>
      </div>
      <span class="text-xs font-mono font-bold px-3 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
        {{ environment.environment }}
      </span>
    </div>

    <!-- Key Metadata Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="bg-neutral-background/70 border border-neutral-ivory rounded-xl p-3.5">
        <div class="text-xs text-neutral-muted font-medium">Release Identifier</div>
        <div class="text-sm font-mono font-bold text-primary mt-1 truncate">{{ release.release_identifier }}</div>
        <div class="text-[11px] text-neutral-muted mt-1">Version: {{ release.application_version }}</div>
      </div>

      <div class="bg-neutral-background/70 border border-neutral-ivory rounded-xl p-3.5">
        <div class="text-xs text-neutral-muted font-medium">Frontend Build</div>
        <div class="text-sm font-mono font-bold text-secondary mt-1 truncate">{{ release.frontend_build }}</div>
        <div class="text-[11px] text-neutral-muted mt-1">API Spec: {{ release.api_version }}</div>
      </div>

      <div class="bg-neutral-background/70 border border-neutral-ivory rounded-xl p-3.5">
        <div class="text-xs text-neutral-muted font-medium">Deployment Detected At</div>
        <div class="text-sm font-mono font-semibold text-neutral-black mt-1 truncate">{{ release.deployment_detected_at }}</div>
        <div class="text-[11px] text-neutral-muted mt-1">Method: {{ release.deployment_detection_method }}</div>
      </div>
    </div>

    <!-- Detailed Configuration Table -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
      <div class="bg-neutral-background/50 p-2.5 rounded-xl border border-neutral-ivory">
        <span class="text-neutral-muted block mb-0.5">Laravel Version</span>
        <span class="font-mono text-neutral-black font-semibold">{{ environment.laravel_version }}</span>
      </div>

      <div class="bg-neutral-background/50 p-2.5 rounded-xl border border-neutral-ivory">
        <span class="text-neutral-muted block mb-0.5">PHP Version</span>
        <span class="font-mono text-neutral-black font-semibold">{{ environment.php_version }}</span>
      </div>

      <div class="bg-neutral-background/50 p-2.5 rounded-xl border border-neutral-ivory">
        <span class="text-neutral-muted block mb-0.5">Database Server</span>
        <span class="font-mono text-neutral-black font-semibold truncate block">{{ environment.database_driver }} ({{ environment.database_version }})</span>
      </div>

      <div class="bg-neutral-background/50 p-2.5 rounded-xl border border-neutral-ivory">
        <span class="text-neutral-muted block mb-0.5">Debug Mode</span>
        <span class="font-semibold" :class="environment.debug_mode ? 'text-amber-700' : 'text-emerald-700'">
          {{ environment.debug_mode ? 'ENABLED (Debug)' : 'DISABLED (Production)' }}
        </span>
      </div>

      <div class="bg-neutral-background/50 p-2.5 rounded-xl border border-neutral-ivory">
        <span class="text-neutral-muted block mb-0.5">Cache Driver</span>
        <span class="font-mono text-neutral-black">{{ environment.cache_driver }}</span>
      </div>

      <div class="bg-neutral-background/50 p-2.5 rounded-xl border border-neutral-ivory">
        <span class="text-neutral-muted block mb-0.5">Queue Driver</span>
        <span class="font-mono text-neutral-black">{{ environment.queue_driver }}</span>
      </div>

      <div class="bg-neutral-background/50 p-2.5 rounded-xl border border-neutral-ivory">
        <span class="text-neutral-muted block mb-0.5">Mail Transport</span>
        <span class="font-mono text-neutral-black">{{ environment.mail_transport }}</span>
      </div>

      <div class="bg-neutral-background/50 p-2.5 rounded-xl border border-neutral-ivory">
        <span class="text-neutral-muted block mb-0.5">Filesystem Storage</span>
        <span class="font-mono text-neutral-black">{{ environment.filesystem_driver }}</span>
      </div>
    </div>

    <!-- Secret Sanitization Notice -->
    <div class="mt-4 p-3 bg-neutral-background border border-neutral-ivory rounded-xl flex items-center justify-between text-xs text-neutral-muted">
      <span>Hosting: <strong class="text-neutral-black">{{ release.hosting_environment }}</strong></span>
      <span class="text-emerald-700 font-mono font-bold flex items-center gap-1">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> 100% Secrets Redacted
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { EnvironmentInventory, ReleaseMetadata } from '../../services/lifecycleService'

defineProps<{
  environment: EnvironmentInventory
  release: ReleaseMetadata
}>()
</script>
