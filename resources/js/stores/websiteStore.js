import { defineStore } from 'pinia'
import api from '@/services/api'

export const useWebsiteStore = defineStore('website', {
  state: () => ({
    websites: [],
    currentWebsite: null,
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
    },
    async fetchWebsite(id) {
      this.loading = true
      try {
        const website = await api.get(`/websites/${id}`)
        const results = await api.get(`/websites/${id}/results`)
        this.currentWebsite = { ...website, results }
        return this.currentWebsite
      } catch (e) {
        this.error = e.message
        throw e
      } finally {
        this.loading = false
      }
    }
  }
})
