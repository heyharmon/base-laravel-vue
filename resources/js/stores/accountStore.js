import { defineStore } from 'pinia';
import api from '@/services/api';

export const useAccountStore = defineStore('account', {
  state: () => ({
    accounts: [],
    currentAccount: null,
    isLoading: false,
    error: null
  }),

  actions: {
    async fetchAccounts() {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await api.get('/accounts');
        this.accounts = response;
      } catch (error) {
        this.error = error.message || 'Failed to fetch accounts';
        console.error('Error fetching accounts:', error);
      } finally {
        this.isLoading = false;
      }
    },

    async fetchAccount(accountId) {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await api.get(`/accounts/${accountId}`);
        this.currentAccount = response;
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to fetch account';
        console.error('Error fetching account:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async createAccount(accountData) {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await api.post('/accounts', accountData);
        this.accounts.push(response);
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to create account';
        console.error('Error creating account:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async updateAccount(accountId, accountData) {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await api.put(`/accounts/${accountId}`, accountData);
        const index = this.accounts.findIndex(a => a.id === accountId);
        if (index !== -1) {
          this.accounts[index] = response;
        }
        if (this.currentAccount && this.currentAccount.id === accountId) {
          this.currentAccount = response;
        }
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to update account';
        console.error('Error updating account:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async deleteAccount(accountId) {
      this.isLoading = true;
      this.error = null;

      try {
        await api.delete(`/accounts/${accountId}`);
        this.accounts = this.accounts.filter(a => a.id !== accountId);
        if (this.currentAccount && this.currentAccount.id === accountId) {
          this.currentAccount = null;
        }
      } catch (error) {
        this.error = error.message || 'Failed to delete account';
        console.error('Error deleting account:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    }
  }
});
