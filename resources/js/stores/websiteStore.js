import { defineStore } from 'pinia'
import api from '@/services/api'

export const useWebsiteStore = defineStore('website', {
  state: () => ({
    websites: [],
    loading: false,
    error: null
  }),
  actions: {
    async fetchWebsites() {
      this.loading = true
      try {
        const data = await api.get('/websites')
        this.websites = data
      } catch (e) {
        this.error = e.message
      } finally {
        this.loading = false
      }
    },
    async addWebsite(payload) {
      const site = await api.post('/websites', payload)
      this.websites.unshift(site)
    },
    async checkWebsite(id) {
      await api.post(`/websites/${id}/check-bots`)
    },
    async loadResults(id) {
      return await api.get(`/websites/${id}/results`)
    }
  }
})
