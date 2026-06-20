import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUserStore = defineStore('user', () => {
  const profile = ref<any | null>(null)

  const setProfile = (newProfile: any | null) => {
    profile.value = newProfile
  }

  return {
    profile,
    setProfile
  }
})
