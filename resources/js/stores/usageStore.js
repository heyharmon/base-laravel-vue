import { defineStore } from 'pinia'
import api from '@/services/api'

export const useUsageStore = defineStore('usage', {
    state: () => ({
        usage: null,
        isLoading: false
    }),
    actions: {
        async fetchUsage(teamId) {
            this.isLoading = true
            try {
                const response = await api.get(`/teams/${teamId}/usage`)
                this.usage = response
                return response
            } catch (error) {
                console.error('Error fetching usage:', error)
                throw error
            } finally {
                this.isLoading = false
            }
        },
        async fetchAdminTeamUsage(teamId, month) {
            try {
                const params = month ? { month } : {}
                const response = await api.get(`/super-admin/teams/${teamId}/usage`, { params })
                return response
            } catch (error) {
                console.error('Error fetching team usage detail:', error)
                throw error
            }
        },
        async updateLimit(teamId, tokenLimitPrice) {
            try {
                const response = await api.put(`/super-admin/teams/${teamId}/limit`, {
                    token_limit_price: tokenLimitPrice
                })
                return response
            } catch (error) {
                console.error('Error updating limit:', error)
                throw error
            }
        }
    }
})
