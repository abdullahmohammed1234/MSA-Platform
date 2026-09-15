<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-black/50 backdrop-blur-sm">
    <div class="bg-white border border-neutral-ivory rounded-2xl max-w-lg w-full p-6 shadow-premium space-y-6 text-neutral-black">
      <div class="flex items-center justify-between pb-4 border-b border-neutral-ivory/60">
        <div>
          <h3 class="text-lg font-display font-semibold text-primary tracking-wide">Release Approval Control</h3>
          <p class="text-xs text-neutral-muted">Governance authorization with enforced separation of duties</p>
        </div>
        <button @click="$emit('close')" class="text-neutral-muted hover:text-neutral-black cursor-pointer text-sm font-bold">✕</button>
      </div>

      <div class="space-y-4">
        <div class="bg-neutral-background/60 border border-neutral-ivory rounded-xl p-4">
          <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider mb-1">Target Release</div>
          <div class="text-base font-bold text-primary font-mono">{{ releaseIdentifier }}</div>
        </div>

        <div>
          <label class="block text-xs font-bold text-neutral-muted uppercase tracking-wider mb-2">
            Authorization & Approval Notes
          </label>
          <textarea
            v-model="notes"
            rows="3"
            placeholder="Enter release authorization notes, compliance confirmation, or governance sign-off..."
            class="w-full bg-white border border-neutral-ivory text-xs rounded-xl p-3 text-neutral-black focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
          ></textarea>
        </div>

        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 leading-relaxed font-medium">
          <span class="font-bold text-amber-900">Separation of Duties Notice:</span>
          The release creator cannot approve their own release unless they hold Super Admin status.
        </div>

        <div v-if="errorMessage" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-semibold">
          {{ errorMessage }}
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-4 border-t border-neutral-ivory/60">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-xs font-semibold rounded-xl bg-white border border-neutral-ivory text-neutral-black hover:bg-neutral-background cursor-pointer"
        >
          Cancel
        </button>
        <button
          @click="submitApproval"
          :disabled="isSubmitting"
          class="px-4 py-2 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white disabled:opacity-50 cursor-pointer shadow-soft"
        >
          {{ isSubmitting ? 'Approving...' : 'Approve Release' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{
  isOpen: boolean
  releaseIdentifier: string
  errorMessage?: string
}>()

const emit = defineEmits(['close', 'approve'])

const notes = ref('')
const isSubmitting = ref(false)

const submitApproval = async () => {
  isSubmitting.value = true
  try {
    await emit('approve', notes.value)
  } finally {
    isSubmitting.value = false
  }
}
</script>
