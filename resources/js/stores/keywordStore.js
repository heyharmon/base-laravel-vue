import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api';

export const useKeywordStore = defineStore('keywords', () => {
  // State
  const keywords = ref([]);
  const isLoading = ref(false);
  const isLoadingDetails = ref(false);
  const selectedKeywordDetails = ref(null);
  
  // Actions
  async function fetchKeywords() {
    isLoading.value = true;
    try {
      keywords.value = await api.get('/keywords');
    } catch (error) {
      console.error('Error fetching keywords:', error);
    } finally {
      isLoading.value = false;
    }
  }
  
  async function createKeyword(data) {
    isLoading.value = true;
    try {
      const newKeyword = await api.post('/keywords', data);
      keywords.value.unshift(newKeyword);
      return newKeyword;
    } catch (error) {
      console.error('Error creating keyword:', error);
      throw error;
    } finally {
      isLoading.value = false;
    }
  }
  
  async function updateKeyword(id, data) {
    isLoading.value = true;
    try {
      const updatedKeyword = await api.put(`/keywords/${id}`, data);
      
      const index = keywords.value.findIndex(k => k.id === id);
      if (index !== -1) {
        keywords.value[index] = updatedKeyword;
      }
      
      return updatedKeyword;
    } catch (error) {
      console.error('Error updating keyword:', error);
      throw error;
    } finally {
      isLoading.value = false;
    }
  }
  
  async function deleteKeyword(id) {
    isLoading.value = true;
    try {
      await api.delete(`/keywords/${id}`);
      keywords.value = keywords.value.filter(k => k.id !== id);
    } catch (error) {
      console.error('Error deleting keyword:', error);
      throw error;
    } finally {
      isLoading.value = false;
    }
  }
  
  async function fetchKeywordDetails(id) {
    isLoadingDetails.value = true;
    try {
      selectedKeywordDetails.value = await api.get(`/keywords/${id}?include=prompts`);
    } catch (error) {
      console.error('Error fetching keyword details:', error);
      throw error;
    } finally {
      isLoadingDetails.value = false;
    }
  }

  return {
    // State
    keywords: computed(() => keywords.value),
    isLoading,
    isLoadingDetails,
    selectedKeywordDetails: computed(() => selectedKeywordDetails.value),
    
    // Actions
    fetchKeywords,
    createKeyword,
    updateKeyword,
    deleteKeyword,
    fetchKeywordDetails,
  };
});
