import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useUsageStore = defineStore('usage', () => {
  const usage = ref(null)

  async function fetchUsage(teamId, params = {}) {
    usage.value = await api.get(`/teams/${teamId}/usage`, { params })
  }

  return { usage, fetchUsage }
})
