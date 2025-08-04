import { defineStore } from 'pinia';
import api from '@/services/api';

export const useTransactionStore = defineStore('transaction', {
  state: () => ({
    transactions: {
      data: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 50,
        total: 0
      }
    },
    selectedTransactions: [],
    filters: {
      account_id: null,
      category_id: null,
      type: null,
      date_from: null,
      date_to: null,
      amount_min: null,
      amount_max: null,
      search: null
    },
    isLoading: false,
    error: null
  }),

  getters: {
    hasSelectedTransactions: (state) => state.selectedTransactions.length > 0,
    selectedTransactionIds: (state) => state.selectedTransactions.map(t => t.id)
  },

  actions: {
    async fetchTransactions(page = 1, resetData = false) {
      this.isLoading = true;
      this.error = null;

      try {
        const params = {
          page,
          per_page: this.transactions.meta?.per_page || 50,
          ...this.filters
        };

        Object.keys(params).forEach(key => {
          if (params[key] === null || params[key] === '') {
            delete params[key];
          }
        });

        const response = await api.get('/transactions', { params });

        if (resetData || page === 1) {
          this.transactions = response;
        } else {
          this.transactions.data.push(...response.data);
          this.transactions.meta = response.meta;
        }

        if (resetData) {
          this.selectedTransactions = [];
        }
      } catch (error) {
        this.error = error.message || 'Failed to fetch transactions';
        console.error('Error fetching transactions:', error);
      } finally {
        this.isLoading = false;
      }
    },

    async uploadCsv(file, accountId) {
      this.isLoading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('account_id', accountId);

        const response = await api.post('/transactions/upload-csv', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });

        await this.fetchTransactions(1, true);

        return response;
      } catch (error) {
        this.error = error.message || 'Failed to upload CSV';
        console.error('Error uploading CSV:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async bulkUpdateCategory(transactionIds, categoryId) {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await api.put('/transactions/bulk-update-category', {
          transaction_ids: transactionIds,
          category_id: categoryId
        });

        this.transactions.data.forEach(transaction => {
          if (transactionIds.includes(transaction.id)) {
            transaction.category_id = categoryId;
            if (categoryId) {
              transaction.category = { id: categoryId };
            } else {
              transaction.category = null;
            }
          }
        });

        this.selectedTransactions = [];

        return response;
      } catch (error) {
        this.error = error.message || 'Failed to update categories';
        console.error('Error updating categories:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    setFilter(key, value) {
      this.filters[key] = value;
    },

    clearFilters() {
      this.filters = {
        account_id: null,
        category_id: null,
        type: null,
        date_from: null,
        date_to: null,
        amount_min: null,
        amount_max: null,
        search: null
      };
    },

    toggleTransactionSelection(transaction) {
      const index = this.selectedTransactions.findIndex(t => t.id === transaction.id);
      if (index === -1) {
        this.selectedTransactions.push(transaction);
      } else {
        this.selectedTransactions.splice(index, 1);
      }
    },

    selectAllTransactions() {
      this.selectedTransactions = [...this.transactions.data];
    },

    clearSelection() {
      this.selectedTransactions = [];
    }
  }
});
