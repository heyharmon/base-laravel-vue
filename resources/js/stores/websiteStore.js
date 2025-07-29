import { defineStore } from 'pinia'
import api from '@/services/api'

export const useWebsiteStore = defineStore('website', {
  state: () => ({
    websites: [],
    currentWebsite: null,
    isLoading: false,
    error: null
  }),

  actions: {
    async fetchWebsites() {
      this.isLoading = true
      this.error = null
      try {
        const data = await api.get('/websites')
        this.websites = data
      } catch (e) {
        this.error = e.message || 'Failed to fetch websites'
      } finally {
        this.isLoading = false
      }
    },

    async createWebsite(payload) {
      this.isLoading = true
      this.error = null
      try {
        const res = await api.post('/websites', payload)
        this.websites.push(res.website)
        return res.website
      } catch (e) {
        this.error = e.message || 'Failed to create website'
        throw e
      } finally {
        this.isLoading = false
      }
    },

    async fetchWebsite(id, params = {}) {
      this.isLoading = true
      this.error = null
      try {
        const res = await api.get(`/websites/${id}`, { params })
        this.currentWebsite = res
        return res
      } catch (e) {
        this.error = e.message || 'Failed to fetch website'
        throw e
      } finally {
        this.isLoading = false
      }
    }
  }
})
