import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useUsageStore = defineStore('usage', () => {
  const usage = ref(null)
  const period = ref(0)
  const billingInterval = ref('monthly')

  async function fetchUsage(teamId, periodIndex = 0) {
    const res = await api.get(`/teams/${teamId}/usage`, { params: { period: periodIndex } })
    usage.value = res
    period.value = res.period_index
    billingInterval.value = res.billing_interval
  }

  return { usage, period, billingInterval, fetchUsage }
})
