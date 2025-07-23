import { defineStore } from 'pinia'
import api from '@/services/api'

export const useCampaignStore = defineStore('campaign', {
    state: () => ({
        campaigns: [],
        isLoading: false
    }),
    actions: {
        async fetchCampaigns(teamId) {
            this.isLoading = true
            try {
                this.campaigns = await api.get(`/teams/${teamId}/campaigns`)
            } finally {
                this.isLoading = false
            }
        },
        async createCampaign(teamId, data) {
            this.isLoading = true
            try {
                const campaign = await api.post(`/teams/${teamId}/campaigns`, data)
                this.campaigns.push(campaign)
                return campaign
            } finally {
                this.isLoading = false
            }
        }
    }
})
