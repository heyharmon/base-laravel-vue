import { defineStore } from 'pinia'
import api from '@/services/api'

export const useCampaignStore = defineStore('campaign', {
	state: () => ({
		campaigns: [],
		currentCampaign: null,
		isLoading: false
	}),

	getters: {
		defaultCampaign: (state) => {
			return state.campaigns.find((campaign) => campaign.is_default) || null
		}
	},

	actions: {
		async fetchCampaigns() {
			console.log('Fetching campaigns...')
			this.isLoading = true

			try {
				const response = await api.get('/campaigns')
				this.campaigns = response

				// Set current campaign based on user's current_campaign_id
				const user = JSON.parse(localStorage.getItem('user'))
				if (user && user.current_campaign_id) {
					this.currentCampaign = this.campaigns.find((campaign) => campaign.id === user.current_campaign_id)
				}

				// If no current campaign set, use default
				if (!this.currentCampaign && this.campaigns.length > 0) {
					const defaultCampaign = this.campaigns.find((campaign) => campaign.is_default)
					if (defaultCampaign) {
						this.currentCampaign = defaultCampaign
					}
				}
			} catch (error) {
				console.error('Error fetching campaigns:', error)
			} finally {
				this.isLoading = false
			}
		},

		async createCampaign(campaignData) {
			console.log('Creating campaign...')
			this.isLoading = true

			try {
				const response = await api.post('/campaigns', campaignData)
				this.campaigns.push(response)
				return response
			} catch (error) {
				console.error('Error creating campaign:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		async updateCampaign(campaignId, campaignData) {
			console.log('Updating campaign ID:', campaignId)
			this.isLoading = true

			try {
				const response = await api.put(`/campaigns/${campaignId}`, campaignData)
				const index = this.campaigns.findIndex((c) => c.id === campaignId)
				if (index !== -1) {
					this.campaigns[index] = response
				}
				if (this.currentCampaign && this.currentCampaign.id === campaignId) {
					this.currentCampaign = response
				}
				return response
			} catch (error) {
				console.error('Error updating campaign:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		async deleteCampaign(campaignId) {
			console.log('Deleting campaign ID:', campaignId)
			this.isLoading = true

			try {
				await api.delete(`/campaigns/${campaignId}`)
				this.campaigns = this.campaigns.filter((c) => c.id !== campaignId)

				// If deleted campaign was current, switch to default
				if (this.currentCampaign && this.currentCampaign.id === campaignId) {
					const defaultCampaign = this.campaigns.find((campaign) => campaign.is_default)
					if (defaultCampaign) {
						this.switchCampaign(defaultCampaign.id)
					}
				}
			} catch (error) {
				console.error('Error deleting campaign:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		async switchCampaign(campaignId) {
			console.log('Switching to campaign ID:', campaignId)
			this.isLoading = true

			try {
				const response = await api.post(`/campaigns/${campaignId}/switch`)
				if (response.campaign && response.message) {
					// Update the user in localStorage with new current campaign
					const user = JSON.parse(localStorage.getItem('user'))
					if (user) {
						user.current_campaign_id = response.campaign.id
						localStorage.setItem('user', JSON.stringify(user))
					}

					// Set current campaign in store
					this.currentCampaign = response.campaign

					return response
				}
				return null
			} catch (error) {
				console.error('Error switching campaign:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		initializeCampaign() {
			// Fetch campaigns from API
			this.fetchCampaigns()
		}
	}
})
