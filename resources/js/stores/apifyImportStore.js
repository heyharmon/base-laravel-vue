import { defineStore } from 'pinia';
import api from '@/services/api';

export const useApifyImportStore = defineStore('apifyImport', {
  state: () => ({
    imports: [],
    currentImport: null,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0,
    },
    isLoading: false,
    error: null,
  }),
  
  actions: {
    async startImport(importData) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.post('/apify/start-import', importData);
        await this.fetchImports();
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to start import';
        console.error('Error starting import:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
    
    async fetchImports(page = 1) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.get('/apify/imports', {
          params: { page }
        });
        
        this.imports = response.data;
        this.pagination = {
          current_page: response.current_page,
          last_page: response.last_page,
          per_page: response.per_page,
          total: response.total,
        };
      } catch (error) {
        this.error = error.message || 'Failed to fetch imports';
        console.error('Error fetching imports:', error);
      } finally {
        this.isLoading = false;
      }
    },
    
    async fetchImport(id) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.get(`/apify/imports/${id}`);
        this.currentImport = response;
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to fetch import';
        console.error('Error fetching import:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
  },
});
