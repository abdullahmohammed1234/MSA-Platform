<script setup lang="ts">
withDefaults(defineProps<{ rows?: number; columns?: number }>(), { rows: 5, columns: 5 });
</script>

<template>
  <div class="divide-y divide-neutral-ivory" role="status" aria-label="Loading results">
    <div v-for="row in rows" :key="row" class="flex items-center gap-4 px-4 py-4">
      <div
        v-for="column in columns"
        :key="column"
        class="h-3 rounded-full bg-neutral-ivory/70 animate-pulse"
        :class="column === 1 ? 'flex-[2]' : 'flex-1'"
      />
    </div>
  </div>
</template>
