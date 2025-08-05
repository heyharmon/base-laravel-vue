import { defineStore } from 'pinia';
import api from '@/services/api';

export const useOrganizationStore = defineStore('organization', {
  state: () => ({
    organizations: [],
    currentOrganization: null,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
    },
    filters: {
      search: '',
      city: '',
      state: '',
      category: '',
      sort_by: 'name',
      sort_direction: 'asc',
    },
    isLoading: false,
    error: null,
  }),
  
  actions: {
    async fetchOrganizations(page = 1) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const params = {
          page,
          ...this.filters,
        };
        
        const response = await api.get('/organizations', { params });
        
        this.organizations = response.data;
        this.pagination = {
          current_page: response.current_page,
          last_page: response.last_page,
          per_page: response.per_page,
          total: response.total,
        };
      } catch (error) {
        this.error = error.message || 'Failed to fetch organizations';
        console.error('Error fetching organizations:', error);
      } finally {
        this.isLoading = false;
      }
    },
    
    async fetchOrganization(id) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.get(`/organizations/${id}`);
        this.currentOrganization = response;
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to fetch organization';
        console.error('Error fetching organization:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
    
    async createOrganization(organizationData) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.post('/organizations', organizationData);
        await this.fetchOrganizations(this.pagination.current_page);
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to create organization';
        console.error('Error creating organization:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
    
    async updateOrganization(id, organizationData) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.put(`/organizations/${id}`, organizationData);
        
        if (this.currentOrganization && this.currentOrganization.id === id) {
          this.currentOrganization = response;
        }
        
        await this.fetchOrganizations(this.pagination.current_page);
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to update organization';
        console.error('Error updating organization:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
    
    async deleteOrganization(id) {
      this.isLoading = true;
      this.error = null;
      
      try {
        await api.delete(`/organizations/${id}`);
        await this.fetchOrganizations(this.pagination.current_page);
      } catch (error) {
        this.error = error.message || 'Failed to delete organization';
        console.error('Error deleting organization:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
    
    setFilters(newFilters) {
      this.filters = { ...this.filters, ...newFilters };
    },
    
    resetFilters() {
      this.filters = {
        search: '',
        city: '',
        state: '',
        category: '',
        sort_by: 'name',
        sort_direction: 'asc',
      };
    },
  },
});
