<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 overflow-y-auto bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6"
        @click.self="handleBackdropClick"
        role="dialog"
        :aria-modal="true"
        :aria-labelledby="titleId"
        :aria-describedby="subtitle ? subtitleId : undefined"
      >
        <div
          ref="modalContainerRef"
          tabindex="-1"
          class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-[#ebe8de] overflow-hidden transform transition-all my-8 outline-none"
          :class="maxWidthClass"
          @keydown="handleTrapKeyDown"
        >
          <!-- Header -->
          <div class="px-6 py-4.5 border-b border-[#ebe8de] flex items-center justify-between bg-[#fffbf4]">
            <div class="space-y-0.5">
              <h3 :id="titleId" class="text-base sm:text-lg font-bold text-[#640c0e]">
                {{ title }}
              </h3>
              <p v-if="subtitle" :id="subtitleId" class="text-xs text-neutral-500">
                {{ subtitle }}
              </p>
            </div>
            <button
              v-if="showClose"
              ref="closeButtonRef"
              type="button"
              @click="close"
              class="w-8 h-8 rounded-lg flex items-center justify-center text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#640c0e] transition-colors"
              aria-label="Close dialog"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 max-h-[calc(85vh-140px)] overflow-y-auto">
            <slot />
          </div>

          <!-- Footer -->
          <div
            v-if="$slots.footer"
            class="px-6 py-4 bg-[#fffbf4]/60 border-t border-[#ebe8de] flex items-center justify-end gap-3"
          >
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { X } from 'lucide-vue-next';

interface Props {
  modelValue: boolean;
  title: string;
  subtitle?: string;
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl';
  showClose?: boolean;
  closeOnBackdrop?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  maxWidth: 'md',
  showClose: true,
  closeOnBackdrop: true,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'close'): void;
}>();

const modalContainerRef = ref<HTMLElement | null>(null);
const closeButtonRef = ref<HTMLButtonElement | null>(null);
const previousActiveElement = ref<HTMLElement | null>(null);

const uniqueId = Math.random().toString(36).substring(2, 9);
const titleId = computed(() => `modal-title-${uniqueId}`);
const subtitleId = computed(() => `modal-subtitle-${uniqueId}`);

const maxWidthClass = computed(() => {
  switch (props.maxWidth) {
    case 'sm':
      return 'max-w-sm';
    case 'lg':
      return 'max-w-2xl';
    case 'xl':
      return 'max-w-4xl';
    case 'md':
    default:
      return 'max-w-lg';
  }
});

function close() {
  emit('update:modelValue', false);
  emit('close');
}

function handleBackdropClick() {
  if (props.closeOnBackdrop) {
    close();
  }
}

/**
 * Returns list of tabbable elements inside modal for focus trapping.
 */
function getFocusableElements(): HTMLElement[] {
  if (!modalContainerRef.value) return [];
  const selector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  return Array.from(modalContainerRef.value.querySelectorAll<HTMLElement>(selector)).filter(
    (el) => el.offsetParent !== null && !el.hasAttribute('aria-hidden')
  );
}

/**
 * Traps focus within modal dialog during Tab and Shift+Tab.
 */
function handleTrapKeyDown(event: KeyboardEvent) {
  if (event.key !== 'Tab') return;

  const focusable = getFocusableElements();
  if (focusable.length === 0) {
    event.preventDefault();
    modalContainerRef.value?.focus();
    return;
  }

  const firstElement = focusable[0];
  const lastElement = focusable[focusable.length - 1];

  if (event.shiftKey) {
    // Shift + Tab: if on first element, wrap to last
    if (document.activeElement === firstElement || document.activeElement === modalContainerRef.value) {
      event.preventDefault();
      lastElement.focus();
    }
  } else {
    // Tab: if on last element, wrap to first
    if (document.activeElement === lastElement) {
      event.preventDefault();
      firstElement.focus();
    }
  }
}

function handleGlobalKeyDown(event: KeyboardEvent) {
  if (event.key === 'Escape' && props.modelValue) {
    event.stopPropagation();
    close();
  }
}

watch(
  () => props.modelValue,
  async (isOpen) => {
    if (typeof document === 'undefined') return;

    if (isOpen) {
      // Store trigger element for focus restoration upon close
      previousActiveElement.value = document.activeElement as HTMLElement | null;
      document.body.style.overflow = 'hidden';

      await nextTick();
      const focusable = getFocusableElements();
      if (focusable.length > 0) {
        // Prefer first form input or first focusable action
        focusable[0].focus();
      } else {
        modalContainerRef.value?.focus();
      }
    } else {
      document.body.style.overflow = '';
      // Restore focus to previous element
      if (previousActiveElement.value && typeof previousActiveElement.value.focus === 'function') {
        previousActiveElement.value.focus();
      }
    }
  }
);

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeyDown);
  if (typeof document !== 'undefined') {
    document.body.style.overflow = '';
  }
});
</script>
