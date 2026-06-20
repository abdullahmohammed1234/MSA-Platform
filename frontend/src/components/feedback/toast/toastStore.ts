import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { ToastItem, ToastType } from './types';

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<ToastItem[]>([]);

  const addToast = (message: string, type: ToastType = 'info', duration = 4000) => {
    const id = `toast-${Math.random().toString(36).substring(2, 9)}`;
    const toast: ToastItem = { id, message, type, duration };
    toasts.value.push(toast);

    if (duration > 0) {
      setTimeout(() => {
        removeToast(id);
      }, duration);
    }
  };

  const removeToast = (id: string) => {
    toasts.value = toasts.value.filter(t => t.id !== id);
  };

  return {
    toasts,
    addToast,
    removeToast,
    success: (msg: string, dur?: number) => addToast(msg, 'success', dur),
    error: (msg: string, dur?: number) => addToast(msg, 'error', dur),
    warning: (msg: string, dur?: number) => addToast(msg, 'warning', dur),
    info: (msg: string, dur?: number) => addToast(msg, 'info', dur)
  };
});
