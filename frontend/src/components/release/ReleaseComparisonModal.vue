<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-black/50 backdrop-blur-sm">
    <div class="bg-white border border-neutral-ivory rounded-2xl max-w-2xl w-full p-6 shadow-premium space-y-6 text-neutral-black">
      <div class="flex items-center justify-between pb-4 border-b border-neutral-ivory/60">
        <div>
          <h3 class="text-lg font-display font-semibold text-primary tracking-wide">Release Comparison Matrix</h3>
          <p class="text-xs text-neutral-muted">Side-by-side comparative analysis between releases</p>
        </div>
        <button @click="$emit('close')" class="text-neutral-muted hover:text-neutral-black cursor-pointer text-sm font-bold">✕</button>
      </div>

      <div v-if="comparison" class="grid grid-cols-2 gap-4">
        <!-- Release A -->
        <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-4 space-y-2">
          <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider">Baseline Release A</div>
          <div class="text-base font-bold text-primary font-mono">{{ comparison.release_a.identifier }}</div>
          <div class="text-xs text-neutral-black">Version: {{ comparison.release_a.version }}</div>
          <div class="text-xs text-neutral-black">Status: {{ comparison.release_a.status }}</div>
          <div class="text-xs text-neutral-black">Changes Count: {{ comparison.release_a.changes_count }}</div>
        </div>

        <!-- Release B -->
        <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-4 space-y-2">
          <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider">Comparison Release B</div>
          <div class="text-base font-bold text-emerald-700 font-mono">{{ comparison.release_b.identifier }}</div>
          <div class="text-xs text-neutral-black">Version: {{ comparison.release_b.version }}</div>
          <div class="text-xs text-neutral-black">Status: {{ comparison.release_b.status }}</div>
          <div class="text-xs text-neutral-black">Changes Count: {{ comparison.release_b.changes_count }}</div>
        </div>
      </div>

      <!-- Diff Summary -->
      <div v-if="comparison" class="bg-neutral-background/40 border border-neutral-ivory rounded-xl p-4 space-y-2">
        <h4 class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Diff Evaluation</h4>
        <div class="grid grid-cols-3 gap-2 text-center text-xs">
          <div class="bg-white border border-neutral-ivory rounded-lg p-2">
            <div class="text-neutral-muted">Version Diff</div>
            <div class="font-bold font-mono text-primary">{{ comparison.comparison.version_diff }}</div>
          </div>
          <div class="bg-white border border-neutral-ivory rounded-lg p-2">
            <div class="text-neutral-muted">Status Diff</div>
            <div class="font-bold font-mono text-emerald-700">{{ comparison.comparison.status_diff }}</div>
          </div>
          <div class="bg-white border border-neutral-ivory rounded-lg p-2">
            <div class="text-neutral-muted">Changes Difference</div>
            <div class="font-bold font-mono text-amber-700">{{ comparison.comparison.changes_diff_count }} item(s)</div>
          </div>
        </div>
      </div>

      <div class="flex justify-end pt-4 border-t border-neutral-ivory/60">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-xs font-semibold rounded-xl bg-white border border-neutral-ivory text-neutral-black hover:bg-neutral-background cursor-pointer"
        >
          Close Matrix
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ComparisonResult } from '../../services/releaseService'

defineProps<{
  isOpen: boolean
  comparison: ComparisonResult | null
}>()

defineEmits(['close'])
</script>
