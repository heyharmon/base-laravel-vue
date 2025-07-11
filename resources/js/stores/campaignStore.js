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

				// Set current campaign to default if not already set
				if (!this.currentCampaign && this.campaigns.length > 0) {
					const defaultCampaign = this.campaigns.find((campaign) => campaign.is_default)
					if (defaultCampaign) {
						this.currentCampaign = defaultCampaign
						this.saveCampaignToStorage(defaultCampaign)
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
					this.saveCampaignToStorage(response)
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

		switchCampaign(campaignId) {
			console.log('Switching to campaign ID:', campaignId)
			const campaign = this.campaigns.find((c) => c.id === campaignId)
			if (campaign) {
				this.currentCampaign = campaign
				this.saveCampaignToStorage(campaign)
				return campaign
			}
			return null
		},

		saveCampaignToStorage(campaign) {
			localStorage.setItem('currentCampaign', JSON.stringify(campaign))
		},

		loadCampaignFromStorage() {
			const stored = localStorage.getItem('currentCampaign')
			if (stored) {
				try {
					this.currentCampaign = JSON.parse(stored)
				} catch (error) {
					console.error('Error parsing stored campaign:', error)
					localStorage.removeItem('currentCampaign')
				}
			}
		},

		initializeCampaign() {
			// Load from storage first
			this.loadCampaignFromStorage()

			// Then fetch campaigns from API
			this.fetchCampaigns()
		}
	}
})
