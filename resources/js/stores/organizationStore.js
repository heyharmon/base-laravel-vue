import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api';

export const useOrganizationStore = defineStore('organization', () => {
  // State
  const organizations = ref([]);
  const currentOrganization = ref(null);
  const isLoading = ref(false);
  const error = ref(null);

  // Getters
  const ownedOrganizations = computed(() => 
    organizations.value ? organizations.value.filter(org => !org.is_competitor) : []
  );
  
  const competitorOrganizations = computed(() => 
    organizations.value ? organizations.value.filter(org => org.is_competitor) : []
  );

  // Actions
  async function fetchOrganizations() {
    isLoading.value = true;
    error.value = null;
    
    try {
      const response = await api.get('/organizations');
      organizations.value = response;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch organizations';
      console.error('Error fetching organizations:', err);
    } finally {
      isLoading.value = false;
    }
  }
  
  async function fetchOrganization(organizationId) {
    isLoading.value = true;
    error.value = null;
    
    try {
      const response = await api.get(`/organizations/${organizationId}`);
      currentOrganization.value = response;
      return response;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch organization details';
      console.error('Error fetching organization details:', err);
    } finally {
      isLoading.value = false;
    }
  }
  
  async function createOrganization(organizationData) {
    isLoading.value = true;
    error.value = null;
    
    try {
      const response = await api.post('/organizations', organizationData);
      await fetchOrganizations();
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create organization';
      console.error('Error creating organization:', err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }
  
  async function updateOrganization(organizationId, organizationData) {
    isLoading.value = true;
    error.value = null;
    
    try {
      const response = await api.put(`/organizations/${organizationId}`, organizationData);
      if (currentOrganization.value && currentOrganization.value.id === organizationId) {
        currentOrganization.value = { ...currentOrganization.value, ...organizationData };
      }
      await fetchOrganizations();
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update organization';
      console.error('Error updating organization:', err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }
  
  async function deleteOrganization(organizationId) {
    isLoading.value = true;
    error.value = null;
    
    try {
      const response = await api.delete(`/organizations/${organizationId}`);
      await fetchOrganizations();
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete organization';
      console.error('Error deleting organization:', err);
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  return {
    // State
    organizations,
    currentOrganization,
    isLoading,
    error,
    
    // Getters
    ownedOrganizations,
    competitorOrganizations,
    
    // Actions
    fetchOrganizations,
    fetchOrganization,
    createOrganization,
    updateOrganization,
    deleteOrganization
  };
});
