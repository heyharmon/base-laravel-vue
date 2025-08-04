import { defineStore } from 'pinia';
import api from '@/services/api';

export const useCategorizationStore = defineStore('categorization', {
  state: () => ({
    activeJobs: [],
    jobHistory: [],
    isLoading: false,
    error: null,
    pollingInterval: null
  }),

  getters: {
    hasActiveJobs: (state) => state.activeJobs.length > 0,
    totalActiveTransactions: (state) => {
      return state.activeJobs.reduce((total, job) => total + job.total_transactions, 0);
    },
    totalProcessedTransactions: (state) => {
      return state.activeJobs.reduce((total, job) => total + job.processed_transactions, 0);
    },
    overallProgress: (state) => {
      const total = state.activeJobs.reduce((sum, job) => sum + job.total_transactions, 0);
      const processed = state.activeJobs.reduce((sum, job) => sum + job.processed_transactions, 0);
      return total > 0 ? Math.round((processed / total) * 100) : 0;
    }
  },

  actions: {
    async categorizeTransaction(transactionId) {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await api.post(`/transactions/${transactionId}/categorize`);
        this.startPolling();
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to start categorization';
        console.error('Error categorizing transaction:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async categorizeBatch(transactionIds) {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await api.post('/transactions/categorize-batch', {
          transaction_ids: transactionIds
        });
        this.startPolling();
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to start batch categorization';
        console.error('Error categorizing batch:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async categorizeAll() {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await api.post('/transactions/categorize-all');
        this.startPolling();
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to start categorization of all transactions';
        console.error('Error categorizing all:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async fetchActiveJobs() {
      try {
        const response = await api.get('/categorization/jobs/active');
        this.activeJobs = response;

        if (response.length === 0) {
          this.stopPolling();
        }
      } catch (error) {
        console.error('Error fetching active jobs:', error);
      }
    },

    async fetchJobHistory() {
      this.isLoading = true;
      try {
        const response = await api.get('/categorization/jobs');
        this.jobHistory = response.data;
      } catch (error) {
        this.error = error.message || 'Failed to fetch job history';
        console.error('Error fetching job history:', error);
      } finally {
        this.isLoading = false;
      }
    },

    startPolling() {
      if (this.pollingInterval) return;

      this.pollingInterval = setInterval(() => {
        this.fetchActiveJobs();
      }, 2000);
    },

    stopPolling() {
      if (this.pollingInterval) {
        clearInterval(this.pollingInterval);
        this.pollingInterval = null;
      }
    },

    cleanup() {
      this.stopPolling();
    }
  }
});
