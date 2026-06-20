<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';

const props = defineProps<{
  lessonId: number;
}>();

const notes = ref('');
const saved = ref(false);

const storageKey = () => `lesson_notes_${props.lessonId}`;

onMounted(() => {
  notes.value = sessionStorage.getItem(storageKey()) || '';
});

watch(notes, (value) => {
  sessionStorage.setItem(storageKey(), value);
  saved.value = true;
  window.setTimeout(() => {
    saved.value = false;
  }, 1200);
});
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <div>
        <h3 class="text-sm font-semibold text-primary">Personal Notes</h3>
        <p class="text-xs text-neutral-muted mt-1">Saved locally for this browser session.</p>
      </div>
      <span v-if="saved" class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Saved</span>
    </div>

    <textarea
      v-model="notes"
      rows="10"
      placeholder="Capture reflections, action items, or outreach reminders..."
      class="input-base resize-none text-sm"
    />
  </div>
</template>
