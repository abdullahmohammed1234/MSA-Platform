import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const sidebarOpen = ref(true)
  const activeModal = ref<string | null>(null)
  const darkMode = ref(false)

  const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value
  }

  const setModal = (modalName: string | null) => {
    activeModal.value = modalName
  }

  const toggleDarkMode = () => {
    darkMode.value = !darkMode.value
  }

  return {
    sidebarOpen,
    activeModal,
    darkMode,
    toggleSidebar,
    setModal,
    toggleDarkMode
  }
})
