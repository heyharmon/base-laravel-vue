import { defineStore } from 'pinia'
import api from '@/services/api'

export const useCampaignStore = defineStore('campaign', {
        state: () => ({
                campaigns: [],
                currentCampaign: null,
                isLoading: false,
                error: null
        }),

        getters: {
                defaultCampaign: (state) => {
                        return state.campaigns.find(c => c.is_default) || null
                }
        },

        actions: {
                async fetchCampaigns(teamId) {
                        this.isLoading = true
                        try {
                                const response = await api.get(`/teams/${teamId}/campaigns`)
                                this.campaigns = response
                                if (!this.currentCampaign && this.campaigns.length > 0) {
                                        this.currentCampaign = this.defaultCampaign || this.campaigns[0]
                                }
                        } catch (error) {
                                this.error = error.message
                                console.error('Error fetching campaigns:', error)
                        } finally {
                                this.isLoading = false
                        }
                },

                async createCampaign(teamId, campaignData) {
                        try {
                                const response = await api.post(`/teams/${teamId}/campaigns`, campaignData)
                                this.campaigns.push(response)

                                if (response.is_default) {
                                        this.currentCampaign = response
                                        localStorage.setItem(`team_${teamId}_current_campaign`, JSON.stringify(response))
                                }

                                return response
                        } catch (error) {
                                this.error = error.message
                                throw error
                        }
                },

                async createDefaultCampaign(teamId, { location, description }) {
                        try {
                                const response = await api.post(`/teams/${teamId}/campaigns/default`, {
                                        location,
                                        description
                                })
                                this.campaigns.push(response)
                                this.currentCampaign = response
                                localStorage.setItem(`team_${teamId}_current_campaign`, JSON.stringify(response))
                                return response
                        } catch (error) {
                                this.error = error.message
                                throw error
                        }
                },

                async updateCampaign(teamId, campaignId, campaignData) {
                        try {
                                const response = await api.put(`/teams/${teamId}/campaigns/${campaignId}`, campaignData)
                                const index = this.campaigns.findIndex(c => c.id === campaignId)
                                if (index !== -1) {
                                        this.campaigns[index] = response
                                }
                                if (this.currentCampaign?.id === campaignId) {
                                        this.currentCampaign = response
                                }
                                return response
                        } catch (error) {
                                this.error = error.message
                                throw error
                        }
                },

                async deleteCampaign(teamId, campaignId) {
                        try {
                                await api.delete(`/teams/${teamId}/campaigns/${campaignId}`)
                                this.campaigns = this.campaigns.filter(c => c.id !== campaignId)
                                if (this.currentCampaign?.id === campaignId) {
                                        this.currentCampaign = this.defaultCampaign || this.campaigns[0] || null
                                }
                        } catch (error) {
                                this.error = error.message
                                throw error
                        }
                },

                async switchCampaign(teamId, campaignId) {
                        try {
                                const campaign = this.campaigns.find(c => c.id === campaignId)
                                if (campaign) {
                                        await api.post(`/teams/${teamId}/campaigns/${campaignId}/switch`)
                                        this.currentCampaign = campaign
                                        localStorage.setItem(`team_${teamId}_current_campaign`, JSON.stringify(campaign))
                                }
                        } catch (error) {
                                this.error = error.message
                                throw error
                        }
                },

                loadCurrentCampaign(teamId) {
                        const stored = localStorage.getItem(`team_${teamId}_current_campaign`)
                        if (stored) {
                                try {
                                        this.currentCampaign = JSON.parse(stored)
                                } catch (e) {
                                        console.error('Error loading stored campaign:', e)
                                }
                        }
                }
        }
})
