import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api';

export const useKeywordStore = defineStore('keywords', () => {
  // State
  const keywords = ref([]);
  const isLoading = ref(false);
  const isLoadingDetails = ref(false);
  const isLoadingKeywordResponses = ref(false);
  const selectedKeywordDetails = ref(null);
  const selectedKeywordResponses = ref([]);
  
  // Actions
  async function fetchKeywords(organizationId) {
    isLoading.value = true;
    try {
      keywords.value = await api.get(`organizations/${organizationId}/keywords`);
    } catch (error) {
      console.error('Error fetching keywords:', error);
    } finally {
      isLoading.value = false;
    }
  }

  async function showKeyword(organizationId, id) {
    isLoadingDetails.value = true;
    try {
      selectedKeywordDetails.value = await api.get(`organizations/${organizationId}/keywords/${id}?include=prompts`);
    } catch (error) {
      console.error('Error fetching keyword details:', error);
      throw error;
    } finally {
      isLoadingDetails.value = false;
    }
  }
  
  async function createKeyword(organizationId, data) {
    isLoading.value = true;
    try {
      const newKeyword = await api.post(`organizations/${organizationId}/keywords`, data);
      keywords.value.unshift(newKeyword);
      return newKeyword;
    } catch (error) {
      console.error('Error creating keyword:', error);
      throw error;
    } finally {
      isLoading.value = false;
    }
  }
  
  async function deleteKeyword(organizationId,id) {
    try {
      await api.delete(`organizations/${organizationId}/keywords/${id}`);
      keywords.value = keywords.value.filter(k => k.id !== id);
    } catch (error) {
      console.error('Error deleting keyword:', error);
      throw error;
    } finally {
    }
  }

  // TODO: Test
  async function getKeywordResponses(keywordId, promptId) {
    isLoadingKeywordResponses.value = true;
    selectedKeywordResponses.value = [];
    try {
      selectedKeywordResponses.value = await api.get(`/keywords/${keywordId}/prompts/${promptId}/responses`);
      return selectedKeywordResponses.value;
    } catch (error) {
      console.error('Error fetching keyword responses:', error);
      throw error;
    } finally {
      isLoadingKeywordResponses.value = false;
    }
  }

  return {
    // State
    keywords: computed(() => keywords.value),
    isLoading,
    isLoadingDetails,
    isLoadingKeywordResponses,
    selectedKeywordDetails: computed(() => selectedKeywordDetails.value),
    selectedKeywordResponses: computed(() => selectedKeywordResponses.value),
    
    // Actions
    fetchKeywords,
    showKeyword,
    createKeyword,
    deleteKeyword,
    getKeywordResponses,
  };
});
